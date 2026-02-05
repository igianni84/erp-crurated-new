<?php

namespace App\Services\Fulfillment;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderAuditLog;
use App\Models\Fulfillment\ShippingOrderException;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Services\Allocation\VoucherService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing Shipping Order lifecycle and operations.
 *
 * Centralizes all Shipping Order business logic including creation, state transitions,
 * voucher validation, and lock management.
 *
 * Key invariants:
 * - Vouchers become locked when SO transitions to planned
 * - Vouchers are unlocked when SO is cancelled
 * - A voucher can only be in one active SO at a time
 * - Allocation lineage is enforced (no cross-allocation substitution)
 */
class ShippingOrderService
{
    /**
     * Event types for audit logging.
     */
    public const EVENT_CREATED = 'created';

    public const EVENT_STATUS_CHANGE = 'status_change';

    public const EVENT_VOUCHER_ADDED = 'voucher_added';

    public const EVENT_VOUCHER_REMOVED = 'voucher_removed';

    public const EVENT_VOUCHERS_LOCKED = 'vouchers_locked';

    public const EVENT_VOUCHERS_UNLOCKED = 'vouchers_unlocked';

    public const EVENT_CANCELLED = 'cancelled';

    public const EVENT_VALIDATION_PASSED = 'validation_passed';

    public const EVENT_VALIDATION_FAILED = 'validation_failed';

    public const EVENT_VOUCHER_INELIGIBLE = 'voucher_ineligible';

    public function __construct(
        protected VoucherService $voucherService,
        protected LateBindingService $lateBindingService
    ) {}

