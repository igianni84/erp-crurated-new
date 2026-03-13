<?php

namespace App\Policies\Pim;

use App\Models\Pim\SellableSku;
use App\Models\User;

/**
 * Policy for SellableSku model authorization.
 */
class SellableSkuPolicy
{
    /**
     * Determine if the user can view any sellable SKUs.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the sellable SKU.
     */
    public function view(User $user, SellableSku $sellableSku): bool
    {
        return true;
    }

    /**
     * Determine if the user can create sellable SKUs.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the sellable SKU.
     */
    public function update(User $user, SellableSku $sellableSku): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the sellable SKU.
     */
    public function delete(User $user, SellableSku $sellableSku): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the sellable SKU.
     */
    public function restore(User $user, SellableSku $sellableSku): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the sellable SKU.
     */
    public function forceDelete(User $user, SellableSku $sellableSku): bool
    {
        return false;
    }
}
