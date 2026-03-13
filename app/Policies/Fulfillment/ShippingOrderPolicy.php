<?php

namespace App\Policies\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\User;

/**
 * Policy for ShippingOrder model authorization.
 *
 * Custom logic — editors can create, updates blocked once confirmed or completed,
 * admins can delete/restore.
 */
class ShippingOrderPolicy
{
    /**
     * Determine if the user can view any shipping orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the shipping order.
     */
    public function view(User $user, ShippingOrder $shippingOrder): bool
    {
        return true;
    }

    /**
     * Determine if the user can create shipping orders.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the shipping order.
     *
     * Editing is blocked once the order reaches Completed or Cancelled terminal states.
     */
    public function update(User $user, ShippingOrder $shippingOrder): bool
    {
        return $user->canEdit()
            && $shippingOrder->status !== ShippingOrderStatus::Completed
            && $shippingOrder->status !== ShippingOrderStatus::Cancelled;
    }

    /**
     * Determine if the user can delete the shipping order.
     */
    public function delete(User $user, ShippingOrder $shippingOrder): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the shipping order.
     */
    public function restore(User $user, ShippingOrder $shippingOrder): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the shipping order.
     */
    public function forceDelete(User $user, ShippingOrder $shippingOrder): bool
    {
        return false;
    }
}