    /**
     * Create a new Shipping Order in draft status.
     *
     * @param  Customer  $customer  The customer who will receive the shipment
     * @param  array<Voucher>|Collection<int, Voucher>  $vouchers  The vouchers to include in the SO
     * @param  string|null  $destinationAddressId  The destination address UUID (nullable for now)
     * @param  string|null  $shippingMethod  The shipping method (nullable)
     * @return ShippingOrder The created shipping order
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function create(
        Customer $customer,
        array|Collection $vouchers,
        ?string $destinationAddressId = null,
        ?string $shippingMethod = null
    ): ShippingOrder {
        $vouchers = $vouchers instanceof Collection ? $vouchers : collect($vouchers);

        // Validate customer is active
        $this->validateCustomer($customer);

        // Validate at least one voucher
        if ($vouchers->isEmpty()) {
            throw new \InvalidArgumentException(
                'Cannot create Shipping Order: at least one voucher is required.'
            );
        }

        // Validate all vouchers belong to the customer and are eligible
        $this->validateVouchersForCreation($customer, $vouchers);

        return DB::transaction(function () use ($customer, $vouchers, $destinationAddressId, $shippingMethod): ShippingOrder {
            // Create the shipping order in draft status
            $shippingOrder = ShippingOrder::create([
                'customer_id' => $customer->id,
                'destination_address_id' => $destinationAddressId,
                'shipping_method' => $shippingMethod,
                'status' => ShippingOrderStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            // Create shipping order lines for each voucher
            foreach ($vouchers as $voucher) {
                ShippingOrderLine::create([
                    'shipping_order_id' => $shippingOrder->id,
                    'voucher_id' => $voucher->id,
                    'allocation_id' => $voucher->allocation_id, // Copied from voucher - IMMUTABLE
                    'status' => ShippingOrderLineStatus::Pending,
                    'early_binding_serial' => $voucher->getAttribute('early_binding_serial'), // From Module D if exists
                    'created_by' => Auth::id(),
                ]);
            }

            // Log the creation
            $this->logEvent(
                $shippingOrder,
                self::EVENT_CREATED,
                'Shipping Order created',
                null,
                [
                    'customer_id' => $customer->id,
                    'voucher_count' => $vouchers->count(),
                    'voucher_ids' => $vouchers->pluck('id')->toArray(),
                ]
            );

            return $shippingOrder->fresh(['lines', 'customer']) ?? $shippingOrder;
        });
    }

    /**
     * Validate all vouchers in a Shipping Order for eligibility.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order to validate
     * @return array{valid: bool, errors: list<array{voucher_id: string, reason: string}>}
     */
    public function validateVouchers(ShippingOrder $shippingOrder): array
    {
        $errors = [];
        $shippingOrder->load('lines.voucher');

        foreach ($shippingOrder->lines as $line) {
            $voucher = $line->voucher;
            if ($voucher === null) {
                $errors[] = [
                    'voucher_id' => $line->voucher_id,
                    'reason' => 'Voucher not found',
                ];

                continue;
            }

            $eligibilityResult = $this->checkVoucherEligibility($voucher, $shippingOrder);

            if (! $eligibilityResult['eligible']) {
                $errors[] = [
                    'voucher_id' => $voucher->id,
                    'reason' => $eligibilityResult['reason'],
                ];
            }
        }

        $valid = $errors === [];

        // Log validation result
        $this->logEvent(
            $shippingOrder,
            $valid ? self::EVENT_VALIDATION_PASSED : self::EVENT_VALIDATION_FAILED,
            $valid ? 'All vouchers passed eligibility validation' : 'Voucher eligibility validation failed',
            null,
            [
                'valid' => $valid,
                'error_count' => count($errors),
                'errors' => $errors,
            ]
        );

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Transition a Shipping Order to a new status.
     *
     * Handles all status transitions with appropriate validations and side effects:
     * - draft → planned: locks vouchers
     * - any → cancelled: unlocks vouchers
     * - any → on_hold: stores previous status
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order to transition
     * @param  ShippingOrderStatus  $targetStatus  The target status
     * @return ShippingOrder The updated shipping order
     *
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function transitionTo(ShippingOrder $shippingOrder, ShippingOrderStatus $targetStatus): ShippingOrder
    {
        $currentStatus = $shippingOrder->status;

        // Check if transition is allowed
        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw new \InvalidArgumentException(
                "Invalid status transition from {$currentStatus->label()} to {$targetStatus->label()}. "
                .'Allowed transitions: '.implode(', ', array_map(fn ($s) => $s->label(), $currentStatus->allowedTransitions())).'.'
            );
        }

        return DB::transaction(function () use ($shippingOrder, $currentStatus, $targetStatus): ShippingOrder {
            // Pre-transition validations and side effects
            $this->handlePreTransition($shippingOrder, $currentStatus, $targetStatus);

            // Log the transition
            $this->logEvent(
                $shippingOrder,
                self::EVENT_STATUS_CHANGE,
                "Status changed from {$currentStatus->label()} to {$targetStatus->label()}",
                ['status' => $currentStatus->value],
                ['status' => $targetStatus->value]
            );

            // Perform the transition (model boot() handles validation)
            $shippingOrder->status = $targetStatus;
            $shippingOrder->save();

            // Post-transition side effects
            $this->handlePostTransition($shippingOrder, $currentStatus, $targetStatus);

            return $shippingOrder->fresh() ?? $shippingOrder;
        });
    }

    /**
     * Cancel a Shipping Order.
     *
     * Cancellation unlocks all vouchers and records the cancellation reason.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order to cancel
     * @param  string  $reason  The reason for cancellation
     * @return ShippingOrder The cancelled shipping order
     *
     * @throws \InvalidArgumentException If cancellation is not allowed
     */
    public function cancel(ShippingOrder $shippingOrder, string $reason): ShippingOrder
    {
        if (! $shippingOrder->canBeCancelled()) {
            throw new \InvalidArgumentException(
                "Cannot cancel Shipping Order: status '{$shippingOrder->status->label()}' does not allow cancellation."
            );
        }

        return DB::transaction(function () use ($shippingOrder, $reason): ShippingOrder {
            $previousStatus = $shippingOrder->status;

            // Log the cancellation with reason
            $this->logEvent(
                $shippingOrder,
                self::EVENT_CANCELLED,
                "Shipping Order cancelled: {$reason}",
                ['status' => $previousStatus->value],
                [
                    'status' => ShippingOrderStatus::Cancelled->value,
                    'cancellation_reason' => $reason,
                ]
            );

            // If SO was in picking, unbind all bound lines to restore bottle state to Stored
            if ($previousStatus === ShippingOrderStatus::Picking) {
                $this->unbindAllLines($shippingOrder);
            }

            // Unlock vouchers if they were locked
            if ($previousStatus->requiresVoucherLock()) {
                $this->unlockVouchers($shippingOrder);
            }

            // Cancel all lines
            $shippingOrder->lines()->update([
                'status' => ShippingOrderLineStatus::Cancelled,
            ]);

            // Perform the cancellation
            $shippingOrder->status = ShippingOrderStatus::Cancelled;
            $shippingOrder->save();

            return $shippingOrder->fresh() ?? $shippingOrder;
        });
    }

