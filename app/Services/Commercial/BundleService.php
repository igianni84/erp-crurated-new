<?php

namespace App\Services\Commercial;

use App\Enums\Commercial\BundleStatus;
use App\Models\AuditLog;
use App\Models\Commercial\Bundle;
use App\Models\Commercial\BundleComponent;
use App\Models\Commercial\PriceBook;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing Bundle lifecycle and operations.
 *
 * Centralizes all Bundle business logic including state transitions,
 * component validation, and price calculations.
 */
class BundleService
{
    // =========================================================================
    // Status Transitions
    // =========================================================================

    /**
     * Activate a Bundle (draft → active).
     *
     * Validates all components and synchronizes with PIM to create/update
     * the composite Sellable SKU. Only bundles with valid components can be activated.
     *
     * @throws \InvalidArgumentException If activation is not allowed
     */
    public function activate(Bundle $bundle): Bundle
    {
        if (! $bundle->isDraft()) {
            throw new \InvalidArgumentException(
                "Cannot activate Bundle: current status '{$bundle->status->label()}' is not Draft. "
                .'Only Draft Bundles can be activated.'
            );
        }

        if (! $bundle->hasComponents()) {
            throw new \InvalidArgumentException(
                'Cannot activate Bundle: it must have at least one component. '
                .'Add components before activating.'
            );
        }

        // Validate all components
        $validation = $this->validateComponents($bundle);
        if (! $validation['valid']) {
            $errorMessages = array_map(
                fn (array $errors) => implode(', ', $errors),
                $validation['errors']
            );
            throw new \InvalidArgumentException(
                'Cannot activate Bundle: component validation failed. '
                .implode('; ', $errorMessages)
            );
        }

        return DB::transaction(function () use ($bundle): Bundle {
            $oldStatus = $bundle->status;
            $bundle->status = BundleStatus::Active;
            $bundle->save();

            // TODO: Sync with PIM to create/update composite Sellable SKU
            // This would create a SellableSku record with source='composite'
            // and link it to the bundle via the bundle_sku field.
            // Implementation depends on PIM module capabilities.
            $this->syncWithPim($bundle);

            $this->logStatusTransition($bundle, $oldStatus, BundleStatus::Active);

            return $bundle->fresh() ?? $bundle;
        });
    }

    /**
     * Deactivate a Bundle (active → inactive).
     *
     * Inactive bundles are not available for sale but components remain linked.
     *
     * @throws \InvalidArgumentException If deactivation is not allowed
     */
    public function deactivate(Bundle $bundle): Bundle
    {
        if (! $bundle->isActive()) {
            throw new \InvalidArgumentException(
                "Cannot deactivate Bundle: current status '{$bundle->status->label()}' is not Active. "
                .'Only Active Bundles can be deactivated.'
            );
        }

        $oldStatus = $bundle->status;
        $bundle->status = BundleStatus::Inactive;
        $bundle->save();

        $this->logStatusTransition($bundle, $oldStatus, BundleStatus::Inactive);

        return $bundle;
    }

    /**
     * Reactivate a Bundle (inactive → active).
     *
     * Reactivates a previously deactivated bundle after validating components.
     *
     * @throws \InvalidArgumentException If reactivation is not allowed
     */
    public function reactivate(Bundle $bundle): Bundle
    {
        if (! $bundle->isInactive()) {
            throw new \InvalidArgumentException(
                "Cannot reactivate Bundle: current status '{$bundle->status->label()}' is not Inactive. "
                .'Only Inactive Bundles can be reactivated.'
            );
        }

        // Validate components again before reactivation
        $validation = $this->validateComponents($bundle);
        if (! $validation['valid']) {
            $errorMessages = array_map(
                fn (array $errors) => implode(', ', $errors),
                $validation['errors']
            );
            throw new \InvalidArgumentException(
                'Cannot reactivate Bundle: component validation failed. '
                .implode('; ', $errorMessages)
            );
        }

        $oldStatus = $bundle->status;
        $bundle->status = BundleStatus::Active;
        $bundle->save();

        $this->logStatusTransition($bundle, $oldStatus, BundleStatus::Active);

        return $bundle;
    }

    // =========================================================================
    // Price Calculation
    // =========================================================================

    /**
     * Calculate the bundle price based on its pricing logic and a Price Book.
     *
     * @return BundlePriceCalculation Result containing price breakdown
     *
     * @throws \InvalidArgumentException If price cannot be calculated
     */
    public function calculatePrice(Bundle $bundle, PriceBook $priceBook): BundlePriceCalculation
    {
        $componentsPrice = $this->getComponentsPrice($bundle, $priceBook);

        if ($componentsPrice === null) {
            throw new \InvalidArgumentException(
                'Cannot calculate bundle price: one or more components are missing prices in the Price Book.'
            );
        }

        $finalPrice = $bundle->calculateBundlePrice($componentsPrice->total);

        $discount = $componentsPrice->total - $finalPrice;
        $discountPercentage = $componentsPrice->total > 0
            ? ($discount / $componentsPrice->total) * 100
            : 0;

        return new BundlePriceCalculation(
            componentsTotal: $componentsPrice->total,
            finalPrice: $finalPrice,
            discount: $discount,
            discountPercentage: $discountPercentage,
            currency: $priceBook->currency,
            componentPrices: $componentsPrice->items,
            pricingLogic: $bundle->pricing_logic,
            fixedPrice: $bundle->getFixedPriceValue(),
            percentageOff: $bundle->getPercentageOffValue()
        );
    }

