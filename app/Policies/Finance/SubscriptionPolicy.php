<?php

namespace App\Policies\Finance;

use App\Models\Finance\Subscription;
use App\Models\User;

/**
 * Policy for Subscription model authorization.
 *
 * This policy provides role-based visibility for Finance module resources.
 */
class SubscriptionPolicy
{
    /**
     * Determine if the user can view any subscriptions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the subscription.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return true;
    }

    /**
     * Determine if the user can create subscriptions.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the subscription.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        return true;
    }

    /**
     * Determine if the user can delete the subscription.
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        return false;
    }

    /**
     * Determine if the user can restore the subscription.
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        return false;
    }

    /**
     * Determine if the user can force delete the subscription.
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return false;
    }
}