    /**
     * Lock vouchers for a Shipping Order.
     *
     * Called when SO transitions to planned. Vouchers are locked using
     * VoucherService.lockForFulfillment() to prevent trading/transfer.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     *
     * @throws \InvalidArgumentException If any voucher cannot be locked
     */
    public function lockVouchersForSO(ShippingOrder $shippingOrder): void
    {
        $shippingOrder->load('lines.voucher');

        $lockedVouchers = [];

        try {
            foreach ($shippingOrder->lines as $line) {
                $voucher = $line->voucher;
                if ($voucher === null) {
                    continue;
                }

                // Skip if already locked
                if ($voucher->isLocked()) {
                    $lockedVouchers[] = $voucher->id;

                    continue;
                }

                // Lock the voucher using VoucherService
                $this->voucherService->lockForFulfillment($voucher);
                $lockedVouchers[] = $voucher->id;
            }

            // Log the lock event
            $this->logEvent(
                $shippingOrder,
                self::EVENT_VOUCHERS_LOCKED,
                'Vouchers locked for fulfillment',
                null,
                [
                    'locked_voucher_ids' => $lockedVouchers,
                    'count' => count($lockedVouchers),
                ]
            );
        } catch (\Throwable $e) {
            // If locking fails, unlock any vouchers we already locked
            foreach ($lockedVouchers as $voucherId) {
                try {
                    $voucher = Voucher::find($voucherId);
                    if ($voucher !== null && $voucher->isLocked()) {
                        $this->voucherService->unlock($voucher);
                    }
                } catch (\Throwable) {
                    // Best effort rollback
                }
            }

            throw new \InvalidArgumentException(
                "Failed to lock vouchers for Shipping Order: {$e->getMessage()}"
            );
        }
    }

    /**
     * Unlock vouchers for a Shipping Order.
     *
     * Called when SO is cancelled. Vouchers are unlocked using
     * VoucherService.unlock() to allow trading/transfer again.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     */
    public function unlockVouchers(ShippingOrder $shippingOrder): void
    {
        $shippingOrder->load('lines.voucher');

        $unlockedVouchers = [];

        foreach ($shippingOrder->lines as $line) {
            $voucher = $line->voucher;
            if ($voucher === null) {
                continue;
            }

            // Only unlock if currently locked
            if (! $voucher->isLocked()) {
                continue;
            }

            try {
                $this->voucherService->unlock($voucher);
                $unlockedVouchers[] = $voucher->id;
            } catch (\Throwable $e) {
                // Log but continue - best effort unlock
                $this->logEvent(
                    $shippingOrder,
                    'unlock_failed',
                    "Failed to unlock voucher {$voucher->id}: {$e->getMessage()}",
                    null,
                    ['voucher_id' => $voucher->id, 'error' => $e->getMessage()]
                );
            }
        }

        if ($unlockedVouchers !== []) {
            $this->logEvent(
                $shippingOrder,
                self::EVENT_VOUCHERS_UNLOCKED,
                'Vouchers unlocked',
                null,
                [
                    'unlocked_voucher_ids' => $unlockedVouchers,
                    'count' => count($unlockedVouchers),
                ]
            );
        }
    }

    /**
     * Unbind all bound lines for a Shipping Order.
     *
     * Called when SO is cancelled during picking. Restores bottle state to Stored.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     */
    protected function unbindAllLines(ShippingOrder $shippingOrder): void
    {
        $shippingOrder->load('lines');

        $unboundSerials = [];

        foreach ($shippingOrder->lines as $line) {
            if (! $line->isBound()) {
                continue;
            }

            $serial = $line->bound_bottle_serial;

            try {
                $this->lateBindingService->unbindLine($line);
                $unboundSerials[] = $serial;
            } catch (\Throwable $e) {
                // Log but continue - best effort unbind
                $this->logEvent(
                    $shippingOrder,
                    'unbind_failed',
                    "Failed to unbind line {$line->id} (serial {$serial}): {$e->getMessage()}",
                    null,
                    ['line_id' => $line->id, 'serial' => $serial, 'error' => $e->getMessage()]
                );
            }
        }

        if ($unboundSerials !== []) {
            $this->logEvent(
                $shippingOrder,
                'lines_unbound',
                'Lines unbound due to SO cancellation during picking',
                null,
                [
                    'unbound_serials' => $unboundSerials,
                    'count' => count($unboundSerials),
                ]
            );
        }
    }

