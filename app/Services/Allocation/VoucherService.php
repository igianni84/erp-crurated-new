<?php

namespace App\Services\Allocation;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing Voucher lifecycle and operations.
 *
 * Centralizes all voucher business logic including issuance, lifecycle transitions,
 * and behavioral flag management.
 */
class VoucherService
{
    public function __construct(
        protected AllocationService $allocationService,
        protected CaseEntitlementService $caseEntitlementService
    ) {}

    /**
     * Issue vouchers from an allocation to a customer.
     *
     * Creates the specified quantity of vouchers (each with quantity=1)
     * and consumes the allocation accordingly.
     *
     * @param  int  $quantity  Number of vouchers to issue (each voucher = 1 bottle)
     * @return Collection<int, Voucher> The created vouchers
     *
     * @throws \InvalidArgumentException If allocation cannot be consumed or quantity is invalid
     */
    public function issueVouchers(
        Allocation $allocation,
        Customer $customer,
        ?SellableSku $sellableSku,
        string $saleReference,
        int $quantity
    ): Collection {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Quantity must be greater than zero.'
            );
        }

        if (! $allocation->canBeConsumed()) {
            throw new \InvalidArgumentException(
                "Cannot issue vouchers: allocation status '{$allocation->status->label()}' does not allow consumption "
                .'or has no remaining quantity.'
            );
        }

        return DB::transaction(function () use ($allocation, $customer, $sellableSku, $saleReference, $quantity): Collection {
            // Consume the allocation (this handles locking and availability checks)
            $allocation = $this->allocationService->consumeAllocation($allocation, $quantity);

            $vouchers = collect();

            for ($i = 0; $i < $quantity; $i++) {
                $voucher = Voucher::create([
                    'customer_id' => $customer->id,
                    'allocation_id' => $allocation->id,
                    'wine_variant_id' => $allocation->wine_variant_id,
                    'format_id' => $allocation->format_id,
                    'sellable_sku_id' => $sellableSku?->id,
                    'quantity' => 1, // Always 1
                    'lifecycle_state' => VoucherLifecycleState::Issued,
                    'tradable' => true, // Default
                    'giftable' => true, // Default
                    'suspended' => false, // Default
                    'sale_reference' => $saleReference,
                ]);

                // Log the issuance
                $this->logVoucherEvent(
                    $voucher,
                    AuditLog::EVENT_VOUCHER_ISSUED,
                    [],
                    [
                        'allocation_id' => $allocation->id,
                        'customer_id' => $customer->id,
                        'sale_reference' => $saleReference,
                        'sellable_sku_id' => $sellableSku?->id,
                    ]
                );

                $vouchers->push($voucher);
            }

            return $vouchers;
        });
    }

    /**
     * Lock a voucher for fulfillment (issued -> locked).
     *
     * When locked, the voucher cannot be traded, transferred, or modified
     * until it is either redeemed or unlocked.
     *
     * @throws \InvalidArgumentException If transition is not allowed or voucher is suspended
     */
    public function lockForFulfillment(Voucher $voucher): Voucher
    {
        $this->validateNotSuspended($voucher, 'lock for fulfillment');

        if (! $voucher->canTransitionTo(VoucherLifecycleState::Locked)) {
            throw new \InvalidArgumentException(
                "Cannot lock voucher: current state '{$voucher->lifecycle_state->label()}' does not allow transition to Locked. "
                .'Only issued vouchers can be locked.'
            );
        }

        $oldState = $voucher->lifecycle_state;
        $voucher->lifecycle_state = VoucherLifecycleState::Locked;
        $voucher->save();

        $this->logLifecycleTransition($voucher, $oldState, VoucherLifecycleState::Locked);

        return $voucher;
    }

    /**
     * Unlock a voucher (locked -> issued).
     *
     * Releases the lock, allowing the voucher to be traded or transferred again.
     *
     * @throws \InvalidArgumentException If transition is not allowed or voucher is suspended
     */
    public function unlock(Voucher $voucher): Voucher
    {
        $this->validateNotSuspended($voucher, 'unlock');

        if (! $voucher->canTransitionTo(VoucherLifecycleState::Issued)) {
            throw new \InvalidArgumentException(
                "Cannot unlock voucher: current state '{$voucher->lifecycle_state->label()}' does not allow transition to Issued. "
                .'Only locked vouchers can be unlocked.'
            );
        }

        $oldState = $voucher->lifecycle_state;
        $voucher->lifecycle_state = VoucherLifecycleState::Issued;
        $voucher->save();

        $this->logLifecycleTransition($voucher, $oldState, VoucherLifecycleState::Issued);

        return $voucher;
    }

    /**
     * Redeem a voucher (locked -> redeemed).
     *
     * This is a terminal state - the voucher cannot be modified after redemption.
     * Typically called when physical fulfillment is complete.
     *
     * If the voucher is part of a CaseEntitlement, redeeming it singularly
     * will break the case (partial redemption).
     *
     * @throws \InvalidArgumentException If transition is not allowed or voucher is suspended
     */
    public function redeem(Voucher $voucher): Voucher
    {
        $this->validateNotSuspended($voucher, 'redeem');

        if (! $voucher->canTransitionTo(VoucherLifecycleState::Redeemed)) {
            throw new \InvalidArgumentException(
                "Cannot redeem voucher: current state '{$voucher->lifecycle_state->label()}' does not allow transition to Redeemed. "
                .'Only locked vouchers can be redeemed.'
            );
        }

        // Break the case entitlement if voucher is part of one (partial redemption)
        $this->caseEntitlementService->breakIfVoucherInCase(
            $voucher,
            CaseEntitlementService::REASON_PARTIAL_REDEMPTION
        );

        $oldState = $voucher->lifecycle_state;
        $voucher->lifecycle_state = VoucherLifecycleState::Redeemed;
        $voucher->save();

        $this->logLifecycleTransition($voucher, $oldState, VoucherLifecycleState::Redeemed);

        return $voucher;
    }

    /**
     * Cancel a voucher (issued -> cancelled).
     *
     * This is a terminal state - the voucher cannot be modified after cancellation.
     * Note: Cancellation does NOT return quantity to the allocation.
     *
     * @throws \InvalidArgumentException If transition is not allowed or voucher is suspended
     */
    public function cancel(Voucher $voucher): Voucher
    {
        $this->validateNotSuspended($voucher, 'cancel');

        if (! $voucher->canTransitionTo(VoucherLifecycleState::Cancelled)) {
            throw new \InvalidArgumentException(
                "Cannot cancel voucher: current state '{$voucher->lifecycle_state->label()}' does not allow transition to Cancelled. "
                .'Only issued vouchers can be cancelled.'
            );
        }

        $oldState = $voucher->lifecycle_state;
        $voucher->lifecycle_state = VoucherLifecycleState::Cancelled;
        $voucher->save();

        $this->logLifecycleTransition($voucher, $oldState, VoucherLifecycleState::Cancelled);

        return $voucher;
    }

    /**
     * Suspend a voucher.
     *
     * Suspended vouchers cannot be traded, transferred, redeemed, or have their flags modified.
     * This is typically used during external trading or when manual intervention is needed.
     *
     * @param  string|null  $reason  Optional reason for suspension (e.g., 'external_trading')
     *
     * @throws \InvalidArgumentException If voucher is already suspended or in terminal state
     */
    public function suspend(Voucher $voucher, ?string $reason = null): Voucher
    {
        if ($voucher->suspended) {
            throw new \InvalidArgumentException(
                'Cannot suspend voucher: voucher is already suspended.'
            );
        }

        if ($voucher->isTerminal()) {
            throw new \InvalidArgumentException(
                "Cannot suspend voucher: voucher is in terminal state '{$voucher->lifecycle_state->label()}'."
            );
        }

        $voucher->suspended = true;
        $voucher->save();

        $this->logVoucherEvent(
            $voucher,
            AuditLog::EVENT_VOUCHER_SUSPENDED,
            ['suspended' => false],
            ['suspended' => true, 'reason' => $reason]
        );

        return $voucher;
    }

    /**
     * Reactivate (unsuspend) a voucher.
     *
     * Removes the suspension flag, allowing normal operations to resume.
     *
     * @throws \InvalidArgumentException If voucher is not suspended or in terminal state
     */
    public function reactivate(Voucher $voucher): Voucher
    {
        if (! $voucher->suspended) {
            throw new \InvalidArgumentException(
                'Cannot reactivate voucher: voucher is not suspended.'
            );
        }

        if ($voucher->isTerminal()) {
            throw new \InvalidArgumentException(
                "Cannot reactivate voucher: voucher is in terminal state '{$voucher->lifecycle_state->label()}'."
            );
        }

        $oldTradingRef = $voucher->external_trading_reference;
        $voucher->suspended = false;
        $voucher->external_trading_reference = null;
        $voucher->save();

        $this->logVoucherEvent(
            $voucher,
            AuditLog::EVENT_VOUCHER_REACTIVATED,
            ['suspended' => true, 'external_trading_reference' => $oldTradingRef],
            ['suspended' => false, 'external_trading_reference' => null]
        );

        return $voucher;
    }

    /**
     * Suspend a voucher for external trading.
     *
     * Suspends the voucher and stores the external trading reference.
     * Suspended vouchers cannot be redeemed, transferred, or modified.
     *
     * @param  string  $tradingReference  The reference from the external trading platform
     *
     * @throws \InvalidArgumentException If voucher cannot be suspended for trading
     */
    public function suspendForTrading(Voucher $voucher, string $tradingReference): Voucher
    {
        if (empty($tradingReference)) {
            throw new \InvalidArgumentException(
                'Cannot suspend for trading: trading reference is required.'
            );
        }

        if ($voucher->suspended) {
            throw new \InvalidArgumentException(
                'Cannot suspend for trading: voucher is already suspended.'
            );
        }

        if ($voucher->isTerminal()) {
            throw new \InvalidArgumentException(
                "Cannot suspend for trading: voucher is in terminal state '{$voucher->lifecycle_state->label()}'."
            );
        }

        if (! $voucher->isIssued()) {
            throw new \InvalidArgumentException(
                'Cannot suspend for trading: voucher must be in Issued state to be suspended for trading. '
                ."Current state: '{$voucher->lifecycle_state->label()}'."
            );
        }

        if (! $voucher->tradable) {
            throw new \InvalidArgumentException(
                'Cannot suspend for trading: voucher is not tradable.'
            );
        }

        if ($voucher->hasPendingTransfer()) {
            throw new \InvalidArgumentException(
                'Cannot suspend for trading: voucher has a pending transfer. Cancel the transfer first.'
            );
        }

        // Break case entitlement if voucher is part of one (trading breaks the case)
        $this->caseEntitlementService->breakIfVoucherInCase(
            $voucher,
            CaseEntitlementService::REASON_TRADE
        );

        $voucher->suspended = true;
        $voucher->external_trading_reference = $tradingReference;
        $voucher->save();

        $this->logVoucherEvent(
            $voucher,
            AuditLog::EVENT_TRADING_SUSPENDED,
            [
                'suspended' => false,
                'external_trading_reference' => null,
            ],
            [
                'suspended' => true,
                'external_trading_reference' => $tradingReference,
            ]
        );

        return $voucher;
    }

    /**
     * Complete external trading by transferring the voucher to a new customer.
     *
     * This is called when an external trading platform notifies us that
     * a trade has been completed. Updates the customer and unsuspends the voucher.
     * Lineage (allocation_id) is preserved - never modified.
     *
     * @param  string  $tradingReference  The reference from the external trading platform (must match)
     * @param  Customer  $newCustomer  The new owner of the voucher
     *
     * @throws \InvalidArgumentException If trading cannot be completed
     */
    public function completeTrading(Voucher $voucher, string $tradingReference, Customer $newCustomer): Voucher
    {
        if (empty($tradingReference)) {
            throw new \InvalidArgumentException(
                'Cannot complete trading: trading reference is required.'
            );
        }

        if (! $voucher->suspended) {
            throw new \InvalidArgumentException(
                'Cannot complete trading: voucher is not suspended.'
            );
        }

        if ($voucher->external_trading_reference === null) {
            throw new \InvalidArgumentException(
                'Cannot complete trading: voucher is not suspended for external trading.'
            );
        }

        if ($voucher->external_trading_reference !== $tradingReference) {
            throw new \InvalidArgumentException(
                'Cannot complete trading: trading reference does not match. '
                ."Expected: '{$voucher->external_trading_reference}', got: '{$tradingReference}'."
            );
        }

        if ($voucher->isTerminal()) {
            throw new \InvalidArgumentException(
                "Cannot complete trading: voucher is in terminal state '{$voucher->lifecycle_state->label()}'."
            );
        }

        return DB::transaction(function () use ($voucher, $tradingReference, $newCustomer): Voucher {
            $oldCustomerId = $voucher->customer_id;

            $voucher->customer_id = $newCustomer->id;
            $voucher->suspended = false;
            $voucher->external_trading_reference = null;
            $voucher->save();

            $this->logVoucherEvent(
                $voucher,
                AuditLog::EVENT_TRADING_COMPLETED,
                [
                    'customer_id' => $oldCustomerId,
                    'suspended' => true,
                    'external_trading_reference' => $tradingReference,
                ],
                [
                    'customer_id' => $newCustomer->id,
                    'suspended' => false,
                    'external_trading_reference' => null,
                ]
            );

            return $voucher;
        });
    }

    /**
     * Update the tradable flag on a voucher.
     *
     * @throws \InvalidArgumentException If flag modification is not allowed
     */
    public function setTradable(Voucher $voucher, bool $tradable): Voucher
    {
        $this->validateCanModifyFlags($voucher, 'tradable');

        if ($voucher->tradable === $tradable) {
            return $voucher;
        }

        $oldValue = $voucher->tradable;
        $voucher->tradable = $tradable;
        $voucher->save();

        $this->logFlagChange($voucher, 'tradable', $oldValue, $tradable);

        return $voucher;
    }

    /**
     * Update the giftable flag on a voucher.
     *
     * @throws \InvalidArgumentException If flag modification is not allowed
     */
    public function setGiftable(Voucher $voucher, bool $giftable): Voucher
    {
        $this->validateCanModifyFlags($voucher, 'giftable');

        if ($voucher->giftable === $giftable) {
            return $voucher;
        }

        $oldValue = $voucher->giftable;
        $voucher->giftable = $giftable;
        $voucher->save();

        $this->logFlagChange($voucher, 'giftable', $oldValue, $giftable);

        return $voucher;
    }

    /**
     * Perform a lifecycle state transition.
     *
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function transitionTo(Voucher $voucher, VoucherLifecycleState $targetState): Voucher
    {
        return match ($targetState) {
            VoucherLifecycleState::Issued => $this->unlock($voucher),
            VoucherLifecycleState::Locked => $this->lockForFulfillment($voucher),
            VoucherLifecycleState::Redeemed => $this->redeem($voucher),
            VoucherLifecycleState::Cancelled => $this->cancel($voucher),
        };
    }

    /**
     * Validate that the voucher is not suspended.
     *
     * @throws \InvalidArgumentException If voucher is suspended
     */
    protected function validateNotSuspended(Voucher $voucher, string $action): void
    {
        if ($voucher->suspended) {
            throw new \InvalidArgumentException(
                "Cannot {$action}: voucher is suspended. Reactivate the voucher first."
            );
        }
    }

    /**
     * Validate that flags can be modified on the voucher.
     *
     * @throws \InvalidArgumentException If flags cannot be modified
     */
    protected function validateCanModifyFlags(Voucher $voucher, string $flagName): void
    {
        if ($voucher->suspended) {
            throw new \InvalidArgumentException(
                "Cannot modify {$flagName} flag: voucher is suspended. Reactivate the voucher first."
            );
        }

        if (! $voucher->lifecycle_state->allowsFlagModification()) {
            throw new \InvalidArgumentException(
                "Cannot modify {$flagName} flag: voucher is in terminal state '{$voucher->lifecycle_state->label()}'."
            );
        }

        // Tradable and giftable flags can only be modified on issued vouchers
        if ($flagName !== 'suspended' && ! $voucher->isIssued()) {
            throw new \InvalidArgumentException(
                "Cannot modify {$flagName} flag: only issued vouchers can have their trading flags modified."
            );
        }
    }

    /**
     * Log a lifecycle state transition to the audit log.
     */
    protected function logLifecycleTransition(
        Voucher $voucher,
        VoucherLifecycleState $oldState,
        VoucherLifecycleState $newState
    ): void {
        $voucher->auditLogs()->create([
            'event' => AuditLog::EVENT_LIFECYCLE_CHANGE,
            'old_values' => [
                'lifecycle_state' => $oldState->value,
                'lifecycle_state_label' => $oldState->label(),
            ],
            'new_values' => [
                'lifecycle_state' => $newState->value,
                'lifecycle_state_label' => $newState->label(),
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log a flag change to the audit log.
     */
    protected function logFlagChange(Voucher $voucher, string $flagName, bool $oldValue, bool $newValue): void
    {
        $voucher->auditLogs()->create([
            'event' => AuditLog::EVENT_FLAG_CHANGE,
            'old_values' => [
                $flagName => $oldValue,
            ],
            'new_values' => [
                $flagName => $newValue,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log a general voucher event to the audit log.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logVoucherEvent(
        Voucher $voucher,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $voucher->auditLogs()->create([
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }
}
