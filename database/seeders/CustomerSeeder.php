<?php

namespace Database\Seeders;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Models\Customer\Customer;
use App\Models\Customer\Party;
use App\Models\Customer\PartyRole;
use Illuminate\Database\Seeder;

/**
 * CustomerSeeder - Creates realistic wine collector customer profiles
 *
 * Each Customer gets a linked Party (Individual) with a Customer role.
 * PartySeeder creates supply-chain parties (Producer/Supplier/Partner).
 */
class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            // Italian collectors (B2C)
            [
                'name' => 'Marco Bianchi',
                'email' => 'marco.bianchi@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            [
                'name' => 'Giulia Rossi',
                'email' => 'giulia.rossi@outlook.it',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            [
                'name' => 'Alessandro Ferrari',
                'email' => 'a.ferrari@libero.it',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2B,
            ],
            [
                'name' => 'Francesca Moretti',
                'email' => 'francesca.moretti@yahoo.it',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            [
                'name' => 'Luca Romano',
                'email' => 'luca.romano@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            // International collectors
            [
                'name' => 'James Thompson',
                'email' => 'james.thompson@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2B,
            ],
            [
                'name' => 'Sophie Dubois',
                'email' => 'sophie.dubois@orange.fr',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            [
                'name' => 'Hans Mueller',
                'email' => 'hans.mueller@web.de',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            [
                'name' => 'Elena Petrova',
                'email' => 'elena.petrova@mail.ru',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            [
                'name' => 'William Chen',
                'email' => 'william.chen@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Active,
                'customer_type' => CustomerType::B2C,
            ],
            // Suspended customer
            [
                'name' => 'Roberto Esposito',
                'email' => 'roberto.esposito@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => CustomerStatus::Suspended,
                'customer_type' => CustomerType::B2C,
            ],
            // Closed customer
            [
                'name' => 'Antonio Colombo',
                'email' => 'antonio.colombo@alice.it',
                'stripe_customer_id' => null,
                'status' => CustomerStatus::Closed,
                'customer_type' => CustomerType::B2C,
            ],
        ];

        foreach ($customers as $customerData) {
            // Create a Party for this customer (Individual type)
            $party = Party::firstOrCreate(
                ['legal_name' => $customerData['name'], 'party_type' => PartyType::Individual],
                [
                    'status' => PartyStatus::Active,
                ]
            );

            // Create Customer BEFORE PartyRole to prevent the PartyRoleObserver
            // from auto-creating a Customer without name/email fields.
            $customer = Customer::firstOrCreate(
                ['email' => $customerData['email']],
                array_merge($customerData, ['party_id' => $party->id])
            );

            // If customer existed but had no party, link it now
            if ($customer->party_id === null) {
                $customer->update(['party_id' => $party->id]);
            }

            // Ensure the Party has a Customer role (observer will skip since Customer exists)
            PartyRole::firstOrCreate([
                'party_id' => $party->id,
                'role' => PartyRoleType::Customer,
            ]);
        }
    }
}
