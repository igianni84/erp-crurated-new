<?php

namespace Database\Factories\Pim;

use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    protected $model = Producer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Wines',
            'country_id' => Country::factory(),
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
