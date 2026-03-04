<?php

namespace Database\Factories\Pim;

use App\Enums\Pim\AppellationSystem;
use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appellation>
 */
class AppellationFactory extends Factory
{
    protected $model = Appellation::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Appellation',
            'country_id' => Country::factory(),
            'system' => fake()->randomElement(AppellationSystem::cases()),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
