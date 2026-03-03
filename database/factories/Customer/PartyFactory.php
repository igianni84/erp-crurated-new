<?php

namespace Database\Factories\Customer;

use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Models\Customer\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'party_type' => PartyType::LegalEntity,
            'status' => PartyStatus::Active,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'legal_name' => fake()->name(),
            'party_type' => PartyType::Individual,
        ]);
    }

    public function legalEntity(): static
    {
        return $this->state(fn (array $attributes) => [
            'party_type' => PartyType::LegalEntity,
        ]);
    }
}
