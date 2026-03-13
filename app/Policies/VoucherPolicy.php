<?php

namespace App\Policies;

use App\Models\Allocation\Voucher;
use App\Models\User;

/**
 * Policy for Voucher model authorization.
 */
class VoucherPolicy
{
    /**
     * Determine if the user can view any vouchers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the voucher.
     */
    public function view(User $user, Voucher $voucher): bool
    {
        return true;
    }

    /**
     * Determine if the user can create vouchers.
     *
     * Note: Vouchers are typically created by services (sale confirmation),
     * not directly by users.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can update the voucher.
     */
    public function update(User $user, Voucher $voucher): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the voucher.
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage behavioral flags on the voucher.
     *
     * This permission controls whether the flag toggle actions
     * (tradable, giftable, suspended) are visible.
     * Requires at least editor role to prevent viewers from modifying flags.
     */
    public function manageFlags(User $user, Voucher $voucher): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can manage the tradable flag.
     */
    public function setTradable(User $user, Voucher $voucher): bool
    {
        return $this->manageFlags($user, $voucher);
    }

    /**
     * Determine if the user can manage the giftable flag.
     */
    public function setGiftable(User $user, Voucher $voucher): bool
    {
        return $this->manageFlags($user, $voucher);
    }

    /**
     * Determine if the user can suspend the voucher.
     */
    public function suspend(User $user, Voucher $voucher): bool
    {
        return $this->manageFlags($user, $voucher);
    }

    /**
     * Determine if the user can reactivate (unsuspend) the voucher.
     */
    public function reactivate(User $user, Voucher $voucher): bool
    {
        return $this->manageFlags($user, $voucher);
    }

    /**
     * Determine if the user can initiate a transfer for the voucher.
     *
     * Transfers move voucher ownership between customers.
     */
    public function initiateTransfer(User $user, Voucher $voucher): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can cancel a transfer for the voucher.
     *
     * Only pending transfers can be cancelled.
     */
    public function cancelTransfer(User $user, Voucher $voucher): bool
    {
        return $user->canEdit();
    }
}
