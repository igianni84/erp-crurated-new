<?php

namespace App\Services\Finance;

use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\StorageBillingStatus;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\MovementType;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\StorageBillingPeriod;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\MovementItem;
use App\Models\Inventory\SerializedBottle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for storage billing operations.
 *
 * Handles storage usage calculations, rate tier determination,
 * and invoice generation for storage fee (INV3) invoices.
 *
 * The billing calculation uses bottle-days:
 * - For each bottle stored during the period, calculate actual days stored
 * - bottle_days = sum(days each bottle was stored)
 * - calculated_amount = bottle_days * unit_rate
 *
 * This service considers:
 * - Inbound movements (bottles entering storage)
 * - Outbound movements (bottles leaving storage via shipment/consumption)
 * - Transfers between locations (custody changes)
 * - Point-in-time snapshots at period boundaries
 */
class StorageBillingService
{
    /**
     * Bottle states that count as "in storage" for billing purposes.
     *
     * @var list<BottleState>
     */
    protected const STORAGE_STATES = [
        BottleState::Stored,
        BottleState::ReservedForPicking,
    ];

    /**
     * Movement types that represent bottles entering storage.
     *
     * @var list<MovementType>
     */
    protected const INBOUND_MOVEMENT_TYPES = [
        MovementType::ConsignmentPlacement,
    ];

