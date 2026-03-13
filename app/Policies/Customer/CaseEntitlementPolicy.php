<?php

namespace App\Policies\Customer;

use App\Models\Allocation\CaseEntitlement;
use App\Models\User;

/**
 * Policy for CaseEntitlement model authorization.
 *
 * Standard CRUD policy — editors can create/update, admins can delete/restore.
 */
class CaseEntitlementPolicy
{
    /**
     * Determine if the user can view any case entitlements.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the case entitlement.
     */
    public function view(User $user, CaseEntitlement $caseEntitlement): bool
    {
        return true;
    }

    /**
     * Determine if the user can create case entitlements.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the case entitlement.
     */
    public function update(User $user, CaseEntitlement $caseEntitlement): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the case entitlement.
     */
    public function delete(User $user, CaseEntitlement $caseEntitlement): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the case entitlement.
     */
    public function restore(User $user, CaseEntitlement $caseEntitlement): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the case entitlement.
     */
    public function forceDelete(User $user, CaseEntitlement $caseEntitlement): bool
    {
        return false;
    }
}
