<?php

namespace App\Policies\Pim;

use App\Models\Pim\Appellation;
use App\Models\User;

/**
 * Policy for Appellation model authorization.
 */
class AppellationPolicy
{
    /**
     * Determine if the user can view any appellations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the appellation.
     */
    public function view(User $user, Appellation $appellation): bool
    {
        return true;
    }

    /**
     * Determine if the user can create appellations.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the appellation.
     */
    public function update(User $user, Appellation $appellation): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the appellation.
     */
    public function delete(User $user, Appellation $appellation): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the appellation.
     */
    public function restore(User $user, Appellation $appellation): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the appellation.
     */
    public function forceDelete(User $user, Appellation $appellation): bool
    {
        return false;
    }
}
