<?php

namespace App\Policies\Pim;

use App\Models\Pim\LiquidProduct;
use App\Models\User;

/**
 * Policy for LiquidProduct model authorization.
 */
class LiquidProductPolicy
{
    /**
     * Determine if the user can view any liquid products.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the liquid product.
     */
    public function view(User $user, LiquidProduct $liquidProduct): bool
    {
        return true;
    }

    /**
     * Determine if the user can create liquid products.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the liquid product.
     */
    public function update(User $user, LiquidProduct $liquidProduct): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the liquid product.
     */
    public function delete(User $user, LiquidProduct $liquidProduct): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the liquid product.
     */
    public function restore(User $user, LiquidProduct $liquidProduct): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the liquid product.
     */
    public function forceDelete(User $user, LiquidProduct $liquidProduct): bool
    {
        return false;
    }
}
