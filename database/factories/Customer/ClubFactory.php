<?php

namespace Database\Factories\Customer;

use App\Enums\Customer\ClubStatus;
use App\Models\Customer\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    protected $model = Club::class;

    public function definition(): array
    {
        return [
            'partner_name' => fake()->company().' Wine Club',
            'status' => ClubStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClubStatus::Active,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClubStatus::Suspended,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClubStatus::Ended,
        ]);
    }
}
