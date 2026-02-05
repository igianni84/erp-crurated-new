<?php

namespace App\Services\Commercial;

use App\Enums\Commercial\ExecutionStatus;
use App\Enums\Commercial\ExecutionType;
use App\Enums\Commercial\PolicyScopeType;
use App\Enums\Commercial\PriceSource;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Models\AuditLog;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Commercial\PricingPolicy;
use App\Models\Commercial\PricingPolicyExecution;
use App\Models\Pim\SellableSku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing PricingPolicy lifecycle and execution.
 *
 * Centralizes all PricingPolicy business logic including state transitions,
 * scope resolution, price calculation, and execution operations.
 */
class PricingPolicyService
{
    /**
     * Activate a PricingPolicy (draft → active).
     *
     * @throws \InvalidArgumentException If activation is not allowed
     */
    public function activate(PricingPolicy $policy): PricingPolicy
    {
        if (! $policy->canBeActivated()) {
            throw new \InvalidArgumentException(
                "Cannot activate PricingPolicy: current status '{$policy->status->label()}' is not Draft. "
                .'Only Draft policies can be activated.'
            );
        }

        $oldStatus = $policy->status;
        $policy->status = PricingPolicyStatus::Active;
        $policy->save();

        $this->logStatusTransition($policy, $oldStatus, PricingPolicyStatus::Active);

        return $policy;
    }

    /**
     * Pause a PricingPolicy (active → paused).
     *
     * @throws \InvalidArgumentException If pausing is not allowed
     */
    public function pause(PricingPolicy $policy): PricingPolicy
    {
        if (! $policy->canBePaused()) {
            throw new \InvalidArgumentException(
                "Cannot pause PricingPolicy: current status '{$policy->status->label()}' is not Active. "
                .'Only Active policies can be paused.'
            );
        }

        $oldStatus = $policy->status;
        $policy->status = PricingPolicyStatus::Paused;
        $policy->save();

        $this->logStatusTransition($policy, $oldStatus, PricingPolicyStatus::Paused);

        return $policy;
    }

    /**
     * Resume a PricingPolicy (paused → active).
     *
     * @throws \InvalidArgumentException If resuming is not allowed
     */
    public function resume(PricingPolicy $policy): PricingPolicy
    {
        if (! $policy->canBeResumed()) {
            throw new \InvalidArgumentException(
                "Cannot resume PricingPolicy: current status '{$policy->status->label()}' is not Paused. "
                .'Only Paused policies can be resumed.'
            );
        }

        $oldStatus = $policy->status;
        $policy->status = PricingPolicyStatus::Active;
        $policy->save();

        $this->logStatusTransition($policy, $oldStatus, PricingPolicyStatus::Active);

        return $policy;
    }

    /**
     * Archive a PricingPolicy (active/paused → archived).
     *
     * @throws \InvalidArgumentException If archiving is not allowed
     */
    public function archive(PricingPolicy $policy): PricingPolicy
    {
        if (! $policy->canBeArchived()) {
            throw new \InvalidArgumentException(
                "Cannot archive PricingPolicy: current status '{$policy->status->label()}' does not allow archiving. "
                .'Only Active or Paused policies can be archived.'
            );
        }

        $oldStatus = $policy->status;
        $policy->status = PricingPolicyStatus::Archived;
        $policy->save();

        $this->logStatusTransition($policy, $oldStatus, PricingPolicyStatus::Archived);

        return $policy;
    }

