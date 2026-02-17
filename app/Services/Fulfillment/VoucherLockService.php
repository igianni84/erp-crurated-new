<?php

namespace App\Services\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderAuditLog;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Services\Allocation\VoucherService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

/**
 * Service for managing voucher locks during fulfillment.
 *
 * This service centralizes all voucher lock/unlock logic for the Fulfillment module,
 * providing a clean integration layer with Module A's VoucherService.
 *
 * Key responsibilities:
 * - Lock vouchers when a Shipping Order moves to planned status
 * - Unlock vouchers when a Shipping Order is cancelled
 * - Track which vouchers are locked for which Shipping Order
 * - Prevent the same voucher from being in multiple active SOs
 *
 * Key invariants:
 * - A voucher can only be locked for ONE active Shipping Order at a time
 * - Locked vouchers cannot be traded, transferred, or assigned to another SO
 * - Unlocking releases the voucher back to issued state (available for new SOs)
 */
class VoucherLockService
{
    /**
     * Event types for audit logging.
     */
    public const EVENT_VOUCHER_LOCKED = 'voucher_locked';

    public const EVENT_VOUCHER_UNLOCKED = 'voucher_unlocked';

    public const EVENT_LOCK_FAILED = 'lock_failed';

    public const EVENT_UNLOCK_FAILED = 'unlock_failed';

    public function __construct(
        protected VoucherService $voucherService
    ) {}

