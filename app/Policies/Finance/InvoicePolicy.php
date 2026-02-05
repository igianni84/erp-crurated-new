<?php

namespace App\Policies\Finance;

use App\Models\Finance\Invoice;
use App\Models\User;

/**
 * Policy for Invoice model authorization.
 *
 * This policy provides role-based visibility for Finance module resources.
 * Currently allows all operations for authenticated users but can be
 * extended to check specific roles/permissions.
 */
class InvoicePolicy
{
    /**
     * Determine if the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view invoices
        // Future: check for 'finance.view' permission
        return true;
    }

    /**
     * Determine if the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    /**
     * Determine if the user can create invoices.
     */
    public function create(User $user): bool
    {
        // Future: check for 'finance.create' permission
        return true;
    }

    /**
     * Determine if the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Only draft invoices can be updated
        return $invoice->canBeEdited();
    }

    /**
     * Determine if the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Only draft invoices can be deleted
        return $invoice->status === \App\Enums\Finance\InvoiceStatus::Draft;
    }

    /**
     * Determine if the user can restore the invoice.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return true;
    }

    /**
     * Determine if the user can force delete the invoice.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        // Force delete not allowed for invoices (audit trail)
        return false;
    }
}
