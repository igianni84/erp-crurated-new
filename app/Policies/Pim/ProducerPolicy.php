<?php

namespace App\Policies\Pim;

use App\Models\Pim\Producer;
use App\Models\User;

/**
 * Policy for Producer model authorization.
 */
class ProducerPolicy
{
    /**
     * Determine if the user can view any producers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the producer.
     */
    public function view(User $user, Producer $producer): bool
    {
        return true;
    }

    /**
     * Determine if the user can create producers.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the producer.
     */
    public function update(User $user, Producer $producer): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the producer.
     */
    public function delete(User $user, Producer $producer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the producer.
     */
    public function restore(User $user, Producer $producer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the producer.
     */
    public function forceDelete(User $user, Producer $producer): bool
    {
        return false;
    }
}
