<?php

namespace App\Services\Fulfillment;

use App\Enums\Fulfillment\PackagingPreference;
use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderAuditLog;
use App\Models\Fulfillment\ShippingOrderException;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\SerializedBottle;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing Late Binding logic in fulfillment.
 *
 * Late Binding is the process of binding abstract voucher entitlements to specific
 * physical bottles (serialized inventory) at the time of picking. This allows:
 * - Deferred commitment: Vouchers are not bound to specific bottles until shipment
 * - Flexibility: Any bottle from the matching allocation lineage can fulfill a voucher
 * - Integrity: Allocation lineage constraint is enforced as a HARD rule
 *
 * Key invariants:
 * - Allocation lineage must match between voucher and bottle (no cross-allocation substitution)
 * - Binding is reversible until shipment confirmation
 * - Early binding (from Module D personalization) takes precedence over late binding
 * - Bottle state transitions: stored → reserved_for_picking upon binding
 */
class LateBindingService
{
    /**
     * Cache TTL for eligible inventory queries (in seconds).
     */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Event types for audit logging.
     */
    public const EVENT_BINDING_REQUESTED = 'binding_requested';

    public const EVENT_BINDING_EXECUTED = 'binding_executed';

    public const EVENT_BINDING_VALIDATED = 'binding_validated';

    public const EVENT_BINDING_FAILED = 'binding_failed';

    public const EVENT_EARLY_BINDING_VALIDATED = 'early_binding_validated';

    public const EVENT_EARLY_BINDING_FAILED = 'early_binding_failed';

    public const EVENT_UNBIND_EXECUTED = 'unbind_executed';

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Request eligible inventory for a Shipping Order from Module B.
     *
     * Retrieves available bottles for each allocation lineage in the SO.
     * If packaging_preference = preserve_cases, also checks intact case availability.
     *
     * @param  ShippingOrder  $so  The shipping order to check inventory for
     * @return array{
     *     allocations: array<string, array{
     *         allocation_id: string,
     *         required_quantity: int,
     *         available_quantity: int,
     *         available_bottles: list<string>,
     *         intact_case_available: bool,
     *         status: string
     *     }>,
     *     all_available: bool,
     *     preserve_cases_satisfied: bool
     * }
     */
    public function requestEligibleInventory(ShippingOrder $so): array
    {
        $so->load(['lines.allocation', 'lines.voucher']);

        // Group lines by allocation to get required quantities
        $allocationRequirements = $so->lines
            ->groupBy('allocation_id')
            ->map(function (Collection $lines, string $allocationId) {
                return [
                    'allocation_id' => $allocationId,
                    'required_quantity' => $lines->count(),
                    'lines' => $lines,
                ];
            });

        $results = [];
        $allAvailable = true;
        $preserveCasesSatisfied = true;
        $warehouseId = $so->source_warehouse_id;
        $preserveCases = $so->packaging_preference === PackagingPreference::PreserveCases;

        foreach ($allocationRequirements as $allocationId => $requirement) {
            $cacheKey = $this->getInventoryCacheKey($allocationId, $warehouseId);

            // Try cache first, fallback to live query
            $inventoryData = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($allocationId, $warehouseId) {
                return $this->queryEligibleInventory($allocationId, $warehouseId);
            });

            $availableQuantity = count($inventoryData['available_bottles']);
            $intactCaseAvailable = $inventoryData['intact_case_available'];

            // Determine status
            $status = 'sufficient';
            if ($availableQuantity < $requirement['required_quantity']) {
                $status = 'insufficient';
                $allAvailable = false;
            } elseif ($preserveCases && ! $intactCaseAvailable) {
                $status = 'intact_case_unavailable';
                $preserveCasesSatisfied = false;
            }

            $results[$allocationId] = [
                'allocation_id' => $allocationId,
                'required_quantity' => $requirement['required_quantity'],
                'available_quantity' => $availableQuantity,
                'available_bottles' => $inventoryData['available_bottles'],
                'intact_case_available' => $intactCaseAvailable,
                'status' => $status,
            ];
        }

