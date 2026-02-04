<?php

namespace App\Observers\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Enums\Customer\PartyRoleType;
use App\Models\Customer\Customer;
use App\Models\Customer\PartyRole;

/**
 * Observer for PartyRole model.
 *
 * Handles automatic Customer creation when a Party receives the customer role.
 */
class PartyRoleObserver
{
    /**
     * Handle the PartyRole "created" event.
     *
     * When a customer role is added to a Party, automatically create a Customer record.
     */
    public function created(PartyRole $partyRole): void
    {
        // Only proceed if the role is customer
        if ($partyRole->role !== PartyRoleType::Customer) {
            return;
        }

        // Check if a Customer already exists for this Party
        $existingCustomer = Customer::where('party_id', $partyRole->party_id)->first();

        if ($existingCustomer) {
            return;
        }

        // Create a new Customer for this Party
        Customer::create([
            'party_id' => $partyRole->party_id,
            'customer_type' => CustomerType::B2C, // Default type
            'status' => CustomerStatus::Prospect, // New customers start as prospects
        ]);
    }

    /**
     * Handle the PartyRole "deleted" event.
     *
     * Note: We don't automatically delete the Customer when the role is removed,
     * as the Customer may have transaction history that needs to be preserved.
     * The Customer status should be managed separately.
     */
    public function deleted(PartyRole $partyRole): void
    {
        // Intentionally left empty - Customer records are preserved
    }
}
