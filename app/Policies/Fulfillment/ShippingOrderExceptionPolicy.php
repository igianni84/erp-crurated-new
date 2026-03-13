<?php

namespace App\Policies\Fulfillment;

use App\Models\Fulfillment\ShippingOrderException;
use App\Models\User;

/**
 * Policy for ShippingOrderException model authorization.
 *
 * Read-only policy — shipping order exceptions are system-generated records.
 */
class ShippingOrderExceptionPolicy
{
    /**
     * Determine if the user can view any shipping order exceptions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the shipping order exception.
     */
    public function view(User $user, ShippingOrderException $shippingOrderException): bool
    {
        return true;
    }

    /**
     * Determine if the user can create shipping order exceptions.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine if the user can update the shipping order exception.
     */
    public function update(User $user, ShippingOrderException $shippingOrderException): bool
    {
        return false;
    }

    /**
     * Determine if the user can delete the shipping order exception.
     */
    public function delete(User $user, ShippingOrderException $shippingOrderException): bool
    {
        return false;
    }

    /**
     * Determine if the user can restore the shipping order exception.
     */
    public function restore(User $user, ShippingOrderException $shippingOrderException): bool
    {
        return false;
    }

    /**
     * Determine if the user can permanently delete the shipping order exception.
     */
    public function forceDelete(User $user, ShippingOrderException $shippingOrderException): bool
    {
        return false;
    }
}
