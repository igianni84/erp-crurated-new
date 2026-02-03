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
        return true;
    }

    /**
     * Determine if the user can update the allocation.
     */
    public function update(User $user, Allocation $allocation): bool
    {
        return true;
    }

    /**
     * Determine if the user can delete the allocation.
     */
    public function delete(User $user, Allocation $allocation): bool
    {
        return true;
    }

    /**
     * Determine if the user can activate the allocation.
     *
     * This permission controls whether the "Create and Activate" button
     * is visible in the allocation creation wizard.
     *
     * Currently allows all authenticated users. Can be refined with
     * role-based checks in future iterations.
     */
    public function activate(User $user, ?Allocation $allocation = null): bool
    {
        // Allow all authenticated users to activate allocations
        // This can be refined with role-based permissions later
        // e.g., return $user->hasRole('admin') || $user->hasRole('operator');
        return true;
    }

    /**
     * Determine if the user can close the allocation.
     */
    public function close(User $user, Allocation $allocation): bool
    {
        return true;
    }
}
