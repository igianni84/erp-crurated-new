<?php

namespace Database\Factories\Customer;

use App\Enums\Customer\CustomerUserStatus;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerUser>
 */
class CustomerUserFactory extends Factory
{
    protected $model = CustomerUser::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'status' => CustomerUserStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerUserStatus::Suspended,
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerUserStatus::Deactivated,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
