<?php

namespace App\Policies\Commercial;

use App\Models\Commercial\PriceBook;
use App\Models\User;

/**
 * Policy for PriceBook model authorization.
 */
class PriceBookPolicy
{
    /**
     * Determine if the user can view any price books.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the price book.
     */
    public function view(User $user, PriceBook $priceBook): bool
    {
        return true;
    }

    /**
     * Determine if the user can create price books.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the price book.
     */
    public function update(User $user, PriceBook $priceBook): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can delete the price book.
     */
    public function delete(User $user, PriceBook $priceBook): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the price book.
     */
    public function restore(User $user, PriceBook $priceBook): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the price book.
     */
    public function forceDelete(User $user, PriceBook $priceBook): bool
    {
        return false;
    }
}
