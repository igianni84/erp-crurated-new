<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\Inbound;
use App\Models\User;

/**
 * Policy for Inbound model authorization.
 */
class InboundPolicy
{
    /**
     * Determine if the user can view any inbounds.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the inbound.
     */
    public function view(User $user, Inbound $inbound): bool
    {
        return true;
    }

    /**
     * Determine if the user can create inbounds.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the inbound.
     */
    public function update(User $user, Inbound $inbound): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the inbound.
     */
    public function delete(User $user, Inbound $inbound): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the inbound.
     */
    public function restore(User $user, Inbound $inbound): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the inbound.
     */
    public function forceDelete(User $user, Inbound $inbound): bool
    {
        return false;
    }
}
