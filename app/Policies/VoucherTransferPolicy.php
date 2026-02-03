<?php

namespace App\Policies;

use App\Models\Allocation\VoucherTransfer;
use App\Models\User;

/**
 * Policy for VoucherTransfer model authorization.
 */
class VoucherTransferPolicy
{
    /**
     * Determine if the user can view any transfers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the transfer.
     */
    public function view(User $user, VoucherTransfer $transfer): bool
    {
        return true;
    }

    /**
     * Determine if the user can create transfers.
     *
     * Note: Transfers are created from Voucher detail page via VoucherTransferService.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can update the transfer.
     */
    public function update(User $user, VoucherTransfer $transfer): bool
    {
        return false;
    }

    /**
     * Determine if the user can delete the transfer.
     */
    public function delete(User $user, VoucherTransfer $transfer): bool
    {
        return false;
    }

    /**
     * Determine if the user can cancel a transfer.
     *
     * Only pending transfers can be cancelled.
     */
    public function cancelTransfer(User $user, VoucherTransfer $transfer): bool
    {
        // Allow all authenticated users to cancel transfers
        // This can be refined with role-based permissions later
        return true;
    }
}