    /**
     * Movement types that represent bottles leaving storage.
     *
     * @var list<MovementType>
     */
    protected const OUTBOUND_MOVEMENT_TYPES = [
        MovementType::EventShipment,
        MovementType::EventConsumption,
        MovementType::ConsignmentReturn,
    ];

    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Calculate storage usage for a customer during a billing period.
     *
     * This is the main entry point for usage calculations. It returns
     * all data needed to create a StorageBillingPeriod and INV3 invoice.
     *
     * The calculation considers:
     * - Bottles present at period start (snapshot)
     * - Bottles entering storage during period (inbound)
     * - Bottles leaving storage during period (outbound/transfers)
     * - Bottles present at period end (snapshot)
     *
     * @param  Customer  $customer  The customer to calculate for
     * @param  Carbon  $periodStart  The start of the billing period (inclusive)
     * @param  Carbon  $periodEnd  The end of the billing period (inclusive)
     * @param  Location|null  $location  Optional specific location (null = all locations)
     * @return array{
     *     bottle_count: int,
     *     bottle_days: int,
     *     unit_rate: string,
     *     calculated_amount: string,
     *     currency: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function calculateUsage(
        Customer $customer,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?Location $location = null
    ): array {
        $periodStartDay = $periodStart->startOfDay()->copy();
        $periodEndDay = $periodEnd->endOfDay()->copy();

        // Calculate bottle-days using detailed movement tracking
        $usageDetails = $this->getBottleDays($customer, $periodStartDay, $periodEndDay, $location);

        // Get the applicable unit rate based on volume tier
        $unitRate = $this->getApplicableRate($customer, $location, $usageDetails['average_bottle_count']);

        // Calculate the total amount
        $calculatedAmount = bcmul((string) $usageDetails['bottle_days'], $unitRate, 2);

        // Apply minimum charge if configured
        $minimumCharge = config('finance.storage.minimum_charge', '0.00');
        if (bccomp($calculatedAmount, $minimumCharge, 2) < 0 && $usageDetails['bottle_days'] > 0) {
            $calculatedAmount = $minimumCharge;
        }

        // Get the rate tier label for metadata
        $rateTierLabel = $this->getRateTierLabel($usageDetails['average_bottle_count']);

        return [
            'bottle_count' => $usageDetails['average_bottle_count'],
            'bottle_days' => $usageDetails['bottle_days'],
            'unit_rate' => $unitRate,
            'calculated_amount' => $calculatedAmount,
            'currency' => config('finance.pricing.base_currency', 'EUR'),
            'metadata' => [
                'calculation_method' => 'movement_based',
                'period_start' => $periodStartDay->toDateString(),
                'period_end' => $periodEndDay->toDateString(),
                'period_days' => $usageDetails['period_days'],
                'bottles_at_start' => $usageDetails['bottles_at_start'],
                'bottles_at_end' => $usageDetails['bottles_at_end'],
                'inbound_count' => $usageDetails['inbound_count'],
                'outbound_count' => $usageDetails['outbound_count'],
                'transfer_count' => $usageDetails['transfer_count'],
                'average_bottles_per_day' => $usageDetails['average_bottle_count'],
                'rate_tier' => $rateTierLabel,
                'location_id' => $location?->id,
                'calculated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Calculate bottle-days for a customer during a billing period.
     *
     * This method implements accurate bottle-day tracking by:
     * 1. Getting the initial snapshot of bottles at period start
     * 2. Tracking all movements (inbound/outbound/transfers) during period
     * 3. Calculating days stored for each bottle based on entry/exit dates
     *
     * @param  Customer  $customer  The customer to calculate for
     * @param  Carbon  $periodStart  The start of the billing period
     * @param  Carbon  $periodEnd  The end of the billing period
     * @param  Location|null  $location  Optional specific location
     * @return array{
     *     bottle_days: int,
     *     period_days: int,
     *     bottles_at_start: int,
     *     bottles_at_end: int,
     *     inbound_count: int,
     *     outbound_count: int,
     *     transfer_count: int,
     *     average_bottle_count: int
     * }
     */
    public function getBottleDays(
        Customer $customer,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?Location $location = null
    ): array {
        $periodDays = (int) $periodStart->diffInDays($periodEnd) + 1;

        // Get bottles at period start (snapshot)
        $bottlesAtStart = $this->getInventorySnapshot($customer, $periodStart, $location);

        // Get bottles at period end (snapshot)
        $bottlesAtEnd = $this->getInventorySnapshot($customer, $periodEnd, $location);

        // Get all movements during the period
        $movements = $this->getMovementsDuringPeriod($customer, $periodStart, $periodEnd, $location);

        // Track bottle entries and exits
        $inboundMovements = $movements->filter(fn (array $m) => $m['type'] === 'inbound');
        $outboundMovements = $movements->filter(fn (array $m) => $m['type'] === 'outbound');
        $transferMovements = $movements->filter(fn (array $m) => $m['type'] === 'transfer');

        // Calculate bottle-days with accurate tracking
        $bottleDays = $this->calculateBottleDaysFromMovements(
            $bottlesAtStart,
            $movements,
            $periodStart,
            $periodEnd
        );

        // Calculate average bottles per day
        $averageBottleCount = $periodDays > 0
            ? (int) round($bottleDays / $periodDays)
            : 0;

        return [
            'bottle_days' => $bottleDays,
            'period_days' => $periodDays,
            'bottles_at_start' => $bottlesAtStart->count(),
            'bottles_at_end' => $bottlesAtEnd->count(),
            'inbound_count' => $inboundMovements->sum(fn (array $m) => $m['bottle_count']),
            'outbound_count' => $outboundMovements->sum(fn (array $m) => $m['bottle_count']),
            'transfer_count' => $transferMovements->sum(fn (array $m) => $m['bottle_count']),
            'average_bottle_count' => $averageBottleCount,
        ];
    }

    /**
     * Get a snapshot of bottles in storage for a customer at a specific point in time.
     *
     * This reconstructs the inventory state at the given date by:
     * 1. Starting with current inventory
     * 2. Reversing all movements after the snapshot date
     *
     * For simplicity in this implementation, we use the current state
     * adjusted by movements. A production system might use event sourcing
     * or daily snapshots for efficiency.
     *
     * @param  Customer  $customer  The customer
     * @param  Carbon  $snapshotDate  The date to snapshot
     * @param  Location|null  $location  Optional specific location
     * @return Collection<int, SerializedBottle>
     */
    public function getInventorySnapshot(
        Customer $customer,
        Carbon $snapshotDate,
        ?Location $location = null
    ): Collection {
        // For current or future dates, use current inventory
        if ($snapshotDate->gte(now())) {
            return $this->getCurrentStoredBottles($customer, $location);
        }

        // For past dates, we need to reconstruct the state
        // This is a simplified approach - a production system might use
        // materialized snapshots or event sourcing

        // Get bottles that were stored at any point and belonged to this customer
        $query = SerializedBottle::query()
            ->where('custody_holder', $customer->id)
            ->where('serialized_at', '<=', $snapshotDate);

        if ($location !== null) {
            $query->where('current_location_id', $location->id);
        }

        // Get all potentially relevant bottles
        $bottles = $query->get();

        // Filter to bottles that were actually in storage at the snapshot date
        return $bottles->filter(function (SerializedBottle $bottle) use ($snapshotDate): bool {
            return $this->wasBottleStoredAtDate($bottle, $snapshotDate);
        });
    }

    /**
     * Get all movements during a billing period for a customer.
     *
     * Returns a collection of movement records with type classification
     * (inbound, outbound, or transfer).
     *
     * @param  Customer  $customer  The customer
     * @param  Carbon  $periodStart  Period start date
     * @param  Carbon  $periodEnd  Period end date
     * @param  Location|null  $location  Optional specific location
     * @return Collection<int, array<string, mixed>>
     */
    public function getMovementsDuringPeriod(
        Customer $customer,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?Location $location = null
    ): Collection {
        // Get all movements in the period that involve bottles held by this customer
        $movementIds = MovementItem::query()
            ->whereHas('serializedBottle', function ($query) use ($customer) {
                $query->where('custody_holder', $customer->id);
            })
            ->whereHas('inventoryMovement', function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('executed_at', [$periodStart, $periodEnd]);
            })
            ->distinct()
            ->pluck('inventory_movement_id');

        $movements = InventoryMovement::query()
            ->whereIn('id', $movementIds)
            ->orderBy('executed_at')
            ->get();

        $result = collect();

        foreach ($movements as $movement) {
            // Get bottle IDs for this customer in this movement
            /** @var list<string> $bottleIds */
            $bottleIds = $movement->movementItems()
                ->whereHas('serializedBottle', function ($query) use ($customer, $location) {
                    $query->where('custody_holder', $customer->id);
                    if ($location !== null) {
                        $query->where('current_location_id', $location->id);
                    }
                })
                ->pluck('serialized_bottle_id')
                ->values()
                ->all();

            $bottleCount = count($bottleIds);

            // Skip movements with no bottles for this customer
            if ($bottleCount === 0) {
                continue;
            }

            // Classify movement type
            $type = $this->classifyMovementType($movement);

            $result->push([
                'type' => $type,
                'movement' => $movement,
                'executed_at' => $movement->executed_at,
                'bottle_count' => $bottleCount,
                'bottle_ids' => $bottleIds,
            ]);
        }

        return $result;
    }

    /**
     * Get the applicable storage rate for a customer.
     *
     * Rate can vary by:
     * - Volume tier (more bottles = lower rate)
     * - Customer tier (premium customers may have negotiated rates)
     * - Location (different storage facilities may have different rates)
     *
     * @param  Customer  $customer  The customer
     * @param  Location|null  $location  Optional specific location
     * @param  int  $bottleCount  The bottle count for tier determination
     * @return string The rate per bottle-day as a decimal string
     */
    public function getApplicableRate(
        Customer $customer,
        ?Location $location,
        int $bottleCount
    ): string {
        // Check for customer-specific rate (negotiated rate)
        $customerRate = $this->getCustomerSpecificRate($customer);
        if ($customerRate !== null) {
            return $customerRate;
        }

        // Check for location-specific rate
        if ($location !== null) {
            $locationRate = $this->getLocationRate($location);
            if ($locationRate !== null) {
                return $locationRate;
            }
        }

        // Fall back to volume-tier based rate
        return $this->getVolumeTierRate($bottleCount);
    }

    /**
     * Generate StorageBillingPeriod records for all customers.
     *
     * @param  Carbon  $periodStart  The start of the billing period
     * @param  Carbon  $periodEnd  The end of the billing period
     * @param  bool  $locationBreakdown  Whether to create separate periods per location
     * @return Collection<int, StorageBillingPeriod>
     */
    public function generatePeriods(
        Carbon $periodStart,
        Carbon $periodEnd,
        bool $locationBreakdown = false
    ): Collection {
        $customersWithStorage = $this->getCustomersWithStorage();
        $periods = collect();

        foreach ($customersWithStorage as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer === null) {
                continue;
            }

            try {
                // Check if period already exists
                $existingPeriod = StorageBillingPeriod::query()
                    ->where('customer_id', $customerId)
                    ->where('period_start', $periodStart)
                    ->where('period_end', $periodEnd->startOfDay())
                    ->first();

                if ($existingPeriod !== null) {
                    Log::channel('finance')->info('Storage billing period already exists', [
                        'customer_id' => $customerId,
                        'period_id' => $existingPeriod->id,
                    ]);

                    continue;
                }

                // Calculate usage
                $usageData = $this->calculateUsage($customer, $periodStart, $periodEnd);

                // Skip if no usage
                if ($usageData['bottle_days'] === 0) {
                    continue;
                }

                // Create the period
                $period = StorageBillingPeriod::create([
                    'customer_id' => $customerId,
                    'location_id' => null,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd->startOfDay(),
                    'bottle_count' => $usageData['bottle_count'],
                    'bottle_days' => $usageData['bottle_days'],
                    'unit_rate' => $usageData['unit_rate'],
                    'calculated_amount' => $usageData['calculated_amount'],
                    'currency' => $usageData['currency'],
                    'status' => StorageBillingStatus::Pending,
                    'invoice_id' => null,
                    'calculated_at' => now(),
                    'metadata' => $usageData['metadata'],
                ]);

                $periods->push($period);

                Log::channel('finance')->info('Created storage billing period', [
                    'period_id' => $period->id,
                    'customer_id' => $customerId,
                    'bottle_days' => $usageData['bottle_days'],
                    'calculated_amount' => $usageData['calculated_amount'],
                ]);
            } catch (\Throwable $e) {
                Log::channel('finance')->error('Failed to generate storage billing period', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $periods;
    }

    /**
     * Generate INV3 invoices for pending StorageBillingPeriods.
     *
     * @param  bool  $autoIssue  Whether to auto-issue generated invoices
     * @return Collection<int, Invoice>
     */
    public function generateInvoices(bool $autoIssue = true): Collection
    {
        $pendingPeriods = StorageBillingPeriod::query()
            ->where('status', StorageBillingStatus::Pending)
            ->whereNull('invoice_id')
            ->with('customer')
            ->get();

        $invoices = collect();

        foreach ($pendingPeriods as $period) {
            try {
                $invoice = $this->createInvoiceForPeriod($period, $autoIssue);

                if ($invoice !== null) {
                    $invoices->push($invoice);
                }
            } catch (\Throwable $e) {
                Log::channel('finance')->error('Failed to generate invoice for storage billing period', [
                    'period_id' => $period->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $invoices;
    }

    /**
     * Create an INV3 invoice for a storage billing period.
     */
    public function createInvoiceForPeriod(
        StorageBillingPeriod $period,
        bool $autoIssue = true
    ): ?Invoice {
        // Skip if already invoiced or no billable amount
        if ($period->hasInvoice() || bccomp($period->calculated_amount, '0', 2) <= 0) {
            return null;
        }

        return DB::transaction(function () use ($period, $autoIssue): Invoice {
            $customer = $period->customer;

            // Get the rate tier label for the description
            $rateTierLabel = $this->getRateTierLabel($period->bottle_count);

            // Build invoice line description with rate tier
            $periodLabel = $period->period_start->format('M j').' - '.$period->period_end->format('M j, Y');
            $rateFormatted = number_format((float) $period->unit_rate, 4);
            $description = "Wine Storage Services ({$periodLabel}) - {$period->bottle_count} bottles avg, {$period->bottle_days} bottle-days @ {$period->currency} {$rateFormatted}/bottle-day ({$rateTierLabel})";

            // Build invoice lines
            $lines = [
                [
                    'description' => $description,
                    'quantity' => (string) $period->bottle_days,
                    'unit_price' => $period->unit_rate,
                    'tax_rate' => config('finance.pricing.default_vat_rate', '22.00'),
                    'sellable_sku_id' => null,
                    'metadata' => [
                        'storage_billing_period_id' => $period->id,
                        'bottle_count' => $period->bottle_count,
                        'period_start' => $period->period_start->toDateString(),
                        'period_end' => $period->period_end->toDateString(),
                        'line_type' => 'storage_fee',
                        'rate_tier' => $rateTierLabel,
                        'unit_rate' => $period->unit_rate,
                    ],
                ],
            ];

            // Create draft invoice
            $dueDate = now()->addDays(config('finance.default_due_date_days.storage_fee', 30));
            $invoice = $this->invoiceService->createDraft(
                invoiceType: InvoiceType::StorageFee,
                customer: $customer,
                lines: $lines,
                sourceType: 'storage_billing_period',
                sourceId: $period->id,
                currency: $period->currency,
                dueDate: $dueDate,
                notes: "Storage fees for period {$period->period_start->format('Y-m-d')} to {$period->period_end->format('Y-m-d')}"
            );

            // Link invoice to period and update status
            $period->invoice_id = $invoice->id;
            $period->status = StorageBillingStatus::Invoiced;
            $period->save();

            // Auto-issue if requested
            if ($autoIssue) {
                $invoice = $this->invoiceService->issue($invoice);
            }

            return $invoice;
        });
    }

    // =========================================================================
    // Protected Helper Methods
    // =========================================================================

    /**
     * Get all customer IDs that have bottles in storage.
     *
     * @return Collection<int, string>
     */
    protected function getCustomersWithStorage(): Collection
    {
        return SerializedBottle::query()
            ->whereNotNull('custody_holder')
            ->whereIn('state', array_map(fn ($s) => $s->value, self::STORAGE_STATES))
            ->distinct()
            ->pluck('custody_holder');
    }

    /**
     * Get bottles currently in storage for a customer.
     *
     * @return Collection<int, SerializedBottle>
     */
    protected function getCurrentStoredBottles(Customer $customer, ?Location $location): Collection
    {
        $query = SerializedBottle::query()
            ->where('custody_holder', $customer->id)
            ->whereIn('state', array_map(fn ($s) => $s->value, self::STORAGE_STATES));

        if ($location !== null) {
            $query->where('current_location_id', $location->id);
        }

        return $query->get();
    }

    /**
     * Check if a bottle was in storage at a specific date.
     *
     * This is a simplified check - production would use movement history.
     */
    protected function wasBottleStoredAtDate(SerializedBottle $bottle, Carbon $date): bool
    {
        // If bottle doesn't exist yet, it wasn't stored
        if ($bottle->serialized_at->gt($date)) {
            return false;
        }

        // If bottle is in terminal state, check when it exited
        if ($bottle->isInTerminalState()) {
            // Find the last movement that changed state to terminal
            $lastTerminalMovement = $bottle->movements()
                ->whereIn('movement_type', array_map(fn ($t) => $t->value, self::OUTBOUND_MOVEMENT_TYPES))
                ->where('executed_at', '<=', $date)
                ->latest('executed_at')
                ->first();

            // If the terminal movement happened after the date, bottle was still stored
            if ($lastTerminalMovement === null) {
                return true; // No outbound movement found, was stored
            }

            return $lastTerminalMovement->executed_at->gt($date);
        }

        // Check if bottle was in storage state at the date
        // For simplicity, assume bottles not in terminal state were stored
        return true;
    }

    /**
     * Calculate bottle-days from movement data.
     *
     * For each bottle:
     * - If present at start: count days from start until exit or period end
     * - If entered during period: count days from entry until exit or period end
     *
     * @param  Collection<int, SerializedBottle>  $bottlesAtStart  Bottles present at period start
     * @param  Collection<int, array<string, mixed>>  $movements  Movements during period
     * @param  Carbon  $periodStart  Start of period
     * @param  Carbon  $periodEnd  End of period
     */
    protected function calculateBottleDaysFromMovements(
        Collection $bottlesAtStart,
        Collection $movements,
        Carbon $periodStart,
        Carbon $periodEnd
    ): int {
        $bottleDays = 0;
        $periodDays = (int) $periodStart->diffInDays($periodEnd) + 1;

        // Build a map of bottle entry/exit dates
        $bottleEvents = [];

        // Bottles present at start enter on day 1
        foreach ($bottlesAtStart as $bottle) {
            $bottleEvents[$bottle->id] = [
                'entry' => $periodStart->copy(),
                'exit' => null,
            ];
        }

        // Process movements chronologically
        foreach ($movements as $movementData) {
            $movementDate = $movementData['executed_at'];
            $type = $movementData['type'];
            $bottleIds = $movementData['bottle_ids'];

            foreach ($bottleIds as $bottleId) {
                if ($type === 'inbound') {
                    // Bottle entered storage
                    if (! isset($bottleEvents[$bottleId])) {
                        $bottleEvents[$bottleId] = [
                            'entry' => $movementDate->copy(),
                            'exit' => null,
                        ];
                    }
                } elseif ($type === 'outbound') {
                    // Bottle exited storage
                    if (isset($bottleEvents[$bottleId]) && $bottleEvents[$bottleId]['exit'] === null) {
                        $bottleEvents[$bottleId]['exit'] = $movementDate->copy();
                    }
                }
                // Transfers don't affect bottle-days for the same customer
            }
        }

        // Calculate days for each bottle
        foreach ($bottleEvents as $events) {
            $entryDate = $events['entry'];
            $exitDate = $events['exit'] ?? $periodEnd;

            // Clamp dates to period
            if ($entryDate->lt($periodStart)) {
                $entryDate = $periodStart->copy();
            }
            if ($exitDate->gt($periodEnd)) {
                $exitDate = $periodEnd->copy();
            }

            // Calculate days (inclusive)
            $days = (int) $entryDate->diffInDays($exitDate) + 1;
            if ($days > 0) {
                $bottleDays += $days;
            }
        }

        return $bottleDays;
    }

    /**
     * Classify a movement as inbound, outbound, or transfer.
     */
    protected function classifyMovementType(InventoryMovement $movement): string
    {
        $type = $movement->movement_type;

        if (in_array($type, self::INBOUND_MOVEMENT_TYPES, true)) {
            return 'inbound';
        }

        if (in_array($type, self::OUTBOUND_MOVEMENT_TYPES, true)) {
            return 'outbound';
        }

        // Internal transfers don't change custody
        return 'transfer';
    }

    /**
     * Get customer-specific storage rate (for negotiated rates).
     */
    protected function getCustomerSpecificRate(Customer $customer): ?string
    {
        // Check if customer has a negotiated storage rate in their metadata
        // This would come from Module K (CRM) customer configuration
        $metadata = $customer->metadata ?? [];

        if (isset($metadata['storage_rate_override'])) {
            return (string) $metadata['storage_rate_override'];
        }

        return null;
    }

    /**
     * Get location-specific storage rate.
     */
    protected function getLocationRate(Location $location): ?string
    {
        // Check if location has a specific storage rate
        // Premium locations might have higher rates
        // This is a placeholder - would integrate with location configuration
        return null;
    }

    /**
     * Get volume-tier based storage rate.
     *
     * @param  int  $bottleCount  Number of bottles for tier determination
     * @return string Rate per bottle-day
     */
    protected function getVolumeTierRate(int $bottleCount): string
    {
        $defaultRate = config('finance.storage.default_rate_per_bottle_day', '0.0050');
        $rateTiers = config('finance.storage.rate_tiers', [
            ['min_bottles' => 0, 'max_bottles' => 100, 'rate' => '0.0060'],
            ['min_bottles' => 101, 'max_bottles' => 500, 'rate' => '0.0050'],
            ['min_bottles' => 501, 'max_bottles' => 1000, 'rate' => '0.0045'],
            ['min_bottles' => 1001, 'max_bottles' => null, 'rate' => '0.0040'],
        ]);

        foreach ($rateTiers as $tier) {
            $minBottles = $tier['min_bottles'];
            $maxBottles = $tier['max_bottles'];

            if ($bottleCount >= $minBottles && ($maxBottles === null || $bottleCount <= $maxBottles)) {
                return $tier['rate'];
            }
        }

        return $defaultRate;
    }

    // =========================================================================
    // Preview and Reporting Methods
    // =========================================================================

    /**
     * Get a preview of usage for a customer without creating billing records.
     *
     * Useful for the Storage Billing Preview page and customer reports.
     *
     * @return array{
     *     bottle_count: int,
     *     bottle_days: int,
     *     unit_rate: string,
     *     calculated_amount: string,
     *     currency: string,
     *     breakdown: array<string, mixed>
     * }
     */
    public function previewUsage(
        Customer $customer,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?Location $location = null
    ): array {
        $usage = $this->calculateUsage($customer, $periodStart, $periodEnd, $location);

        // Add detailed breakdown for preview
        $usage['breakdown'] = [
            'period_label' => $periodStart->format('M j').' - '.$periodEnd->format('M j, Y'),
            'rate_tier' => $this->getRateTierLabel($usage['bottle_count']),
            'daily_average' => $usage['bottle_count'],
            'total_bottle_days' => $usage['bottle_days'],
            'unit_rate_formatted' => number_format((float) $usage['unit_rate'], 4),
            'amount_formatted' => number_format((float) $usage['calculated_amount'], 2),
        ];

        return $usage;
    }

    /**
     * Get a human-readable label for the applicable rate tier.
     */
    protected function getRateTierLabel(int $bottleCount): string
    {
        $rateTiers = config('finance.storage.rate_tiers', []);

        foreach ($rateTiers as $tier) {
            $minBottles = $tier['min_bottles'];
            $maxBottles = $tier['max_bottles'];

            if ($bottleCount >= $minBottles && ($maxBottles === null || $bottleCount <= $maxBottles)) {
                if ($maxBottles === null) {
                    return "{$minBottles}+ bottles";
                }

                return "{$minBottles}-{$maxBottles} bottles";
            }
        }

        return 'Standard rate';
    }
}
