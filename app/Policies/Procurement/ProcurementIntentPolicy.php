<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\ProcurementIntent;
use App\Models\User;

/**
 * Policy for ProcurementIntent model authorization.
 */
class ProcurementIntentPolicy
{
    /**
     * Determine if the user can view any procurement intents.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the procurement intent.
     */
    public function view(User $user, ProcurementIntent $procurementIntent): bool
    {
        return true;
    }

    /**
     * Determine if the user can create procurement intents.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the procurement intent.
     */
    public function update(User $user, ProcurementIntent $procurementIntent): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the procurement intent.
     */
    public function delete(User $user, ProcurementIntent $procurementIntent): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the procurement intent.
     */
    public function restore(User $user, ProcurementIntent $procurementIntent): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the procurement intent.
     */
    public function forceDelete(User $user, ProcurementIntent $procurementIntent): bool
    {
        return false;
    }
}