    /**
     * Execute a PricingPolicy (generate prices and write to target Price Book).
     *
     * @param  bool  $isDryRun  If true, preview results without writing to Price Book
     * @return ExecutionResult The result of the execution
     *
     * @throws \InvalidArgumentException If execution is not allowed
     */
    public function execute(PricingPolicy $policy, bool $isDryRun = false): ExecutionResult
    {
        // Validate execution is allowed
        if (! $isDryRun && ! $policy->canBeExecuted()) {
            throw new \InvalidArgumentException(
                "Cannot execute PricingPolicy: status '{$policy->status->label()}' does not allow execution. "
                .'Only Active policies can be executed. Dry runs are available for non-archived policies.'
            );
        }

        if ($isDryRun && ! $policy->canDryRun()) {
            throw new \InvalidArgumentException(
                'Cannot perform dry run: Archived policies cannot be previewed.'
            );
        }

        // Validate target Price Book exists
        $targetPriceBook = $policy->targetPriceBook;
        if ($targetPriceBook === null) {
            throw new \InvalidArgumentException(
                'Cannot execute PricingPolicy: no target Price Book is assigned. '
                .'Assign a target Price Book before executing.'
            );
        }

        // Resolve scope to get affected SKUs
        $skus = $this->resolveScope($policy);

        if ($skus->isEmpty()) {
            return $this->createExecutionResult(
                $policy,
                $isDryRun ? ExecutionType::DryRun : ExecutionType::Manual,
                ExecutionStatus::Success,
                0,
                0,
                0,
                [],
                [],
                'No SKUs matched the policy scope.'
            );
        }

        // Calculate prices for all SKUs
        $calculatedPrices = [];
        $errors = [];

        foreach ($skus as $sku) {
            try {
                $calculatedPrice = $this->calculatePrice($policy, $sku);
                if ($calculatedPrice !== null) {
                    $calculatedPrices[] = [
                        'sku' => $sku,
                        'sku_id' => $sku->id,
                        'sku_code' => $sku->sku_code,
                        'new_price' => $calculatedPrice,
                        'current_price' => $this->getCurrentPrice($targetPriceBook, $sku),
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'sku_id' => $sku->id,
                    'sku_code' => $sku->sku_code,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // If dry run, return preview without writing
        if ($isDryRun) {
            return $this->createExecutionResult(
                $policy,
                ExecutionType::DryRun,
                count($errors) === 0 ? ExecutionStatus::Success : (count($calculatedPrices) > 0 ? ExecutionStatus::Partial : ExecutionStatus::Failed),
                $skus->count(),
                count($calculatedPrices),
                count($errors),
                $calculatedPrices,
                $errors,
                $this->generateLogSummary($skus->count(), count($calculatedPrices), count($errors), true)
            );
        }

        // Write prices to target Price Book
        return DB::transaction(function () use ($policy, $targetPriceBook, $skus, $calculatedPrices, $errors): ExecutionResult {
            $pricesWritten = 0;

            foreach ($calculatedPrices as $priceData) {
                $this->writePrice(
                    $targetPriceBook,
                    $priceData['sku_id'],
                    $priceData['new_price'],
                    $policy->id
                );
                $pricesWritten++;
            }

            // Update last_executed_at
            $policy->last_executed_at = now();
            $policy->save();

            // Determine execution status
            $status = ExecutionStatus::Success;
            if (count($errors) > 0 && $pricesWritten > 0) {
                $status = ExecutionStatus::Partial;
            } elseif ($pricesWritten === 0) {
                $status = ExecutionStatus::Failed;
            }

            // Create execution log
            $execution = PricingPolicyExecution::create([
                'pricing_policy_id' => $policy->id,
                'executed_at' => now(),
                'execution_type' => ExecutionType::Manual,
                'skus_processed' => $skus->count(),
                'prices_generated' => $pricesWritten,
                'errors_count' => count($errors),
                'status' => $status,
                'log_summary' => $this->generateLogSummary($skus->count(), $pricesWritten, count($errors), false),
            ]);

            return $this->createExecutionResult(
                $policy,
                ExecutionType::Manual,
                $status,
                $skus->count(),
                $pricesWritten,
                count($errors),
                $calculatedPrices,
                $errors,
                $execution->log_summary,
                $execution
            );
        });
    }

    /**
     * Resolve the scope of a PricingPolicy to a collection of Sellable SKUs.
     *
     * @return Collection<int, SellableSku>
     */
    public function resolveScope(PricingPolicy $policy): Collection
    {
        $scope = $policy->scope;

        if ($scope === null) {
            // No scope defined - return empty collection
            return new Collection;
        }

        $query = SellableSku::query()
            ->where('lifecycle_status', SellableSku::STATUS_ACTIVE);

        // Apply scope type filter
        switch ($scope->scope_type) {
            case PolicyScopeType::All:
                // No additional filter needed
                break;

            case PolicyScopeType::Category:
                if ($scope->scope_reference !== null) {
                    // Filter by category name (via wine variant -> wine master)
                    $query->whereHas('wineVariant.wineMaster', function ($q) use ($scope): void {
                        $q->where('name', 'like', '%'.$scope->scope_reference.'%');
                    });
                }
                break;

            case PolicyScopeType::Product:
                if ($scope->scope_reference !== null) {
                    // Filter by product/wine name
                    $query->whereHas('wineVariant.wineMaster', function ($q) use ($scope): void {
                        $q->where('name', 'like', '%'.$scope->scope_reference.'%');
                    });
                }
                break;

            case PolicyScopeType::Sku:
                if ($scope->scope_reference !== null) {
                    // scope_reference contains comma-separated SKU IDs
                    $skuIds = array_filter(array_map('trim', explode(',', $scope->scope_reference)));
                    if (! empty($skuIds)) {
                        $query->whereIn('id', $skuIds);
                    }
                }
                break;
        }

        // Market restrictions are informational only at SKU level
        // Channel restrictions are informational only at SKU level
        // These are enforced at Offer level, not at SKU resolution

        return $query->get();
    }

    /**
     * Calculate the price for a single Sellable SKU based on the policy logic.
     *
     * @return string|null The calculated price as a decimal string, or null if cannot calculate
     */
    public function calculatePrice(PricingPolicy $policy, SellableSku $sku): ?string
    {
        $logicDefinition = $policy->logic_definition;

        return match ($policy->policy_type) {
            PricingPolicyType::CostPlusMargin => $this->calculateCostPlusMargin($policy, $sku, $logicDefinition),
            PricingPolicyType::ReferencePriceBook => $this->calculateReferencePriceBook($policy, $sku, $logicDefinition),
            PricingPolicyType::IndexBased => $this->calculateIndexBased($policy, $sku, $logicDefinition),
            PricingPolicyType::FixedAdjustment => $this->calculateFixedAdjustment($policy, $sku, $logicDefinition),
            PricingPolicyType::Rounding => $this->calculateRounding($policy, $sku, $logicDefinition),
        };
    }

    /**
     * Calculate price using Cost + Margin method.
     *
     * @param  array<string, mixed>  $logicDefinition
     */
    protected function calculateCostPlusMargin(PricingPolicy $policy, SellableSku $sku, array $logicDefinition): ?string
    {
        // Get cost from SKU (placeholder - in a real implementation, this would come from cost data)
        // For now, we'll use a base cost calculation or return null if no cost available
        $baseCost = $this->getSkuCost($sku, $logicDefinition);

        if ($baseCost === null) {
            return null;
        }

        $marginPercentage = (float) ($logicDefinition['margin_percentage'] ?? 0);
        $markupValue = (float) ($logicDefinition['markup_value'] ?? 0);

        // Apply margin: price = cost * (1 + margin/100)
        $price = $baseCost * (1 + $marginPercentage / 100);

        // Add fixed markup if present
        if ($markupValue > 0) {
            $price += $markupValue;
        }

        // Apply rounding if configured
        $price = $this->applyRounding($price, $logicDefinition);

        return number_format($price, 2, '.', '');
    }

    /**
     * Calculate price using Reference Price Book method.
     *
     * @param  array<string, mixed>  $logicDefinition
     */
    protected function calculateReferencePriceBook(PricingPolicy $policy, SellableSku $sku, array $logicDefinition): ?string
    {
        $sourcePriceBookId = $logicDefinition['source_price_book_id'] ?? null;

        if ($sourcePriceBookId === null) {
            return null;
        }

        $sourcePriceBook = PriceBook::find($sourcePriceBookId);
        if ($sourcePriceBook === null) {
            return null;
        }

        $sourceEntry = $sourcePriceBook->entries()
            ->where('sellable_sku_id', $sku->id)
            ->first();

        if ($sourceEntry === null) {
            return null;
        }

        $basePrice = (float) $sourceEntry->base_price;

        // Apply adjustment
        $adjustmentType = $logicDefinition['adjustment_type'] ?? 'percentage';
        $adjustmentValue = (float) ($logicDefinition['adjustment_value'] ?? 0);

        if ($adjustmentType === 'percentage') {
            $price = $basePrice * (1 + $adjustmentValue / 100);
        } else {
            $price = $basePrice + $adjustmentValue;
        }

        // Apply rounding if configured
        $price = $this->applyRounding($price, $logicDefinition);

        return number_format($price, 2, '.', '');
    }

    /**
     * Calculate price using Index-Based method (EMP or FX).
     *
     * @param  array<string, mixed>  $logicDefinition
     */
    protected function calculateIndexBased(PricingPolicy $policy, SellableSku $sku, array $logicDefinition): ?string
    {
        $indexType = $logicDefinition['index_type'] ?? 'emp';

        if ($indexType === 'emp') {
            // Get EMP value for the SKU
            $market = $logicDefinition['emp_market'] ?? null;
            $emp = $sku->estimatedMarketPrices()
                ->when($market, fn ($q) => $q->where('market', $market))
                ->orderBy('fetched_at', 'desc')
                ->first();

            if ($emp === null) {
                return null;
            }

            $basePrice = (float) $emp->emp_value;
        } else {
            // FX-based calculation - placeholder
            // In real implementation, this would use FX rates
            return null;
        }

        // Apply multiplier and adjustment
        $multiplier = (float) ($logicDefinition['index_multiplier'] ?? 1.0);
        $fixedAdjustment = (float) ($logicDefinition['index_fixed_adjustment'] ?? 0);

        $price = ($basePrice * $multiplier) + $fixedAdjustment;

        // Apply rounding if configured
        $price = $this->applyRounding($price, $logicDefinition);

        return number_format($price, 2, '.', '');
    }

    /**
     * Calculate price using Fixed Adjustment method.
     *
     * @param  array<string, mixed>  $logicDefinition
     */
    protected function calculateFixedAdjustment(PricingPolicy $policy, SellableSku $sku, array $logicDefinition): ?string
    {
        // Get current price from target Price Book
        $targetPriceBook = $policy->targetPriceBook;
        if ($targetPriceBook === null) {
            return null;
        }

        $currentEntry = $targetPriceBook->entries()
            ->where('sellable_sku_id', $sku->id)
            ->first();

        if ($currentEntry === null) {
            return null;
        }

        $basePrice = (float) $currentEntry->base_price;

        $adjustmentType = $logicDefinition['adjustment_type'] ?? 'percentage';
        $adjustmentValue = (float) ($logicDefinition['adjustment_value'] ?? 0);

        if ($adjustmentType === 'percentage') {
            $price = $basePrice * (1 + $adjustmentValue / 100);
        } else {
            $price = $basePrice + $adjustmentValue;
        }

        // Apply rounding if configured
        $price = $this->applyRounding($price, $logicDefinition);

        return number_format($price, 2, '.', '');
    }

    /**
     * Calculate price using Rounding method.
     *
     * @param  array<string, mixed>  $logicDefinition
     */
    protected function calculateRounding(PricingPolicy $policy, SellableSku $sku, array $logicDefinition): ?string
    {
        // Get current price from target Price Book
        $targetPriceBook = $policy->targetPriceBook;
        if ($targetPriceBook === null) {
            return null;
        }

        $currentEntry = $targetPriceBook->entries()
            ->where('sellable_sku_id', $sku->id)
            ->first();

        if ($currentEntry === null) {
            return null;
        }

        $basePrice = (float) $currentEntry->base_price;

        // Apply rounding
        $price = $this->applyRounding($basePrice, $logicDefinition);

        return number_format($price, 2, '.', '');
    }

    /**
     * Apply rounding rules to a price.
     *
     * @param  array<string, mixed>  $logicDefinition
     */
    protected function applyRounding(float $price, array $logicDefinition): float
    {
        $roundingRule = $logicDefinition['rounding_rule'] ?? null;
        $roundingDirection = $logicDefinition['rounding_direction'] ?? 'nearest';

        if ($roundingRule === null) {
            return $price;
        }

        $integerPart = floor($price);
        $decimalPart = $price - $integerPart;

        switch ($roundingRule) {
            case '.99':
                return $this->roundToEnding($price, 0.99, $roundingDirection);
            case '.95':
                return $this->roundToEnding($price, 0.95, $roundingDirection);
            case '.90':
                return $this->roundToEnding($price, 0.90, $roundingDirection);
            case '.00':
                return $this->roundToEnding($price, 0.00, $roundingDirection);
            case 'nearest_5':
                return $this->roundToNearest($price, 5, $roundingDirection);
            case 'nearest_10':
                return $this->roundToNearest($price, 10, $roundingDirection);
            default:
                return $price;
        }
    }

    /**
     * Round to a specific decimal ending.
     */
    protected function roundToEnding(float $price, float $ending, string $direction): float
    {
        $base = floor($price);
        $target = $base + $ending;

        if ($direction === 'up') {
            return $price <= $target ? $target : $target + 1;
        } elseif ($direction === 'down') {
            return $price >= $target ? $target : ($base > 0 ? $base - 1 + $ending : $ending);
        } else {
            // nearest
            $lowerTarget = $target <= $price ? $target : $target - 1;
            $upperTarget = $target >= $price ? $target : $target + 1;

            return abs($price - $lowerTarget) <= abs($price - $upperTarget) ? $lowerTarget : $upperTarget;
        }
    }

    /**
     * Round to nearest multiple.
     */
    protected function roundToNearest(float $price, int $multiple, string $direction): float
    {
        if ($direction === 'up') {
            return ceil($price / $multiple) * $multiple;
        } elseif ($direction === 'down') {
            return floor($price / $multiple) * $multiple;
        } else {
            return round($price / $multiple) * $multiple;
        }
    }

    /**
     * Get cost for a SKU.
     *
     * @param  array<string, mixed>  $logicDefinition
     */
    protected function getSkuCost(SellableSku $sku, array $logicDefinition): ?float
    {
        $costSource = $logicDefinition['cost_source'] ?? 'product_catalog';

        // Placeholder implementation - in a real system, this would fetch from:
        // - Product catalog cost
        // - Bottle SKU cost
        // - Manual cost input
        // For now, return null to indicate cost data is not available
        // This can be extended when cost data sources are implemented

        // Try to get EMP as a fallback cost reference
        $emp = $sku->estimatedMarketPrices()->orderBy('fetched_at', 'desc')->first();
        if ($emp !== null) {
            // Use EMP as a proxy for cost (e.g., cost = 60% of EMP)
            return (float) $emp->emp_value * 0.6;
        }

        return null;
    }

    /**
     * Get current price from a Price Book for a SKU.
     */
    protected function getCurrentPrice(PriceBook $priceBook, SellableSku $sku): ?string
    {
        $entry = $priceBook->entries()
            ->where('sellable_sku_id', $sku->id)
            ->first();

        return $entry?->base_price;
    }

    /**
     * Write a price to the target Price Book.
     */
    protected function writePrice(PriceBook $priceBook, string $skuId, string $price, string $policyId): void
    {
        PriceBookEntry::updateOrCreate(
            [
                'price_book_id' => $priceBook->id,
                'sellable_sku_id' => $skuId,
            ],
            [
                'base_price' => $price,
                'source' => PriceSource::PolicyGenerated,
                'policy_id' => $policyId,
            ]
        );
    }

    /**
     * Generate a log summary message.
     */
    protected function generateLogSummary(int $skusProcessed, int $pricesGenerated, int $errors, bool $isDryRun): string
    {
        $type = $isDryRun ? 'Dry run' : 'Execution';

        if ($errors === 0 && $pricesGenerated > 0) {
            return "{$type} completed successfully: {$pricesGenerated} prices generated from {$skusProcessed} SKUs.";
        } elseif ($errors > 0 && $pricesGenerated > 0) {
            return "{$type} completed with warnings: {$pricesGenerated} prices generated, {$errors} errors from {$skusProcessed} SKUs.";
        } elseif ($pricesGenerated === 0 && $skusProcessed > 0) {
            return "{$type} completed: No prices could be generated from {$skusProcessed} SKUs. {$errors} errors.";
        } else {
            return "{$type} completed: No SKUs matched the policy scope.";
        }
    }

    /**
     * Create an execution result object.
     *
     * @param  array<int, array{sku: SellableSku, sku_id: string, sku_code: string, new_price: string, current_price: string|null}>  $calculatedPrices
     * @param  array<int, array{sku_id: string, sku_code: string, error: string}>  $errors
     */
    protected function createExecutionResult(
        PricingPolicy $policy,
        ExecutionType $executionType,
        ExecutionStatus $status,
        int $skusProcessed,
        int $pricesGenerated,
        int $errorsCount,
        array $calculatedPrices,
        array $errors,
        string $logSummary,
        ?PricingPolicyExecution $execution = null
    ): ExecutionResult {
        return new ExecutionResult(
            policy: $policy,
            executionType: $executionType,
            status: $status,
            skusProcessed: $skusProcessed,
            pricesGenerated: $pricesGenerated,
            errorsCount: $errorsCount,
            calculatedPrices: $calculatedPrices,
            errors: $errors,
            logSummary: $logSummary,
            execution: $execution
        );
    }

    /**
     * Log a status transition to the audit log.
     */
    protected function logStatusTransition(
        PricingPolicy $policy,
        PricingPolicyStatus $oldStatus,
        PricingPolicyStatus $newStatus
    ): void {
        $policy->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            'new_values' => [
                'status' => $newStatus->value,
                'status_label' => $newStatus->label(),
            ],
            'user_id' => Auth::id(),
        ]);
    }
}

/**
 * Result object for PricingPolicy execution.
 */
class ExecutionResult
{
    /**
     * @param  array<int, array{sku: SellableSku, sku_id: string, sku_code: string, new_price: string, current_price: string|null}>  $calculatedPrices
     * @param  array<int, array{sku_id: string, sku_code: string, error: string}>  $errors
     */
    public function __construct(
        public readonly PricingPolicy $policy,
        public readonly ExecutionType $executionType,
        public readonly ExecutionStatus $status,
        public readonly int $skusProcessed,
        public readonly int $pricesGenerated,
        public readonly int $errorsCount,
        public readonly array $calculatedPrices,
        public readonly array $errors,
        public readonly string $logSummary,
        public readonly ?PricingPolicyExecution $execution = null,
    ) {}

    /**
     * Check if the execution was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === ExecutionStatus::Success;
    }

    /**
     * Check if the execution had partial success.
     */
    public function isPartial(): bool
    {
        return $this->status === ExecutionStatus::Partial;
    }

    /**
     * Check if the execution failed.
     */
    public function isFailed(): bool
    {
        return $this->status === ExecutionStatus::Failed;
    }

    /**
     * Check if this was a dry run.
     */
    public function isDryRun(): bool
    {
        return $this->executionType === ExecutionType::DryRun;
    }

    /**
     * Check if there were any errors.
     */
    public function hasErrors(): bool
    {
        return $this->errorsCount > 0;
    }

    /**
     * Get the price changes for display.
     *
     * @return array<int, array{sku_code: string, current_price: string|null, new_price: string, change: float|null, change_percent: float|null}>
     */
    public function getPriceChanges(): array
    {
        $changes = [];

        foreach ($this->calculatedPrices as $priceData) {
            $currentPrice = $priceData['current_price'] !== null ? (float) $priceData['current_price'] : null;
            $newPrice = (float) $priceData['new_price'];

            $change = $currentPrice !== null ? $newPrice - $currentPrice : null;
            $changePercent = $currentPrice !== null && $currentPrice > 0
                ? round((($newPrice - $currentPrice) / $currentPrice) * 100, 2)
                : null;

            $changes[] = [
                'sku_code' => $priceData['sku_code'],
                'current_price' => $priceData['current_price'],
                'new_price' => $priceData['new_price'],
                'change' => $change,
                'change_percent' => $changePercent,
            ];
        }

        return $changes;
    }
}
