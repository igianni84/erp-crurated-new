<?php

namespace App\Policies\Procurement;

use App\Models\Procurement\PurchaseOrder;
use App\Models\User;

/**
 * Policy for PurchaseOrder model authorization.
 */
class PurchaseOrderPolicy
{
    /**
     * Determine if the user can view any purchase orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the purchase order.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    /**
     * Determine if the user can create purchase orders.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the purchase order.
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the purchase order.
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the purchase order.
     */
    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the purchase order.
     */
    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }
}
