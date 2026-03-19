<?php

namespace Database\Seeders;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerUser;
use Illuminate\Database\Seeder;

class CustomerUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create customer users for existing active customers
        $customers = Customer::query()
            ->where('status', 'active')
            ->limit(5)
            ->get();

        foreach ($customers as $customer) {
            CustomerUser::factory()->create([
                'customer_id' => $customer->id,
            ]);
        }

        // If no active customers exist, create 3 with customer users
        if ($customers->isEmpty()) {
            CustomerUser::factory()
                ->count(3)
                ->create();
        }
    }
}