    /**
     * Get vouchers that are locked for a specific Shipping Order.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @return Collection<int, Voucher> The locked vouchers
     */
    public function getLockedVouchers(ShippingOrder $shippingOrder): Collection
    {
        $shippingOrder->load('lines.voucher');

        return $shippingOrder->lines
            ->map(fn (ShippingOrderLine $line) => $line->voucher)
            ->filter(fn (?Voucher $voucher) => $voucher !== null && $voucher->isLocked())
            ->values();
    }

    /**
     * Check if a voucher is locked for a specific Shipping Order.
     *
     * @param  Voucher  $voucher  The voucher to check
     * @param  ShippingOrder  $shippingOrder  The shipping order
     */
    public function isVoucherLockedForSO(Voucher $voucher, ShippingOrder $shippingOrder): bool
    {
        if (! $voucher->isLocked()) {
            return false;
        }

        return $shippingOrder->lines()
            ->where('voucher_id', $voucher->id)
            ->exists();
    }

    /**
     * Check if a Shipping Order is blocked due to ineligible vouchers.
     *
     * This method re-validates all vouchers and returns whether the SO is blocked.
     * Called by UI to show blocking banners and prevent state transitions.
     *
     * Per US-C030: If a voucher becomes ineligible during the SO lifecycle,
     * the SO is blocked and cannot proceed until the issue is resolved.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order to check
     * @return array{blocked: bool, ineligible_count: int, errors: list<array{voucher_id: string, reason: string}>}
     */
    public function checkIfBlocked(ShippingOrder $shippingOrder): array
    {
        $validation = $this->validateVouchers($shippingOrder);

        return [
            'blocked' => ! $validation['valid'],
            'ineligible_count' => count($validation['errors']),
            'errors' => $validation['errors'],
        ];
    }

    /**
     * Get a summary of voucher eligibility status for a Shipping Order.
     *
     * Returns detailed eligibility information for each voucher in the SO.
     * Useful for displaying eligibility status in the UI.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @return array{total: int, eligible: int, ineligible: int, vouchers: list<array{voucher_id: string, eligible: bool, reason: string|null}>}
     */
    public function getVoucherEligibilitySummary(ShippingOrder $shippingOrder): array
    {
        $shippingOrder->load('lines.voucher');

        $vouchers = [];
        $eligible = 0;
        $ineligible = 0;

        foreach ($shippingOrder->lines as $line) {
            $voucher = $line->voucher;
            if ($voucher === null) {
                $vouchers[] = [
                    'voucher_id' => $line->voucher_id,
                    'eligible' => false,
                    'reason' => 'Voucher not found',
                ];
                $ineligible++;

                continue;
            }

            $eligibilityResult = $this->checkVoucherEligibility($voucher, $shippingOrder);

            $vouchers[] = [
                'voucher_id' => $voucher->id,
                'eligible' => $eligibilityResult['eligible'],
                'reason' => $eligibilityResult['reason'],
            ];

            if ($eligibilityResult['eligible']) {
                $eligible++;
            } else {
                $ineligible++;
            }
        }

        return [
            'total' => count($vouchers),
            'eligible' => $eligible,
            'ineligible' => $ineligible,
            'vouchers' => $vouchers,
        ];
    }

    /**
     * Check if a Shipping Order can proceed based on voucher eligibility.
     *
     * Returns false if any voucher is ineligible. This is a quick check
     * for determining if an SO can transition to the next state.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     */
    public function canProceed(ShippingOrder $shippingOrder): bool
    {
        $validation = $this->validateVouchers($shippingOrder);

        return $validation['valid'];
    }

