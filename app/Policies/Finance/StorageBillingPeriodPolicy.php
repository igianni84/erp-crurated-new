<?php

namespace App\Policies\Finance;

use App\Models\Finance\StorageBillingPeriod;
use App\Models\User;

/**
 * Policy for StorageBillingPeriod model authorization.
 *
 * This policy provides role-based visibility for Finance module resources.
 */
class StorageBillingPeriodPolicy
{
    /**
     * Determine if the user can view any storage billing periods.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the storage billing period.
     */
    public function view(User $user, StorageBillingPeriod $storageBillingPeriod): bool
    {
        return true;
    }

    /**
     * Determine if the user can create storage billing periods.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the storage billing period.
     */
    public function update(User $user, StorageBillingPeriod $storageBillingPeriod): bool
    {
        return true;
    }

    /**
     * Determine if the user can delete the storage billing period.
     */
    public function delete(User $user, StorageBillingPeriod $storageBillingPeriod): bool
    {
        return false;
    }

    /**
     * Determine if the user can restore the storage billing period.
     */
    public function restore(User $user, StorageBillingPeriod $storageBillingPeriod): bool
    {
        return false;
    }

    /**
     * Determine if the user can force delete the storage billing period.
     */
    public function forceDelete(User $user, StorageBillingPeriod $storageBillingPeriod): bool
    {
        return false;
    }
}
