<?php

namespace App\Policies\Finance;

use App\Models\Finance\Refund;
use App\Models\User;

/**
 * Policy for Refund model authorization.
 *
 * This policy provides role-based visibility for Finance module resources.
 */
class RefundPolicy
{
    /**
     * Determine if the user can view any refunds.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the refund.
     */
    public function view(User $user, Refund $refund): bool
    {
        return true;
    }

    /**
     * Determine if the user can create refunds.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the refund.
     */
    public function update(User $user, Refund $refund): bool
    {
        // Only pending refunds can be updated
        return $refund->status === \App\Enums\Finance\RefundStatus::Pending;
    }

    /**
     * Determine if the user can delete the refund.
     */
    public function delete(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * Determine if the user can restore the refund.
     */
    public function restore(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * Determine if the user can force delete the refund.
     */
    public function forceDelete(User $user, Refund $refund): bool
    {
        return false;
    }
}