    /**
     * Add a voucher to an existing Shipping Order.
     *
     * Only allowed when SO is in draft status.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @param  Voucher  $voucher  The voucher to add
     *
     * @throws \InvalidArgumentException If voucher cannot be added
     */
    public function addVoucher(ShippingOrder $shippingOrder, Voucher $voucher): ShippingOrderLine
    {
        if (! $shippingOrder->canBeEdited()) {
            throw new \InvalidArgumentException(
                "Cannot add voucher: Shipping Order status '{$shippingOrder->status->label()}' does not allow editing."
            );
        }

        // Check if voucher already in this SO
        if ($shippingOrder->lines()->where('voucher_id', $voucher->id)->exists()) {
            throw new \InvalidArgumentException(
                'Voucher is already included in this Shipping Order.'
            );
        }

        // Check voucher eligibility
        $eligibility = $this->checkVoucherEligibility($voucher, $shippingOrder);
        if (! $eligibility['eligible']) {
            throw new \InvalidArgumentException(
                "Cannot add voucher: {$eligibility['reason']}"
            );
        }

        return DB::transaction(function () use ($shippingOrder, $voucher): ShippingOrderLine {
            $line = ShippingOrderLine::create([
                'shipping_order_id' => $shippingOrder->id,
                'voucher_id' => $voucher->id,
                'allocation_id' => $voucher->allocation_id,
                'status' => ShippingOrderLineStatus::Pending,
                'created_by' => Auth::id(),
            ]);

            $this->logEvent(
                $shippingOrder,
                self::EVENT_VOUCHER_ADDED,
                'Voucher added to Shipping Order',
                null,
                [
                    'voucher_id' => $voucher->id,
                    'allocation_id' => $voucher->allocation_id,
                ]
            );

            return $line;
        });
    }

    /**
     * Remove a voucher from a Shipping Order.
     *
     * Only allowed when SO is in draft status.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @param  Voucher  $voucher  The voucher to remove
     *
     * @throws \InvalidArgumentException If voucher cannot be removed
     */
    public function removeVoucher(ShippingOrder $shippingOrder, Voucher $voucher): void
    {
        if (! $shippingOrder->canBeEdited()) {
            throw new \InvalidArgumentException(
                "Cannot remove voucher: Shipping Order status '{$shippingOrder->status->label()}' does not allow editing."
            );
        }

        $line = $shippingOrder->lines()->where('voucher_id', $voucher->id)->first();

        if ($line === null) {
            throw new \InvalidArgumentException(
                'Voucher is not in this Shipping Order.'
            );
        }

        DB::transaction(function () use ($shippingOrder, $voucher, $line): void {
            $this->logEvent(
                $shippingOrder,
                self::EVENT_VOUCHER_REMOVED,
                'Voucher removed from Shipping Order',
                [
                    'voucher_id' => $voucher->id,
                    'allocation_id' => $voucher->allocation_id,
                ],
                null
            );

            $line->delete();
        });
    }

    /**
     * Validate customer for Shipping Order creation.
     *
     * @param  Customer  $customer  The customer to validate
     *
     * @throws \InvalidArgumentException If customer is not eligible
     */
    protected function validateCustomer(Customer $customer): void
    {
        if (! $customer->isActive()) {
            throw new \InvalidArgumentException(
                "Cannot create Shipping Order: customer is not active (status: {$customer->status->value})."
            );
        }
    }

    /**
     * Validate vouchers for Shipping Order creation.
     *
     * @param  Customer  $customer  The customer
     * @param  Collection<int, Voucher>  $vouchers  The vouchers to validate
     *
     * @throws \InvalidArgumentException If any voucher is not eligible
     */
    protected function validateVouchersForCreation(Customer $customer, Collection $vouchers): void
    {
        foreach ($vouchers as $voucher) {
            // Check voucher belongs to customer
            if ($voucher->customer_id !== $customer->id) {
                throw new \InvalidArgumentException(
                    "Voucher {$voucher->id} does not belong to customer {$customer->id}."
                );
            }

            // Check voucher is eligible (uses full eligibility check)
            $eligibility = $this->checkVoucherEligibility($voucher);
            if (! $eligibility['eligible']) {
                throw new \InvalidArgumentException(
                    "Voucher {$voucher->id} is not eligible: {$eligibility['reason']}"
                );
            }
        }
    }

