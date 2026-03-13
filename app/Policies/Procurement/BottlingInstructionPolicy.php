<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\BottlingInstruction;
use App\Models\User;

/**
 * Policy for BottlingInstruction model authorization.
 */
class BottlingInstructionPolicy
{
    /**
     * Determine if the user can view any bottling instructions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the bottling instruction.
     */
    public function view(User $user, BottlingInstruction $bottlingInstruction): bool
    {
        return true;
    }

    /**
     * Determine if the user can create bottling instructions.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the bottling instruction.
     */
    public function update(User $user, BottlingInstruction $bottlingInstruction): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the bottling instruction.
     */
    public function delete(User $user, BottlingInstruction $bottlingInstruction): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the bottling instruction.
     */
    public function restore(User $user, BottlingInstruction $bottlingInstruction): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the bottling instruction.
     */
    public function forceDelete(User $user, BottlingInstruction $bottlingInstruction): bool
    {
        return false;
    }
}
