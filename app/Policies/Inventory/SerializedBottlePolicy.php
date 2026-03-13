<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\SerializedBottle;
use App\Models\User;

/**
 * Policy for SerializedBottle model authorization.
 *
 * Read-only policy — serialized bottles are managed by system processes.
 */
class SerializedBottlePolicy
{
    /**
     * Determine if the user can view any serialized bottles.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the serialized bottle.
     */
    public function view(User $user, SerializedBottle $serializedBottle): bool
    {
        return true;
    }

    /**
     * Determine if the user can create serialized bottles.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can update the serialized bottle.
     */
    public function update(User $user, SerializedBottle $serializedBottle): bool
    {
        return false;
    }

    /**
     * Determine if the user can delete the serialized bottle.
     */
    public function delete(User $user, SerializedBottle $serializedBottle): bool
    {
        return false;
    }

    /**
     * Determine if the user can restore the serialized bottle.
     */
    public function restore(User $user, SerializedBottle $serializedBottle): bool
    {
        return false;
    }

    /**
     * Determine if the user can permanently delete the serialized bottle.
     */
    public function forceDelete(User $user, SerializedBottle $serializedBottle): bool
    {
        return false;
    }
}
