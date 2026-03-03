<?php

namespace Database\Factories\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Models\Customer\Customer;
use App\Models\Customer\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'customer_type' => CustomerType::B2C,
            'status' => CustomerStatus::Prospect,
        ];
    }

    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Prospect,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Active,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Suspended,
        ]);
    }
}
