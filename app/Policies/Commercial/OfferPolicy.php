<?php

namespace App\Policies\Commercial;

use App\Models\Commercial\Offer;
use App\Models\User;

/**
 * Policy for Offer model authorization.
 */
class OfferPolicy
{
    /**
     * Determine if the user can view any offers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the offer.
     */
    public function view(User $user, Offer $offer): bool
    {
        return true;
    }

    /**
     * Determine if the user can create offers.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the offer.
     */
    public function update(User $user, Offer $offer): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the offer.
     */
    public function delete(User $user, Offer $offer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the offer.
     */
    public function restore(User $user, Offer $offer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the offer.
     */
    public function forceDelete(User $user, Offer $offer): bool
    {
        return false;
    }
}
