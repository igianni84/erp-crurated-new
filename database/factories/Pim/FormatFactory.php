<?php

namespace Database\Factories\Pim;

use App\Models\Pim\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Format>
 */
class FormatFactory extends Factory
{
    protected $model = Format::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Standard 750ml', 'Magnum 1500ml', 'Jeroboam 3000ml', 'Half 375ml']),
            'volume_ml' => fake()->randomElement([375, 750, 1500, 3000]),
            'is_standard' => true,
            'allowed_for_liquid_conversion' => false,
        ];
    }

    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Standard 750ml',
            'volume_ml' => 750,
            'is_standard' => true,
        ]);
    }

    public function magnum(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Magnum 1500ml',
            'volume_ml' => 1500,
            'is_standard' => false,
        ]);
    }
}