        // Log the inventory request
        $this->logEvent(
            $so,
            self::EVENT_BINDING_REQUESTED,
            'Eligible inventory requested for all allocations',
            null,
            [
                'allocation_count' => count($results),
                'all_available' => $allAvailable,
                'preserve_cases_satisfied' => $preserveCasesSatisfied,
            ]
        );

        return [
            'allocations' => $results,
            'all_available' => $allAvailable,
            'preserve_cases_satisfied' => $preserveCasesSatisfied,
        ];
    }

    /**
     * Bind a voucher (via ShippingOrderLine) to a specific serialized bottle.
     *
     * This is the core late binding operation that:
     * 1. Validates the bottle matches the allocation lineage
     * 2. Validates the bottle is available (stored state)
     * 3. Updates the line with bound_bottle_serial
     * 4. Transitions bottle state to reserved_for_picking
     *
     * @param  ShippingOrderLine  $line  The shipping order line to bind
     * @param  string  $serialNumber  The bottle serial number to bind
     * @return ShippingOrderLine The updated line
     *
     * @throws InvalidArgumentException If binding validation fails
     */
    public function bindVoucherToBottle(ShippingOrderLine $line, string $serialNumber): ShippingOrderLine
    {
        // Validate line status allows binding
        if (! $line->status->allowsBinding()) {
            throw new InvalidArgumentException(
                "Cannot bind voucher: line status '{$line->status->label()}' does not allow binding. "
                .'Binding is only allowed in Validated status.'
            );
        }

        // Check if already bound
        if ($line->isBound()) {
            throw new InvalidArgumentException(
                "Cannot bind voucher: line is already bound to serial '{$line->bound_bottle_serial}'. "
                .'Use unbindLine() first to change the binding.'
            );
        }

        // Find the bottle
        $bottle = SerializedBottle::where('serial_number', $serialNumber)->first();
        if ($bottle === null) {
            throw new InvalidArgumentException(
                "Cannot bind voucher: bottle with serial '{$serialNumber}' not found."
            );
        }

        // Validate allocation lineage match (HARD constraint)
        if ($bottle->allocation_id !== $line->allocation_id) {
            throw new InvalidArgumentException(
                'Allocation lineage mismatch. Cross-allocation substitution not allowed. '
                ."Line allocation: {$line->allocation_id}, Bottle allocation: {$bottle->allocation_id}."
            );
        }

        // Validate bottle state
        if ($bottle->state !== BottleState::Stored) {
            throw new InvalidArgumentException(
                "Cannot bind voucher: bottle is in '{$bottle->state->label()}' state. "
                .'Only bottles in Stored state can be bound.'
            );
        }

        // Validate bottle is not already bound to another line
        $existingBinding = ShippingOrderLine::where('bound_bottle_serial', $serialNumber)
            ->where('id', '!=', $line->id)
            ->whereHas('shippingOrder', function ($query) {
                $query->whereNotIn('status', [
                    ShippingOrderStatus::Cancelled->value,
                    ShippingOrderStatus::Completed->value,
                ]);
            })
            ->first();

        if ($existingBinding !== null) {
            throw new InvalidArgumentException(
                "Cannot bind voucher: bottle '{$serialNumber}' is already bound to another active shipping order line."
            );
        }

        return DB::transaction(function () use ($line, $bottle, $serialNumber): ShippingOrderLine {
            // Update bottle state to reserved_for_picking
            $bottle->state = BottleState::ReservedForPicking;
            $bottle->save();

            // Bind the line
            $line->bound_bottle_serial = $serialNumber;
            $line->binding_confirmed_at = now();
            $line->binding_confirmed_by = Auth::id();

            // If bottle is in a case, record the case_id
            if ($bottle->case_id !== null) {
                $line->bound_case_id = $bottle->case_id;
            }

            $line->save();

            // Log the binding
            $this->logEvent(
                $line->shippingOrder,
                self::EVENT_BINDING_EXECUTED,
                "Voucher bound to bottle serial {$serialNumber}",
                null,
                [
                    'line_id' => $line->id,
                    'voucher_id' => $line->voucher_id,
                    'bottle_serial' => $serialNumber,
                    'allocation_id' => $line->allocation_id,
                    'case_id' => $bottle->case_id,
                ]
            );

            return $line->fresh() ?? $line;
        });
    }

    /**
     * Validate the binding of a ShippingOrderLine.
     *
     * Checks that:
     * - Line has a bound bottle
     * - Bottle still exists and is valid
     * - Allocation lineage still matches
     * - Bottle ownership/custody allows fulfillment
     *
     * @param  ShippingOrderLine  $line  The line to validate
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateBinding(ShippingOrderLine $line): array
    {
        $errors = [];

        // Check line has a binding
        if (! $line->isBound()) {
            $errors[] = 'Line has no bound bottle serial';

            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        // Find the bottle
        $bottle = SerializedBottle::where('serial_number', $line->bound_bottle_serial)->first();
        if ($bottle === null) {
            $errors[] = "Bound bottle serial '{$line->bound_bottle_serial}' not found in inventory";

            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        // Validate allocation lineage match
        if ($bottle->allocation_id !== $line->allocation_id) {
            $errors[] = 'Allocation lineage mismatch. Cross-allocation substitution not allowed. '
                ."Line allocation: {$line->allocation_id}, Bottle allocation: {$bottle->allocation_id}";
        }

        // Validate bottle state (should be reserved_for_picking or stored)
        if (! in_array($bottle->state, [BottleState::Stored, BottleState::ReservedForPicking], true)) {
            $errors[] = "Bottle is in '{$bottle->state->label()}' state, expected Stored or Reserved for Picking";
        }

        // Validate bottle is not destroyed/missing
        if ($bottle->state->isTerminal()) {
            $errors[] = "Bottle is in terminal state '{$bottle->state->label()}' and cannot be fulfilled";
        }

        // Log validation result
        $valid = $errors === [];
        $line->load('shippingOrder');

        $this->logEvent(
            $line->shippingOrder,
            $valid ? self::EVENT_BINDING_VALIDATED : self::EVENT_BINDING_FAILED,
            $valid ? 'Binding validation passed' : 'Binding validation failed',
            null,
            [
                'line_id' => $line->id,
                'bound_bottle_serial' => $line->bound_bottle_serial,
                'valid' => $valid,
                'errors' => $errors,
            ]
        );

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Validate early binding from Module D (personalization).
     *
     * Early binding means the voucher was pre-bound to a specific bottle
     * during personalization. This binding must be honored without fallback.
     *
     * @param  ShippingOrderLine  $line  The line with early binding to validate
     * @return array{valid: bool, errors: list<string>}
     *
     * @throws InvalidArgumentException If validation fails and blocks shipment
     */
    public function validateEarlyBinding(ShippingOrderLine $line): array
    {
        $errors = [];

        // Check if line has early binding
        if (! $line->hasEarlyBinding()) {
            // No early binding - this is valid (will use late binding)
            return [
                'valid' => true,
                'errors' => [],
            ];
        }

        $earlySerial = $line->early_binding_serial;

        // Find the bottle
        $bottle = SerializedBottle::where('serial_number', $earlySerial)->first();
        if ($bottle === null) {
            $errors[] = "Early bound bottle serial '{$earlySerial}' not found in inventory";
            $this->createEarlyBindingException($line, 'Bottle not found');

            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        // Validate bottle state (must be stored)
        if ($bottle->state !== BottleState::Stored) {
            $errors[] = "Early bound bottle is in '{$bottle->state->label()}' state, expected Stored";
            $this->createEarlyBindingException($line, "Bottle in invalid state: {$bottle->state->label()}");
        }

        // Validate allocation lineage match
        if ($bottle->allocation_id !== $line->allocation_id) {
            $errors[] = 'Allocation lineage mismatch on early bound bottle. '
                ."Line allocation: {$line->allocation_id}, Bottle allocation: {$bottle->allocation_id}";
            $this->createEarlyBindingException($line, 'Allocation lineage mismatch');
        }

        // Log validation result
        $valid = $errors === [];
        $line->load('shippingOrder');

        $this->logEvent(
            $line->shippingOrder,
            $valid ? self::EVENT_EARLY_BINDING_VALIDATED : self::EVENT_EARLY_BINDING_FAILED,
            $valid ? 'Early binding validation passed' : 'Early binding validation failed - NO FALLBACK',
            null,
            [
                'line_id' => $line->id,
                'early_binding_serial' => $earlySerial,
                'valid' => $valid,
                'errors' => $errors,
            ]
        );

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Remove binding from a ShippingOrderLine.
     *
     * Unbinding is only allowed if the line has not been shipped.
     * This reverses the bottle state back to stored.
     *
     * @param  ShippingOrderLine  $line  The line to unbind
     * @return ShippingOrderLine The updated line
     *
     * @throws InvalidArgumentException If unbinding is not allowed
     */
    public function unbindLine(ShippingOrderLine $line): ShippingOrderLine
    {
        // Cannot unbind if already shipped
        if ($line->isShipped()) {
            throw new InvalidArgumentException(
                'Cannot unbind line: line has already been shipped. Shipped bindings are permanent.'
            );
        }

        // Cannot unbind if not bound
        if (! $line->isBound()) {
            throw new InvalidArgumentException(
                'Cannot unbind line: line has no bound bottle.'
            );
        }

        $boundSerial = $line->bound_bottle_serial;

        return DB::transaction(function () use ($line, $boundSerial): ShippingOrderLine {
            // Find the bottle and revert its state
            $bottle = SerializedBottle::where('serial_number', $boundSerial)->first();
            if ($bottle !== null && $bottle->state === BottleState::ReservedForPicking) {
                $bottle->state = BottleState::Stored;
                $bottle->save();
            }

            // Clear the binding
            $previousSerial = $line->bound_bottle_serial;
            $previousCaseId = $line->bound_case_id;

            $line->bound_bottle_serial = null;
            $line->bound_case_id = null;
            $line->binding_confirmed_at = null;
            $line->binding_confirmed_by = null;
            $line->save();

            // Log the unbind
            $line->load('shippingOrder');

            $this->logEvent(
                $line->shippingOrder,
                self::EVENT_UNBIND_EXECUTED,
                'Binding removed from line, bottle returned to stored state',
                [
                    'bound_bottle_serial' => $previousSerial,
                    'bound_case_id' => $previousCaseId,
                ],
                [
                    'bound_bottle_serial' => null,
                    'bound_case_id' => null,
                ]
            );

            return $line->fresh() ?? $line;
        });
    }

    /**
     * Check if all lines in a shipping order are bound.
     *
     * @param  ShippingOrder  $so  The shipping order to check
     * @return array{all_bound: bool, bound_count: int, unbound_count: int, lines: list<array{line_id: string, voucher_id: string, is_bound: bool, serial: ?string}>}
     */
    public function checkAllLinesBinding(ShippingOrder $so): array
    {
        $so->load('lines.voucher');

        $boundCount = 0;
        $unboundCount = 0;
        $lineDetails = [];

        foreach ($so->lines as $line) {
            $isBound = $line->isBound() || $line->hasEarlyBinding();

            if ($isBound) {
                $boundCount++;
            } else {
                $unboundCount++;
            }

            $lineDetails[] = [
                'line_id' => $line->id,
                'voucher_id' => $line->voucher_id,
                'is_bound' => $isBound,
                'serial' => $line->getEffectiveSerial(),
            ];
        }

        return [
            'all_bound' => $unboundCount === 0,
            'bound_count' => $boundCount,
            'unbound_count' => $unboundCount,
            'lines' => $lineDetails,
        ];
    }

    /**
     * Validate all bindings for a shipping order before shipment.
     *
     * @param  ShippingOrder  $so  The shipping order to validate
     * @return array{valid: bool, errors: list<array{line_id: string, errors: list<string>}>}
     */
    public function validateAllBindings(ShippingOrder $so): array
    {
        $so->load('lines');

        $allErrors = [];

        foreach ($so->lines as $line) {
            // If line has early binding, validate that
            if ($line->hasEarlyBinding()) {
                $result = $this->validateEarlyBinding($line);
            } else {
                $result = $this->validateBinding($line);
            }

            if (! $result['valid']) {
                $allErrors[] = [
                    'line_id' => $line->id,
                    'errors' => $result['errors'],
                ];
            }
        }

        return [
            'valid' => $allErrors === [],
            'errors' => $allErrors,
        ];
    }

    /**
     * Query eligible inventory for a specific allocation and optional warehouse.
     *
     * @param  string  $allocationId  The allocation ID to query
     * @param  string|null  $warehouseId  Optional warehouse ID to filter by
     * @return array{available_bottles: list<string>, intact_case_available: bool}
     */
    protected function queryEligibleInventory(string $allocationId, ?string $warehouseId): array
    {
        $query = SerializedBottle::query()
            ->where('allocation_id', $allocationId)
            ->where('state', BottleState::Stored);

        if ($warehouseId !== null) {
            $query->where('current_location_id', $warehouseId);
        }

        $bottles = $query->pluck('serial_number')->toArray();

        // Check for intact case availability
        $intactCaseQuery = InventoryCase::query()
            ->where('allocation_id', $allocationId)
            ->where('integrity_status', CaseIntegrityStatus::Intact);

        if ($warehouseId !== null) {
            $intactCaseQuery->where('current_location_id', $warehouseId);
        }

        $intactCaseAvailable = $intactCaseQuery->exists();

        return [
            'available_bottles' => $bottles,
            'intact_case_available' => $intactCaseAvailable,
        ];
    }

    /**
     * Get the cache key for inventory queries.
     */
    protected function getInventoryCacheKey(string $allocationId, ?string $warehouseId): string
    {
        $warehousePart = $warehouseId ?? 'all';

        return "late_binding_inventory:{$allocationId}:{$warehousePart}";
    }

    /**
     * Clear the inventory cache for a specific allocation.
     */
    public function clearInventoryCache(string $allocationId, ?string $warehouseId = null): void
    {
        if ($warehouseId !== null) {
            Cache::forget($this->getInventoryCacheKey($allocationId, $warehouseId));
        } else {
            // Clear all warehouse variations for this allocation
            Cache::forget($this->getInventoryCacheKey($allocationId, null));
        }
    }

    /**
     * Create an early binding exception.
     */
    protected function createEarlyBindingException(ShippingOrderLine $line, string $reason): void
    {
        $line->load('shippingOrder');

        ShippingOrderException::create([
            'shipping_order_id' => $line->shippingOrder->id,
            'shipping_order_line_id' => $line->id,
            'exception_type' => ShippingOrderExceptionType::EarlyBindingFailed,
            'description' => "Early binding validation failed for voucher {$line->voucher_id}: {$reason}. "
                .'NO fallback to late binding is allowed.',
            'status' => ShippingOrderExceptionStatus::Active,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Log an event to the shipping order audit log.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @param  string  $eventType  The event type
     * @param  string  $description  The event description
     * @param  array<string, mixed>|null  $oldValues  The old values (if applicable)
     * @param  array<string, mixed>|null  $newValues  The new values (if applicable)
     */
    protected function logEvent(
        ShippingOrder $shippingOrder,
        string $eventType,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        ShippingOrderAuditLog::create([
            'shipping_order_id' => $shippingOrder->id,
            'event_type' => $eventType,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);
    }
}
