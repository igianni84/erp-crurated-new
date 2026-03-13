<?php

namespace App\Policies\Pim;

use App\Models\Pim\Format;
use App\Models\User;

/**
 * Policy for Format model authorization.
 */
class FormatPolicy
{
    /**
     * Determine if the user can view any formats.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the format.
     */
    public function view(User $user, Format $format): bool
    {
        return true;
    }

    /**
     * Determine if the user can create formats.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the format.
     */
    public function update(User $user, Format $format): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the format.
     */
    public function delete(User $user, Format $format): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the format.
     */
    public function restore(User $user, Format $format): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the format.
     */
    public function forceDelete(User $user, Format $format): bool
    {
        return false;
    }
}