    /**
     * Check if a voucher is eligible for fulfillment.
     *
     * Eligibility criteria (per US-C030):
     * - lifecycle_state = issued (or locked for THIS SO during planning/picking)
     * - suspended = false
     * - customer_id match SO customer
     * - not in pending transfer
     * - allocation active (not closed)
     *
     * @param  Voucher  $voucher  The voucher to check
     * @param  ShippingOrder|null  $shippingOrder  Optional SO context for additional checks
     * @return array{eligible: bool, reason: string|null}
     */
    public function checkVoucherEligibility(Voucher $voucher, ?ShippingOrder $shippingOrder = null): array
    {
        // Check lifecycle state - must be issued (not locked by another process)
        if ($voucher->lifecycle_state !== VoucherLifecycleState::Issued) {
            // If it's locked, check if it's locked for this SO (that's OK during planning)
            if ($voucher->lifecycle_state === VoucherLifecycleState::Locked) {
                if ($shippingOrder !== null && $this->isVoucherLockedForSO($voucher, $shippingOrder)) {
                    // Voucher is locked for THIS SO - that's fine
                } else {
                    return [
                        'eligible' => false,
                        'reason' => "Voucher is locked (state: {$voucher->lifecycle_state->label()}). "
                            .'Only issued vouchers can be added to a Shipping Order.',
                    ];
                }
            } else {
                return [
                    'eligible' => false,
                    'reason' => "Voucher is in state '{$voucher->lifecycle_state->label()}'. "
                        .'Only issued vouchers can be added to a Shipping Order.',
                ];
            }
        }

        // Check not suspended
        if ($voucher->suspended) {
            return [
                'eligible' => false,
                'reason' => 'Voucher is suspended. '.$voucher->getSuspensionReason(),
            ];
        }

        // Check not in pending transfer
        if ($voucher->hasPendingTransfer()) {
            return [
                'eligible' => false,
                'reason' => 'Voucher has a pending transfer. Complete or cancel the transfer first.',
            ];
        }

        // Check not quarantined
        if ($voucher->isQuarantined()) {
            return [
                'eligible' => false,
                'reason' => 'Voucher requires attention: '.($voucher->getAttentionReason() ?? 'Unknown issue'),
            ];
        }

        // Check allocation is valid (has lineage)
        if ($voucher->hasLineageIssues()) {
            return [
                'eligible' => false,
                'reason' => 'Voucher has allocation lineage issues.',
            ];
        }

        // Check allocation is active (not closed) - US-C030 requirement
        $allocation = $voucher->allocation;
        if ($allocation !== null && $allocation->isClosed()) {
            return [
                'eligible' => false,
                'reason' => 'Voucher allocation is closed. Closed allocations cannot be used for fulfillment.',
            ];
        }

        // Check not already in another active SO
        $existingLine = ShippingOrderLine::query()
            ->where('voucher_id', $voucher->id)
            ->whereHas('shippingOrder', function ($query) use ($shippingOrder) {
                $query->whereIn('status', [
                    ShippingOrderStatus::Draft->value,
                    ShippingOrderStatus::Planned->value,
                    ShippingOrderStatus::Picking->value,
                    ShippingOrderStatus::OnHold->value,
                ]);

                // Exclude current SO if provided
                if ($shippingOrder !== null) {
                    $query->where('id', '!=', $shippingOrder->id);
                }
            })
            ->first();

        if ($existingLine !== null) {
            $existingSO = $existingLine->shippingOrder;

            return [
                'eligible' => false,
                'reason' => "Voucher is already assigned to Shipping Order {$existingSO?->id}",
            ];
        }

        // Check customer match if SO provided
        if ($shippingOrder !== null && $voucher->customer_id !== $shippingOrder->customer_id) {
            return [
                'eligible' => false,
                'reason' => 'Voucher holder does not match Shipping Order customer.',
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
        ];
    }

    /**
     * Handle pre-transition validations and side effects.
     *
     * Voucher eligibility is validated at three checkpoints (US-C030):
     * 1. SO creation (in validateVouchersForCreation)
     * 2. Planning (draft → planned)
     * 3. Pre-picking (planned → picking)
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @param  ShippingOrderStatus  $from  The current status
     * @param  ShippingOrderStatus  $to  The target status
     *
     * @throws \InvalidArgumentException If pre-conditions are not met
     */
    protected function handlePreTransition(
        ShippingOrder $shippingOrder,
        ShippingOrderStatus $from,
        ShippingOrderStatus $to
    ): void {
        // Validate vouchers before planning (checkpoint 2)
        if ($to === ShippingOrderStatus::Planned) {
            $validation = $this->validateVouchers($shippingOrder);
            if (! $validation['valid']) {
                $errorMessages = array_map(
                    fn ($e) => "{$e['voucher_id']}: {$e['reason']}",
                    $validation['errors']
                );

                // Create VoucherIneligible exceptions for each ineligible voucher
                foreach ($validation['errors'] as $error) {
                    $line = $shippingOrder->lines->first(fn ($l) => $l->voucher_id === $error['voucher_id']);

                    ShippingOrderException::create([
                        'shipping_order_id' => $shippingOrder->id,
                        'shipping_order_line_id' => $line?->id,
                        'exception_type' => ShippingOrderExceptionType::VoucherIneligible,
                        'description' => "Voucher {$error['voucher_id']} is not eligible for planning: {$error['reason']}",
                        'resolution_path' => "Remove ineligible voucher from Shipping Order\nCancel Shipping Order",
                        'status' => ShippingOrderExceptionStatus::Active,
                        'created_by' => Auth::id(),
                    ]);
                }

                throw new \InvalidArgumentException(
                    'Cannot plan Shipping Order: voucher validation failed. '
                    .implode('; ', $errorMessages)
                );
            }
        }

        // Validate vouchers before picking (checkpoint 3 - pre-picking)
        if ($to === ShippingOrderStatus::Picking) {
            $validation = $this->validateVouchers($shippingOrder);
            if (! $validation['valid']) {
                $errorMessages = array_map(
                    fn ($e) => "{$e['voucher_id']}: {$e['reason']}",
                    $validation['errors']
                );

                // Create VoucherIneligible exceptions and log for each ineligible voucher
                foreach ($validation['errors'] as $error) {
                    // Find the line for this voucher to link exception to it
                    $line = $shippingOrder->lines->first(fn ($l) => $l->voucher_id === $error['voucher_id']);

                    ShippingOrderException::create([
                        'shipping_order_id' => $shippingOrder->id,
                        'shipping_order_line_id' => $line?->id,
                        'exception_type' => ShippingOrderExceptionType::VoucherIneligible,
                        'description' => "Voucher {$error['voucher_id']} is no longer eligible: {$error['reason']}",
                        'resolution_path' => "Remove ineligible voucher from Shipping Order\nCancel Shipping Order",
                        'status' => ShippingOrderExceptionStatus::Active,
                        'created_by' => Auth::id(),
                    ]);

                    $this->logEvent(
                        $shippingOrder,
                        self::EVENT_VOUCHER_INELIGIBLE,
                        "Voucher {$error['voucher_id']} became ineligible during SO lifecycle: {$error['reason']}",
                        null,
                        ['voucher_id' => $error['voucher_id'], 'reason' => $error['reason']]
                    );
                }

                throw new \InvalidArgumentException(
                    'Cannot proceed to picking: voucher validation failed. '
                    .'One or more vouchers are no longer eligible for fulfillment. '
                    .implode('; ', $errorMessages)
                );
            }
        }
    }

    /**
     * Handle post-transition side effects.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @param  ShippingOrderStatus  $from  The previous status
     * @param  ShippingOrderStatus  $to  The new status
     */
    protected function handlePostTransition(
        ShippingOrder $shippingOrder,
        ShippingOrderStatus $from,
        ShippingOrderStatus $to
    ): void {
        // Lock vouchers when moving to planned (if not already locked)
        if ($to === ShippingOrderStatus::Planned && ! $from->requiresVoucherLock()) {
            $this->lockVouchersForSO($shippingOrder);
        }

        // Update line statuses when moving to picking
        if ($to === ShippingOrderStatus::Picking) {
            $shippingOrder->lines()
                ->where('status', ShippingOrderLineStatus::Pending)
                ->update(['status' => ShippingOrderLineStatus::Validated]);
        }

        // Unlock vouchers when cancelled
        if ($to === ShippingOrderStatus::Cancelled && $from->requiresVoucherLock()) {
            $this->unlockVouchers($shippingOrder);
        }
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