    /**
     * Lock a voucher for a Shipping Order.
     *
     * When a voucher is locked:
     * - Its lifecycle state transitions from issued → locked
     * - It cannot be traded, transferred, or assigned to another SO
     * - It remains locked until the SO is completed or cancelled
     *
     * @param  Voucher  $voucher  The voucher to lock
     * @param  ShippingOrder  $shippingOrder  The shipping order to lock for
     * @return Voucher The locked voucher
     *
     * @throws InvalidArgumentException If voucher cannot be locked
     */
    public function lockForShippingOrder(Voucher $voucher, ShippingOrder $shippingOrder): Voucher
    {
        // Validate the voucher is in this shipping order
        if (! $this->isVoucherInShippingOrder($voucher, $shippingOrder)) {
            throw new InvalidArgumentException(
                "Cannot lock voucher: voucher {$voucher->id} is not in Shipping Order {$shippingOrder->id}."
            );
        }

        // Check if already locked
        if ($voucher->isLocked()) {
            // If locked for this SO, return early (idempotent)
            if ($this->isLockedForSO($voucher, $shippingOrder)) {
                return $voucher;
            }

            // Locked for a different SO - error
            $existingSO = $this->findShippingOrderForLockedVoucher($voucher);
            throw new InvalidArgumentException(
                "Cannot lock voucher: voucher {$voucher->id} is already locked for "
                .($existingSO !== null ? "Shipping Order {$existingSO->id}" : 'another process').'.'
            );
        }

        // Use Module A VoucherService to perform the lock
        try {
            $voucher = $this->voucherService->lockForFulfillment($voucher);

            // Log the lock event
            $this->logEvent(
                $shippingOrder,
                self::EVENT_VOUCHER_LOCKED,
                "Voucher {$voucher->id} locked for fulfillment",
                ['voucher_id' => $voucher->id, 'lifecycle_state' => 'issued'],
                ['voucher_id' => $voucher->id, 'lifecycle_state' => 'locked']
            );

            return $voucher;
        } catch (Throwable $e) {
            $this->logEvent(
                $shippingOrder,
                self::EVENT_LOCK_FAILED,
                "Failed to lock voucher {$voucher->id}: {$e->getMessage()}",
                null,
                ['voucher_id' => $voucher->id, 'error' => $e->getMessage()]
            );

            throw new InvalidArgumentException(
                "Failed to lock voucher {$voucher->id}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Lock all vouchers for a Shipping Order.
     *
     * Atomically locks all vouchers in the SO. If any lock fails, all previously
     * locked vouchers are rolled back.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @return Collection<int, Voucher> The locked vouchers
     *
     * @throws InvalidArgumentException If any voucher cannot be locked
     */
    public function lockAllForShippingOrder(ShippingOrder $shippingOrder): Collection
    {
        $shippingOrder->load('lines.voucher');

        $lockedVouchers = collect();

        try {
            foreach ($shippingOrder->lines as $line) {
                $voucher = $line->voucher;
                if ($voucher === null) {
                    continue;
                }

                // Skip if already locked for this SO (idempotent)
                if ($voucher->isLocked() && $this->isLockedForSO($voucher, $shippingOrder)) {
                    $lockedVouchers->push($voucher);

                    continue;
                }

                $lockedVoucher = $this->lockForShippingOrder($voucher, $shippingOrder);
                $lockedVouchers->push($lockedVoucher);
            }

            return $lockedVouchers;
        } catch (Throwable $e) {
            // Rollback: unlock any vouchers we already locked in this operation
            foreach ($lockedVouchers as $voucher) {
                try {
                    // Only unlock if we locked it (not if it was already locked)
                    if ($voucher->isLocked()) {
                        $this->voucherService->unlock($voucher);
                    }
                } catch (Throwable) {
                    // Best effort rollback - log but don't fail
                }
            }

            throw new InvalidArgumentException(
                "Failed to lock vouchers for Shipping Order {$shippingOrder->id}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Unlock a voucher.
     *
     * Transitions the voucher from locked → issued state, making it available
     * for trading, transfer, or assignment to a new Shipping Order.
     *
     * @param  Voucher  $voucher  The voucher to unlock
     * @return Voucher The unlocked voucher
     *
     * @throws InvalidArgumentException If voucher cannot be unlocked
     */
    public function unlock(Voucher $voucher): Voucher
    {
        // Check if voucher is actually locked
        if (! $voucher->isLocked()) {
            // Already unlocked - idempotent behavior
            return $voucher;
        }

        // Use Module A VoucherService to perform the unlock
        return $this->voucherService->unlock($voucher);
    }

    /**
     * Unlock all vouchers for a Shipping Order.
     *
     * Typically called when a Shipping Order is cancelled.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @return Collection<int, Voucher> The unlocked vouchers
     */
    public function unlockAllForShippingOrder(ShippingOrder $shippingOrder): Collection
    {
        $shippingOrder->load('lines.voucher');

        $unlockedVouchers = collect();

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
                $unlockedVoucher = $this->unlock($voucher);
                $unlockedVouchers->push($unlockedVoucher);

                $this->logEvent(
                    $shippingOrder,
                    self::EVENT_VOUCHER_UNLOCKED,
                    "Voucher {$voucher->id} unlocked",
                    ['voucher_id' => $voucher->id, 'lifecycle_state' => 'locked'],
                    ['voucher_id' => $voucher->id, 'lifecycle_state' => 'issued']
                );
            } catch (Throwable $e) {
                // Log but continue - best effort unlock
                $this->logEvent(
                    $shippingOrder,
                    self::EVENT_UNLOCK_FAILED,
                    "Failed to unlock voucher {$voucher->id}: {$e->getMessage()}",
                    null,
                    ['voucher_id' => $voucher->id, 'error' => $e->getMessage()]
                );
            }
        }

        return $unlockedVouchers;
    }

    /**
     * Check if a voucher is locked for a specific Shipping Order.
     *
     * @param  Voucher  $voucher  The voucher to check
     * @param  ShippingOrder  $shippingOrder  The shipping order
     */
    public function isLockedForSO(Voucher $voucher, ShippingOrder $shippingOrder): bool
    {
        // Voucher must be in locked state
        if (! $voucher->isLocked()) {
            return false;
        }

        // Voucher must be in an active line of this SO
        return $this->isVoucherInShippingOrder($voucher, $shippingOrder);
    }

    /**
     * Get all vouchers that are locked for a specific Shipping Order.
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
     * Check if a voucher is currently assigned to any active Shipping Order.
     *
     * @param  Voucher  $voucher  The voucher to check
     * @param  ShippingOrder|null  $excludeShippingOrder  Optional SO to exclude from check
     * @return bool True if voucher is in an active SO
     */
    public function isVoucherInActiveShippingOrder(Voucher $voucher, ?ShippingOrder $excludeShippingOrder = null): bool
    {
        $query = ShippingOrderLine::query()
            ->where('voucher_id', $voucher->id)
            ->whereHas('shippingOrder', function ($q) {
                $q->whereIn('status', [
                    ShippingOrderStatus::Draft->value,
                    ShippingOrderStatus::Planned->value,
                    ShippingOrderStatus::Picking->value,
                    ShippingOrderStatus::OnHold->value,
                ]);
            });

        if ($excludeShippingOrder !== null) {
            $query->where('shipping_order_id', '!=', $excludeShippingOrder->id);
        }

        return $query->exists();
    }

    /**
     * Find the Shipping Order that a locked voucher is assigned to.
     *
     * @param  Voucher  $voucher  The locked voucher
     * @return ShippingOrder|null The shipping order, or null if not found
     */
    public function findShippingOrderForLockedVoucher(Voucher $voucher): ?ShippingOrder
    {
        if (! $voucher->isLocked()) {
            return null;
        }

        $line = ShippingOrderLine::query()
            ->where('voucher_id', $voucher->id)
            ->whereHas('shippingOrder', function ($q) {
                $q->whereIn('status', [
                    ShippingOrderStatus::Planned->value,
                    ShippingOrderStatus::Picking->value,
                    ShippingOrderStatus::OnHold->value,
                ]);
            })
            ->with('shippingOrder')
            ->first();

        return $line?->shippingOrder;
    }

    /**
     * Validate that a voucher can be locked for a Shipping Order.
     *
     * @param  Voucher  $voucher  The voucher to validate
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @return array{can_lock: bool, reason: string|null}
     */
    public function validateCanLock(Voucher $voucher, ShippingOrder $shippingOrder): array
    {
        // Check if voucher is in this SO
        if (! $this->isVoucherInShippingOrder($voucher, $shippingOrder)) {
            return [
                'can_lock' => false,
                'reason' => "Voucher is not in Shipping Order {$shippingOrder->id}.",
            ];
        }

        // Check if already locked
        if ($voucher->isLocked()) {
            // OK if locked for this SO
            if ($this->isLockedForSO($voucher, $shippingOrder)) {
                return ['can_lock' => true, 'reason' => null];
            }

            // Not OK if locked for another SO
            $existingSO = $this->findShippingOrderForLockedVoucher($voucher);

            return [
                'can_lock' => false,
                'reason' => 'Voucher is already locked for '
                    .($existingSO !== null ? "Shipping Order {$existingSO->id}" : 'another process').'.',
            ];
        }

        // Check voucher eligibility via VoucherService
        $eligibility = $this->voucherService->checkFulfillmentEligibility($voucher);
        if (! $eligibility['fulfillable']) {
            // Note: this checks for locked state, but voucher is not locked here
            // The check is useful for other conditions like suspension
            if ($voucher->suspended) {
                return [
                    'can_lock' => false,
                    'reason' => 'Voucher is suspended and cannot be locked.',
                ];
            }
        }

        // Check voucher is in correct lifecycle state
        if (! $voucher->isIssued()) {
            return [
                'can_lock' => false,
                'reason' => "Voucher is in state '{$voucher->lifecycle_state->label()}'. Only issued vouchers can be locked.",
            ];
        }

        // Check voucher is not in another active SO
        if ($this->isVoucherInActiveShippingOrder($voucher, $shippingOrder)) {
            $existingLine = ShippingOrderLine::query()
                ->where('voucher_id', $voucher->id)
                ->where('shipping_order_id', '!=', $shippingOrder->id)
                ->whereHas('shippingOrder', function ($q) {
                    $q->whereIn('status', [
                        ShippingOrderStatus::Draft->value,
                        ShippingOrderStatus::Planned->value,
                        ShippingOrderStatus::Picking->value,
                        ShippingOrderStatus::OnHold->value,
                    ]);
                })
                ->with('shippingOrder')
                ->first();

            $existingShippingOrderId = $existingLine?->shippingOrder?->id;

            return [
                'can_lock' => false,
                'reason' => 'Voucher is already assigned to Shipping Order '.($existingShippingOrderId !== null ? $existingShippingOrderId : 'unknown').'.',
            ];
        }

        return ['can_lock' => true, 'reason' => null];
    }

    /**
     * Check if a voucher is in a specific Shipping Order.
     *
     * @param  Voucher  $voucher  The voucher to check
     * @param  ShippingOrder  $shippingOrder  The shipping order
     */
    protected function isVoucherInShippingOrder(Voucher $voucher, ShippingOrder $shippingOrder): bool
    {
        return ShippingOrderLine::query()
            ->where('shipping_order_id', $shippingOrder->id)
            ->where('voucher_id', $voucher->id)
            ->whereNot('status', ShippingOrderLineStatus::Cancelled)
            ->exists();
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
