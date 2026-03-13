<?php

namespace App\Policies\Commercial;

use App\Models\Commercial\Bundle;
use App\Models\User;

/**
 * Policy for Bundle model authorization.
 */
class BundlePolicy
{
    /**
     * Determine if the user can view any bundles.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the bundle.
     */
    public function view(User $user, Bundle $bundle): bool
    {
        return true;
    }

    /**
     * Determine if the user can create bundles.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the bundle.
     */
    public function update(User $user, Bundle $bundle): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the bundle.
     */
    public function delete(User $user, Bundle $bundle): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the bundle.
     */
    public function restore(User $user, Bundle $bundle): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the bundle.
     */
    public function forceDelete(User $user, Bundle $bundle): bool
    {
        return false;
    }
}
