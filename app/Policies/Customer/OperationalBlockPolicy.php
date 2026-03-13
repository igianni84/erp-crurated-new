<?php

namespace App\Policies\Customer;

use App\Models\Customer\OperationalBlock;
use App\Models\User;

/**
 * Policy for OperationalBlock model authorization.
 *
 * Custom logic — managing operational blocks requires admin-level privileges
 * via canManageOperationalBlocks() (Manager+).
 */
class OperationalBlockPolicy
{
    /**
     * Determine if the user can view any operational blocks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the operational block.
     */
    public function view(User $user, OperationalBlock $operationalBlock): bool
    {
        return true;
    }

    /**
     * Determine if the user can create operational blocks.
     */
    public function create(User $user): bool
    {
        return $user->canManageOperationalBlocks();
    }

    /**
     * Determine if the user can update the operational block.
     */
    public function update(User $user, OperationalBlock $operationalBlock): bool
    {
        return $user->canManageOperationalBlocks();
    }

    /**
     * Determine if the user can delete the operational block.
     */
    public function delete(User $user, OperationalBlock $operationalBlock): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the operational block.
     */
    public function restore(User $user, OperationalBlock $operationalBlock): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the operational block.
     */
    public function forceDelete(User $user, OperationalBlock $operationalBlock): bool
    {
        return false;
    }
}
