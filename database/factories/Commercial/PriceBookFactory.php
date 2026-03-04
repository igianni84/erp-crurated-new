<?php

namespace Database\Factories\Commercial;

use App\Enums\Commercial\PriceBookStatus;
use App\Models\Commercial\PriceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceBook>
 */
class PriceBookFactory extends Factory
{
    protected $model = PriceBook::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Price Book',
            'market' => fake()->randomElement(['EU', 'UK', 'US', 'APAC']),
            'currency' => 'EUR',
            'valid_from' => now()->toDateString(),
            'status' => PriceBookStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PriceBookStatus::Active,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PriceBookStatus::Expired,
            'valid_from' => now()->subYear()->toDateString(),
            'valid_to' => now()->subMonth()->toDateString(),
        ]);
    }
}
