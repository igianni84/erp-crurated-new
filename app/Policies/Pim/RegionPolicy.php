<?php

namespace App\Policies\Pim;

use App\Models\Pim\Region;
use App\Models\User;

/**
 * Policy for Region model authorization.
 */
class RegionPolicy
{
    /**
     * Determine if the user can view any regions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the region.
     */
    public function view(User $user, Region $region): bool
    {
        return true;
    }

    /**
     * Determine if the user can create regions.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the region.
     */
    public function update(User $user, Region $region): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the region.
     */
    public function delete(User $user, Region $region): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the region.
     */
    public function restore(User $user, Region $region): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the region.
     */
    public function forceDelete(User $user, Region $region): bool
    {
        return false;
    }
}
