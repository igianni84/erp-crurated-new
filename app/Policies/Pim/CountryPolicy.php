<?php

namespace App\Policies\Pim;

use App\Models\Pim\Country;
use App\Models\User;

/**
 * Policy for Country model authorization.
 */
class CountryPolicy
{
    /**
     * Determine if the user can view any countries.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the country.
     */
    public function view(User $user, Country $country): bool
    {
        return true;
    }

    /**
     * Determine if the user can create countries.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the country.
     */
    public function update(User $user, Country $country): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the country.
     */
    public function delete(User $user, Country $country): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the country.
     */
    public function restore(User $user, Country $country): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the country.
     */
    public function forceDelete(User $user, Country $country): bool
    {
        return false;
    }
}
