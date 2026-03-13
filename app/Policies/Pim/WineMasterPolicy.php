<?php

namespace App\Policies\Pim;

use App\Models\Pim\WineMaster;
use App\Models\User;

/**
 * Policy for WineMaster model authorization.
 */
class WineMasterPolicy
{
    /**
     * Determine if the user can view any wine masters.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the wine master.
     */
    public function view(User $user, WineMaster $wineMaster): bool
    {
        return true;
    }

    /**
     * Determine if the user can create wine masters.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the wine master.
     */
    public function update(User $user, WineMaster $wineMaster): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the wine master.
     */
    public function delete(User $user, WineMaster $wineMaster): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the wine master.
     */
    public function restore(User $user, WineMaster $wineMaster): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the wine master.
     */
    public function forceDelete(User $user, WineMaster $wineMaster): bool
    {
        return false;
    }
}
