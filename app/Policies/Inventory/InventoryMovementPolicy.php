<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\User;

/**
 * Policy for InventoryMovement model authorization.
 *
 * Read-only policy — inventory movements are append-only system records.
 */
class InventoryMovementPolicy
{
    /**
     * Determine if the user can view any inventory movements.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the inventory movement.
     */
    public function view(User $user, InventoryMovement $inventoryMovement): bool
    {
        return true;
    }

    /**
     * Determine if the user can create inventory movements.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can update the inventory movement.
     */
    public function update(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    /**
     * Determine if the user can delete the inventory movement.
     */
    public function delete(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    /**
     * Determine if the user can restore the inventory movement.
     */
    public function restore(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    /**
     * Determine if the user can permanently delete the inventory movement.
     */
    public function forceDelete(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }
}
