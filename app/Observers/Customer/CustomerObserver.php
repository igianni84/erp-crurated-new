<?php

namespace App\Observers\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Models\Customer\Customer;

/**
 * Observer for Customer model.
 *
 * Handles validation rules when Customer status changes.
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
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], []),
                    new \Illuminate\Http\JsonResponse([
                        'message' => 'A billing address is required to activate a customer.',
                        'errors' => [
                            'status' => ['A billing address is required to activate a customer.'],
                        ],
                    ], 422)
                );
            }
        }
    }
}
