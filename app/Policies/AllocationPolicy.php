<?php

namespace App\Policies;

use App\Models\Allocation\Allocation;
use App\Models\User;

/**
 * Policy for Allocation model authorization.
 */
class AllocationPolicy
{
    /**
     * Determine if the user can view any allocations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the allocation.
     */
    public function view(User $user, Allocation $allocation): bool
    {
        return true;
    }

    /**
     * Determine if the user can create allocations.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the allocation.
     */
    public function update(User $user, Allocation $allocation): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the allocation.
     */
    public function delete(User $user, Allocation $allocation): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can activate the allocation.
     *
     * Requires at least Editor role to activate allocations.
     */
    public function activate(User $user, ?Allocation $allocation = null): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can close the allocation.
     */
    public function close(User $user, Allocation $allocation): bool
    {
        return $user->isAdmin();
    }
}
