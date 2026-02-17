<?php

namespace App\Observers\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Models\Customer\Customer;
use App\Models\Customer\PaymentPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Observer for Customer model.
 *
 * Handles validation rules when Customer status changes.
 * Also handles auto-creation of related records when status changes.
 */
class CustomerObserver
{
    /**
     * Handle the Customer "updating" event.
     *
     * Validates that active customers have at least one billing address.
     */
    public function updating(Customer $customer): void
    {
        // Check if status is being changed to Active
        if (! $customer->isDirty('status')) {
            return;
        }

        $newStatus = $customer->status;

        // If becoming active, require at least one billing address
        if ($newStatus === CustomerStatus::Active) {
            if (! $customer->hasBillingAddress()) {
                throw new ValidationException(
                    Validator::make([], []),
                    new JsonResponse([
                        'message' => 'A billing address is required to activate a customer.',
                        'errors' => [
                            'status' => ['A billing address is required to activate a customer.'],
                        ],
                    ], 422)
                );
            }
        }
    }

    /**
     * Handle the Customer "updated" event.
     *
     * Creates PaymentPermission with defaults when Customer becomes active.
     */
    public function updated(Customer $customer): void
    {
        // Check if status was changed to Active
        if (! $customer->wasChanged('status')) {
            return;
        }

        $newStatus = $customer->status;

        // If just became active, create PaymentPermission with defaults
        if ($newStatus === CustomerStatus::Active) {
            // Only create if doesn't already exist
            if (! $customer->hasPaymentPermission()) {
                PaymentPermission::create([
                    'customer_id' => $customer->id,
                    'card_allowed' => true,
                    'bank_transfer_allowed' => false,
                    'credit_limit' => null,
                ]);
            }
        }
    }
}
