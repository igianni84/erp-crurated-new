<?php

namespace App\Policies;

use App\Models\Customer\Customer;
use App\Models\User;

/**
 * Policy for Customer model authorization.
 *
 * Defines access control rules for viewing, updating, and deleting customers,
 * as well as managing operational blocks and payment permissions.
 */
class CustomerPolicy
{
    /**
     * Determine if the user can view any customers.
     *
     * All authenticated users can view the customer list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the customer.
     *
     * All authenticated users can view customer details.
     */
    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    /**
     * Determine if the user can create customers.
     *
     * Requires at least Editor role.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the customer.
     *
     * Requires at least Editor role.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the customer.
     *
     * Requires at least Manager role for deletion due to data integrity concerns.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->role?->hasAtLeast(\App\Enums\UserRole::Manager) ?? false;
    }

    /**
     * Determine if the user can restore a soft-deleted customer.
     *
     * Requires at least Manager role.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $user->role?->hasAtLeast(\App\Enums\UserRole::Manager) ?? false;
    }

    /**
     * Determine if the user can permanently delete the customer.
     *
     * Requires Admin role for permanent deletion.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage operational blocks on the customer.
     *
     * This includes adding and removing blocks (payment, shipment, redemption, trading, compliance).
     * Requires at least Manager role (Compliance/Operations function).
     */
    public function manageBlocks(User $user, Customer $customer): bool
    {
        return $user->canManageOperationalBlocks();
    }

    /**
     * Determine if the user can manage payment permissions on the customer.
     *
     * This includes modifying credit limits, card_allowed, and bank_transfer_allowed.
     * Requires at least Manager role (Finance function).
     */
    public function managePayments(User $user, Customer $customer): bool
    {
        return $user->canManagePaymentPermissions();
    }

    /**
     * Determine if the user can suspend the customer.
     *
     * Requires at least Editor role.
     */
    public function suspend(User $user, Customer $customer): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can activate the customer.
     *
     * Requires at least Editor role.
     */
    public function activate(User $user, Customer $customer): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can manage membership for the customer.
     *
     * Basic membership operations (apply, submit for review) require Editor role.
     * Approval/rejection requires Manager role (checked separately in ViewCustomer).
     */
    public function manageMembership(User $user, Customer $customer): bool
    {
        return $user->canEdit();
    }
}
