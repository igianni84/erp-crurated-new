<?php

namespace App\Policies\Fulfillment;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Models\Fulfillment\Shipment;
use App\Models\User;

/**
 * Policy for Shipment model authorization.
 *
 * Custom logic — editors can create, updates blocked once delivered,
 * admins can delete/restore.
 */
class ShipmentPolicy
{
    /**
     * Determine if the user can view any shipments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the shipment.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        return true;
    }

    /**
     * Determine if the user can create shipments.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the shipment.
     *
     * Editing is blocked once the shipment reaches the Delivered terminal state.
     */
    public function update(User $user, Shipment $shipment): bool
    {
        return $user->canEdit()
            && $shipment->status !== ShipmentStatus::Delivered;
    }

    /**
     * Determine if the user can delete the shipment.
     */
    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the shipment.
     */
    public function restore(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the shipment.
     */
    public function forceDelete(User $user, Shipment $shipment): bool
    {
        return false;
    }
}
