<?php

namespace App\Policies\Pim;

use App\Models\Pim\WineVariant;
use App\Models\User;

/**
 * Policy for WineVariant model authorization.
 */
class WineVariantPolicy
{
    /**
     * Determine if the user can view any wine variants.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the wine variant.
     */
    public function view(User $user, WineVariant $wineVariant): bool
    {
        return true;
    }

    /**
     * Determine if the user can create wine variants.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the wine variant.
     */
    public function update(User $user, WineVariant $wineVariant): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the wine variant.
     */
    public function delete(User $user, WineVariant $wineVariant): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the wine variant.
     */
    public function restore(User $user, WineVariant $wineVariant): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the wine variant.
     */
    public function forceDelete(User $user, WineVariant $wineVariant): bool
    {
        return false;
    }
}