    /**
     * Calculate the bundle price as a simple float value.
     *
     * @return float|null The calculated price, or null if calculation fails
     */
    public function calculatePriceValue(Bundle $bundle, PriceBook $priceBook): ?float
    {
        try {
            $calculation = $this->calculatePrice($bundle, $priceBook);

            return $calculation->finalPrice;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Get the sum of all component prices from a Price Book.
     *
     * @return ComponentsPriceResult|null Price breakdown, or null if any component is missing
     */
    public function getComponentsPrice(Bundle $bundle, PriceBook $priceBook): ?ComponentsPriceResult
    {
        $bundle->load('components.sellableSku');
        $components = $bundle->components;

        if ($components->isEmpty()) {
            return null;
        }

        $total = 0.0;
        /** @var array<string, ComponentPriceItem> $items */
        $items = [];
        $missingPrices = [];

        foreach ($components as $component) {
            $sku = $component->sellableSku;

            if ($sku === null) {
                $missingPrices[] = $component->id;

                continue;
            }

            // Find the price entry for this SKU in the Price Book
            $entry = $priceBook->entries()
                ->where('sellable_sku_id', $sku->id)
                ->first();

            if ($entry === null) {
                $missingPrices[] = $component->getSkuCode() ?? $component->id;

                continue;
            }

            $unitPrice = (float) $entry->base_price;
            $lineTotal = $unitPrice * $component->quantity;
            $total += $lineTotal;

            $items[$component->id] = new ComponentPriceItem(
                componentId: $component->id,
                skuCode: $sku->sku_code,
                quantity: $component->quantity,
                unitPrice: $unitPrice,
                lineTotal: $lineTotal
            );
        }

        // If any component is missing a price, return null
        if (! empty($missingPrices)) {
            return null;
        }

        return new ComponentsPriceResult(
            total: $total,
            items: $items
        );
    }

    // =========================================================================
    // Component Validation
    // =========================================================================

    /**
     * Validate all components of a bundle for activation.
     *
     * Checks that:
     * - All components have valid quantities (> 0)
     * - All component SKUs exist and are active
     * - All component SKUs have active allocations
     *
     * @return array{valid: bool, errors: array<string, array<string>>}
     */
    public function validateComponents(Bundle $bundle): array
    {
        $bundle->load('components.sellableSku');
        $components = $bundle->components;

        if ($components->isEmpty()) {
            return [
                'valid' => false,
                'errors' => ['bundle' => ['Bundle must have at least one component']],
            ];
        }

        $errors = [];
        $hasAllAllocations = true;

        foreach ($components as $component) {
            $componentErrors = $component->validate();

            if (! empty($componentErrors)) {
                $errors[$component->id] = array_values($componentErrors);
            }

            // Check for allocation
            if (! $component->hasAllocation()) {
                $skuCode = $component->getSkuCode() ?? 'Unknown';
                if (! isset($errors[$component->id])) {
                    $errors[$component->id] = [];
                }
                $errors[$component->id][] = "SKU '{$skuCode}' does not have an active allocation";
                $hasAllAllocations = false;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if all components have valid allocations.
     */
    public function allComponentsHaveAllocations(Bundle $bundle): bool
    {
        $bundle->load('components.sellableSku');

        foreach ($bundle->components as $component) {
            if (! $component->hasAllocation()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get components that are missing allocations.
     *
     * @return Collection<int, BundleComponent>
     */
    public function getComponentsWithoutAllocations(Bundle $bundle): Collection
    {
        $bundle->load('components.sellableSku');

        return $bundle->components->filter(
            fn (BundleComponent $component): bool => ! $component->hasAllocation()
        );
    }

    /**
     * Get components that have inactive or missing SKUs.
     *
     * @return Collection<int, BundleComponent>
     */
    public function getComponentsWithInactiveSku(Bundle $bundle): Collection
    {
        $bundle->load('components.sellableSku');

        return $bundle->components->filter(
            fn (BundleComponent $component): bool => ! $component->hasActiveSku()
        );
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if a Bundle can be activated.
     */
    public function canActivate(Bundle $bundle): bool
    {
        if (! $bundle->isDraft() || ! $bundle->hasComponents()) {
            return false;
        }

        $validation = $this->validateComponents($bundle);

        return $validation['valid'];
    }

    /**
     * Check if a Bundle can be deactivated.
     */
    public function canDeactivate(Bundle $bundle): bool
    {
        return $bundle->isActive();
    }

    /**
     * Check if a Bundle can be reactivated.
     */
    public function canReactivate(Bundle $bundle): bool
    {
        if (! $bundle->isInactive()) {
            return false;
        }

        $validation = $this->validateComponents($bundle);

        return $validation['valid'];
    }

    /**
     * Get a summary of bundle components with their prices.
     *
     * @return array<int, array{component_id: string, sku_code: string|null, wine_name: string|null, quantity: int, has_allocation: bool, unit_price: float|null, line_total: float|null}>
     */
    public function getComponentsSummary(Bundle $bundle, ?PriceBook $priceBook = null): array
    {
        $bundle->load('components.sellableSku');
        $summary = [];

        foreach ($bundle->components as $component) {
            $sku = $component->sellableSku;
            $unitPrice = null;
            $lineTotal = null;

            if ($priceBook !== null && $sku !== null) {
                $entry = $priceBook->entries()
                    ->where('sellable_sku_id', $sku->id)
                    ->first();

                if ($entry !== null) {
                    $unitPrice = (float) $entry->base_price;
                    $lineTotal = $unitPrice * $component->quantity;
                }
            }

            $summary[] = [
                'component_id' => $component->id,
                'sku_code' => $component->getSkuCode(),
                'wine_name' => $component->getWineName(),
                'quantity' => $component->quantity,
                'has_allocation' => $component->hasAllocation(),
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        return $summary;
    }

    // =========================================================================
    // PIM Integration
    // =========================================================================

    /**
     * Sync bundle with PIM to create/update the composite Sellable SKU.
     *
     * This is a placeholder for PIM integration. When PIM module is available,
     * this would create or update a SellableSku with source='composite' and
     * link it to the bundle via the bundle_sku field.
     */
    protected function syncWithPim(Bundle $bundle): void
    {
        // TODO: Implement PIM sync when PIM module supports composite SKUs
        // Example implementation:
        //
        // $sellableSku = SellableSku::firstOrNew(['sku_code' => $bundle->bundle_sku]);
        // $sellableSku->source = 'composite';
        // $sellableSku->composite_bundle_id = $bundle->id;
        // $sellableSku->lifecycle_status = 'active';
        // $sellableSku->save();
        //
        // For now, we just log that sync would happen
    }

    // =========================================================================
    // Audit Logging
    // =========================================================================

    /**
     * Log a status transition to the audit log.
     */
    protected function logStatusTransition(
        Bundle $bundle,
        BundleStatus $oldStatus,
        BundleStatus $newStatus
    ): void {
        // Create audit log if bundle has auditable trait
        // For now, we'll use a manual approach since Bundle uses SoftDeletes but not Auditable
        DB::table('audit_logs')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'auditable_type' => Bundle::class,
            'auditable_id' => $bundle->id,
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => json_encode([
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ]),
            'new_values' => json_encode([
                'status' => $newStatus->value,
                'status_label' => $newStatus->label(),
            ]),
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

// =========================================================================
// Value Objects
// =========================================================================

/**
 * Value object representing a component's price breakdown.
 */
readonly class ComponentPriceItem
{
    public function __construct(
        public string $componentId,
        public string $skuCode,
        public int $quantity,
        public float $unitPrice,
        public float $lineTotal
    ) {}
}

/**
 * Value object representing the total components price result.
 */
readonly class ComponentsPriceResult
{
    /**
     * @param  array<string, ComponentPriceItem>  $items
     */
    public function __construct(
        public float $total,
        public array $items
    ) {}
}

/**
 * Value object representing a complete bundle price calculation.
 */
readonly class BundlePriceCalculation
{
    /**
     * @param  array<string, ComponentPriceItem>  $componentPrices
     */
    public function __construct(
        public float $componentsTotal,
        public float $finalPrice,
        public float $discount,
        public float $discountPercentage,
        public string $currency,
        public array $componentPrices,
        public \App\Enums\Commercial\BundlePricingLogic $pricingLogic,
        public ?float $fixedPrice,
        public ?float $percentageOff
    ) {}

    /**
     * Get a formatted price string.
     */
    public function getFormattedFinalPrice(): string
    {
        return $this->currency.' '.number_format($this->finalPrice, 2);
    }

    /**
     * Get a formatted components total string.
     */
    public function getFormattedComponentsTotal(): string
    {
        return $this->currency.' '.number_format($this->componentsTotal, 2);
    }

    /**
     * Get a formatted discount string.
     */
    public function getFormattedDiscount(): string
    {
        if ($this->discount <= 0) {
            return 'No discount';
        }

        return $this->currency.' '.number_format($this->discount, 2)
            .' ('.number_format($this->discountPercentage, 1).'% off)';
    }

    /**
     * Get a pricing summary string.
     */
    public function getSummary(): string
    {
        $lines = [];
        $lines[] = 'Components Total: '.$this->getFormattedComponentsTotal();

        if ($this->discount > 0) {
            $lines[] = 'Discount: '.$this->getFormattedDiscount();
        }

        $lines[] = 'Bundle Price: '.$this->getFormattedFinalPrice();

        return implode("\n", $lines);
    }
}
