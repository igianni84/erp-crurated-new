<?php

namespace App\Policies\Pim;

use App\Models\Pim\CaseConfiguration;
use App\Models\User;

/**
 * Policy for CaseConfiguration model authorization.
 */
class CaseConfigurationPolicy
{
    /**
     * Determine if the user can view any case configurations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the case configuration.
     */
    public function view(User $user, CaseConfiguration $caseConfiguration): bool
    {
        return true;
    }

    /**
     * Determine if the user can create case configurations.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the case configuration.
     */
    public function update(User $user, CaseConfiguration $caseConfiguration): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the case configuration.
     */
    public function delete(User $user, CaseConfiguration $caseConfiguration): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the case configuration.
     */
    public function restore(User $user, CaseConfiguration $caseConfiguration): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the case configuration.
     */
    public function forceDelete(User $user, CaseConfiguration $caseConfiguration): bool
    {
        return false;
    }
}
