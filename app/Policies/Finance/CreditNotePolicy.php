<?php

namespace App\Policies\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Models\Finance\CreditNote;
use App\Models\User;

/**
 * Policy for CreditNote model authorization.
 *
 * This policy provides role-based visibility for Finance module resources.
 */
class CreditNotePolicy
{
    /**
     * Determine if the user can view any credit notes.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the credit note.
     */
    public function view(User $user, CreditNote $creditNote): bool
    {
        return true;
    }

    /**
     * Determine if the user can create credit notes.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the credit note.
     */
    public function update(User $user, CreditNote $creditNote): bool
    {
        return $user->canEdit() && $creditNote->status === CreditNoteStatus::Draft;
    }

    /**
     * Determine if the user can delete the credit note.
     */
    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $user->isAdmin() && $creditNote->status === CreditNoteStatus::Draft;
    }

    /**
     * Determine if the user can restore the credit note.
     */
    public function restore(User $user, CreditNote $creditNote): bool
    {
        return false;
    }

    /**
     * Determine if the user can force delete the credit note.
     */
    public function forceDelete(User $user, CreditNote $creditNote): bool
    {
        return false;
    }
}
