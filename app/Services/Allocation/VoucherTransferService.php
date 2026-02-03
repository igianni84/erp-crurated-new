<?php

namespace App\Services\Allocation;

use App\Enums\Allocation\VoucherTransferStatus;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing VoucherTransfer operations.
 *
 * Centralizes all voucher transfer business logic including initiating,
 * accepting, cancelling, and expiring transfers.
 *
 * Key behaviors:
 * - A transfer does NOT create a new voucher - it only changes the holder
 * - A transfer does NOT consume allocation - it's purely a customer-to-customer operation
 * - If a voucher is part of a CaseEntitlement, accepting the transfer breaks the case
 */
class VoucherTransferService
{
    public function __construct(
        protected CaseEntitlementService $caseEntitlementService
    ) {}

    /**
     * Initiate a transfer of a voucher to another customer.
     *
     * Creates a pending transfer that the recipient must accept.
     *
     * @param  Voucher  $voucher  The voucher to transfer
     * @param  Customer  $toCustomer  The recipient customer
     * @param  Carbon  $expiresAt  When the transfer offer expires
     * @return VoucherTransfer The created transfer
     *
     * @throws \InvalidArgumentException If transfer cannot be initiated
     */
    public function initiateTransfer(
        Voucher $voucher,
        Customer $toCustomer,
        Carbon $expiresAt
    ): VoucherTransfer {
        // Validate voucher state
        $this->validateCanInitiateTransfer($voucher);

        // Validate recipient is different from current holder
        if ($voucher->customer_id === $toCustomer->id) {
            throw new \InvalidArgumentException(
                'Cannot transfer voucher to the same customer.'
            );
        }

        // Validate expiration is in the future
        if ($expiresAt->isPast()) {
            throw new \InvalidArgumentException(
                'Transfer expiration date must be in the future.'
            );
        }

        $fromCustomer = $voucher->customer;

        $transfer = VoucherTransfer::create([
            'voucher_id' => $voucher->id,
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
            'status' => VoucherTransferStatus::Pending,
            'initiated_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // Log the transfer initiation on the voucher
        $this->logTransferEvent(
            $voucher,
            AuditLog::EVENT_TRANSFER_INITIATED,
            [],
            [
                'transfer_id' => $transfer->id,
                'from_customer_id' => $fromCustomer->id,
                'to_customer_id' => $toCustomer->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ]
        );

        // Also log on the transfer itself
        $this->logTransferTransitionEvent(
            $transfer,
            AuditLog::EVENT_TRANSFER_INITIATED,
            [],
            [
                'voucher_id' => $voucher->id,
                'from_customer_id' => $fromCustomer->id,
                'to_customer_id' => $toCustomer->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ]
        );

        return $transfer;
    }

    /**
     * Accept a pending transfer.
     *
     * Updates the voucher's customer_id to the recipient and marks the transfer as accepted.
     * If the voucher is part of a CaseEntitlement, the case is broken.
     *
     * @param  VoucherTransfer  $transfer  The transfer to accept
     * @return VoucherTransfer The updated transfer
     *
     * @throws \InvalidArgumentException If transfer cannot be accepted
     */
    public function acceptTransfer(VoucherTransfer $transfer): VoucherTransfer
    {
        if (! $transfer->canBeAccepted()) {
            throw new \InvalidArgumentException(
                "Cannot accept transfer: transfer is in status '{$transfer->status->label()}'. "
                .'Only pending transfers can be accepted.'
            );
        }

        // Check if the transfer has expired
        if ($transfer->hasExpired()) {
            throw new \InvalidArgumentException(
                'Cannot accept transfer: transfer has expired.'
            );
        }

        $voucher = $transfer->voucher;

        // Validate voucher is not locked
        if ($voucher->isLocked()) {
            throw new \InvalidArgumentException(
                'Cannot accept transfer: voucher is locked for fulfillment. '
                .'The transfer cannot be completed while the voucher is locked.'
            );
        }

        // Validate voucher is not suspended
        if ($voucher->suspended) {
            throw new \InvalidArgumentException(
                'Cannot accept transfer: voucher is suspended. '
                .'The transfer cannot be completed while the voucher is suspended.'
            );
        }

        // Validate voucher is not in terminal state
        if ($voucher->isTerminal()) {
            throw new \InvalidArgumentException(
                "Cannot accept transfer: voucher is in terminal state '{$voucher->lifecycle_state->label()}'."
            );
        }

        return DB::transaction(function () use ($transfer, $voucher): VoucherTransfer {
            $oldCustomerId = $voucher->customer_id;
            $newCustomerId = $transfer->to_customer_id;

            // Break the case entitlement if voucher is part of one
            $this->caseEntitlementService->breakIfVoucherInCase(
                $voucher,
                CaseEntitlementService::REASON_TRANSFER
            );

            // Update voucher ownership
            $voucher->customer_id = $newCustomerId;
            $voucher->save();

            // Update transfer status
            $transfer->status = VoucherTransferStatus::Accepted;
            $transfer->accepted_at = now();
            $transfer->save();

            // Log the acceptance on the voucher
            $this->logTransferEvent(
                $voucher,
                AuditLog::EVENT_TRANSFER_ACCEPTED,
                ['customer_id' => $oldCustomerId],
                [
                    'customer_id' => $newCustomerId,
                    'transfer_id' => $transfer->id,
                ]
            );

            // Log on the transfer
            $this->logTransferTransitionEvent(
                $transfer,
                AuditLog::EVENT_TRANSFER_ACCEPTED,
                [
                    'status' => VoucherTransferStatus::Pending->value,
                    'status_label' => VoucherTransferStatus::Pending->label(),
                ],
                [
                    'status' => VoucherTransferStatus::Accepted->value,
                    'status_label' => VoucherTransferStatus::Accepted->label(),
                    'old_customer_id' => $oldCustomerId,
                    'new_customer_id' => $newCustomerId,
                ]
            );

            return $transfer;
        });
    }

    /**
     * Cancel a pending transfer.
     *
     * @param  VoucherTransfer  $transfer  The transfer to cancel
     * @return VoucherTransfer The updated transfer
     *
     * @throws \InvalidArgumentException If transfer cannot be cancelled
     */
    public function cancelTransfer(VoucherTransfer $transfer): VoucherTransfer
    {
        if (! $transfer->canBeCancelled()) {
            throw new \InvalidArgumentException(
                "Cannot cancel transfer: transfer is in status '{$transfer->status->label()}'. "
                .'Only pending transfers can be cancelled.'
            );
        }

        $transfer->status = VoucherTransferStatus::Cancelled;
        $transfer->cancelled_at = now();
        $transfer->save();

        // Log on the voucher
        $this->logTransferEvent(
            $transfer->voucher,
            AuditLog::EVENT_TRANSFER_CANCELLED,
            [
                'transfer_id' => $transfer->id,
                'status' => VoucherTransferStatus::Pending->value,
            ],
            [
                'transfer_id' => $transfer->id,
                'status' => VoucherTransferStatus::Cancelled->value,
            ]
        );

        // Log on the transfer
        $this->logTransferTransitionEvent(
            $transfer,
            AuditLog::EVENT_TRANSFER_CANCELLED,
            [
                'status' => VoucherTransferStatus::Pending->value,
                'status_label' => VoucherTransferStatus::Pending->label(),
            ],
            [
                'status' => VoucherTransferStatus::Cancelled->value,
                'status_label' => VoucherTransferStatus::Cancelled->label(),
            ]
        );

        return $transfer;
    }

    /**
     * Expire pending transfers that have passed their expiration date.
     *
     * This method is intended to be called by a scheduled job.
     *
     * @return int The number of transfers expired
     */
    public function expireTransfers(): int
    {
        $expiredCount = 0;

        VoucherTransfer::needsExpiration()->each(function (VoucherTransfer $transfer) use (&$expiredCount): void {
            $this->expireTransfer($transfer);
            $expiredCount++;
        });

        return $expiredCount;
    }

    /**
     * Expire a single transfer.
     *
     * @param  VoucherTransfer  $transfer  The transfer to expire
     * @return VoucherTransfer The updated transfer
     *
     * @throws \InvalidArgumentException If transfer cannot be expired
     */
    public function expireTransfer(VoucherTransfer $transfer): VoucherTransfer
    {
        if (! $transfer->isPending()) {
            throw new \InvalidArgumentException(
                "Cannot expire transfer: transfer is in status '{$transfer->status->label()}'. "
                .'Only pending transfers can be expired.'
            );
        }

        $transfer->status = VoucherTransferStatus::Expired;
        $transfer->save();

        // Log on the voucher
        $this->logTransferEvent(
            $transfer->voucher,
            AuditLog::EVENT_TRANSFER_EXPIRED,
            [
                'transfer_id' => $transfer->id,
                'status' => VoucherTransferStatus::Pending->value,
            ],
            [
                'transfer_id' => $transfer->id,
                'status' => VoucherTransferStatus::Expired->value,
                'expired_at' => now()->toIso8601String(),
            ]
        );

        // Log on the transfer
        $this->logTransferTransitionEvent(
            $transfer,
            AuditLog::EVENT_TRANSFER_EXPIRED,
            [
                'status' => VoucherTransferStatus::Pending->value,
                'status_label' => VoucherTransferStatus::Pending->label(),
            ],
            [
                'status' => VoucherTransferStatus::Expired->value,
                'status_label' => VoucherTransferStatus::Expired->label(),
            ]
        );

        return $transfer;
    }

    /**
     * Get pending transfers for a voucher.
     *
     * @return VoucherTransfer|null The pending transfer, if any
     */
    public function getPendingTransfer(Voucher $voucher): ?VoucherTransfer
    {
        return $voucher->getPendingTransfer();
    }

    /**
     * Check if a voucher has a pending transfer.
     */
    public function hasPendingTransfer(Voucher $voucher): bool
    {
        return $voucher->hasPendingTransfer();
    }

    /**
     * Validate that a transfer can be initiated for a voucher.
     *
     * @throws \InvalidArgumentException If transfer cannot be initiated
     */
    protected function validateCanInitiateTransfer(Voucher $voucher): void
    {
        // Check if voucher is in issued state (only issued vouchers can be transferred)
        if (! $voucher->isIssued()) {
            throw new \InvalidArgumentException(
                "Cannot initiate transfer: voucher is in state '{$voucher->lifecycle_state->label()}'. "
                .'Only issued vouchers can be transferred.'
            );
        }

        // Check if voucher is suspended
        if ($voucher->suspended) {
            throw new \InvalidArgumentException(
                'Cannot initiate transfer: voucher is suspended.'
            );
        }

        // Check if voucher already has a pending transfer
        if ($voucher->hasPendingTransfer()) {
            throw new \InvalidArgumentException(
                'Cannot initiate transfer: voucher already has a pending transfer. '
                .'Cancel the existing transfer first.'
            );
        }

        // Check if voucher is giftable (transfers use the giftable flag)
        if (! $voucher->giftable) {
            throw new \InvalidArgumentException(
                'Cannot initiate transfer: voucher is not giftable.'
            );
        }
    }

    /**
     * Log a transfer event to the voucher's audit log.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logTransferEvent(
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

    /**
     * Log a transfer transition event to the transfer's audit log.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logTransferTransitionEvent(
        VoucherTransfer $transfer,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $transfer->auditLogs()->create([
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }
}
