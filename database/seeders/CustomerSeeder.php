<?php

namespace Database\Seeders;

use App\Models\Customer\Customer;
use Illuminate\Database\Seeder;

/**
 * CustomerSeeder - Creates realistic wine collector customer profiles
 */
class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            // Italian collectors
            [
                'name' => 'Marco Bianchi',
                'email' => 'marco.bianchi@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'Giulia Rossi',
                'email' => 'giulia.rossi@outlook.it',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'Alessandro Ferrari',
                'email' => 'a.ferrari@libero.it',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'Francesca Moretti',
                'email' => 'francesca.moretti@yahoo.it',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'Luca Romano',
                'email' => 'luca.romano@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            // International collectors
            [
                'name' => 'James Thompson',
                'email' => 'james.thompson@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'Sophie Dubois',
                'email' => 'sophie.dubois@orange.fr',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'Hans Mueller',
                'email' => 'hans.mueller@web.de',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'Elena Petrova',
                'email' => 'elena.petrova@mail.ru',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            [
                'name' => 'William Chen',
                'email' => 'william.chen@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_ACTIVE,
            ],
            // Suspended customer
            [
                'name' => 'Roberto Esposito',
                'email' => 'roberto.esposito@gmail.com',
                'stripe_customer_id' => 'cus_'.fake()->regexify('[A-Za-z0-9]{14}'),
                'status' => Customer::STATUS_SUSPENDED,
            ],
            // Closed customer
            [
                'name' => 'Antonio Colombo',
                'email' => 'antonio.colombo@alice.it',
                'stripe_customer_id' => null,
                'status' => Customer::STATUS_CLOSED,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate(
                ['email' => $customerData['email']],
                $customerData
            );
        }
    }
}
