<?php

namespace App\Policies\Commercial;

use App\Models\Commercial\PricingPolicy;
use App\Models\User;

/**
 * Policy for PricingPolicy model authorization.
 */
class PricingPolicyPolicy
{
    /**
     * Determine if the user can view any pricing policies.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the pricing policy.
     */
    public function view(User $user, PricingPolicy $pricingPolicy): bool
    {
        return true;
    }

    /**
     * Determine if the user can create pricing policies.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the pricing policy.
     */
    public function update(User $user, PricingPolicy $pricingPolicy): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the pricing policy.
     */
    public function delete(User $user, PricingPolicy $pricingPolicy): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the pricing policy.
     */
    public function restore(User $user, PricingPolicy $pricingPolicy): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the pricing policy.
     */
    public function forceDelete(User $user, PricingPolicy $pricingPolicy): bool
    {
        return false;
    }
}
