<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\InventoryCase;
use App\Models\User;

/**
 * Policy for InventoryCase model authorization.
 *
 * Read-only policy — inventory cases are managed by system processes.
 */
class InventoryCasePolicy
{
    /**
     * Determine if the user can view any inventory cases.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the inventory case.
     */
    public function view(User $user, InventoryCase $inventoryCase): bool
    {
        return true;
    }

    /**
     * Determine if the user can create inventory cases.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can update the inventory case.
     */
    public function update(User $user, InventoryCase $inventoryCase): bool
    {
        return false;
    }

    /**
     * Determine if the user can delete the inventory case.
     */
    public function delete(User $user, InventoryCase $inventoryCase): bool
    {
        return false;
    }

    /**
     * Determine if the user can restore the inventory case.
     */
    public function restore(User $user, InventoryCase $inventoryCase): bool
    {
        return false;
    }

    /**
     * Determine if the user can permanently delete the inventory case.
     */
    public function forceDelete(User $user, InventoryCase $inventoryCase): bool
    {
        return false;
    }
}
