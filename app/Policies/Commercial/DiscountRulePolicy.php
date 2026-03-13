<?php

namespace App\Policies\Commercial;

use App\Models\Commercial\DiscountRule;
use App\Models\User;

/**
 * Policy for DiscountRule model authorization.
 */
class DiscountRulePolicy
{
    /**
     * Determine if the user can view any discount rules.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the discount rule.
     */
    public function view(User $user, DiscountRule $discountRule): bool
    {
        return true;
    }

    /**
     * Determine if the user can create discount rules.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the discount rule.
     */
    public function update(User $user, DiscountRule $discountRule): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the discount rule.
     */
    public function delete(User $user, DiscountRule $discountRule): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the discount rule.
     */
    public function restore(User $user, DiscountRule $discountRule): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the discount rule.
     */
    public function forceDelete(User $user, DiscountRule $discountRule): bool
    {
        return false;
    }
}
