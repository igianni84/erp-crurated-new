<?php

namespace App\Policies\Finance;

use App\Models\Finance\Payment;
use App\Models\User;

/**
 * Policy for Payment model authorization.
 *
 * This policy provides role-based visibility for Finance module resources.
 */
class PaymentPolicy
{
    /**
     * Determine if the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        return true;
    }

    /**
     * Determine if the user can create payments.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        return true;
    }

    /**
     * Determine if the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Payments should not be deleted (audit trail)
        return false;
    }

    /**
     * Determine if the user can restore the payment.
     */
    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Determine if the user can force delete the payment.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
