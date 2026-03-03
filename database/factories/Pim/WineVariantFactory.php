<?php

namespace Database\Factories\Pim;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WineVariant>
 */
class WineVariantFactory extends Factory
{
    protected $model = WineVariant::class;

    public function definition(): array
    {
        return [
            'wine_master_id' => WineMaster::factory(),
            'vintage_year' => fake()->numberBetween(2000, 2023),
            'alcohol_percentage' => fake()->randomFloat(2, 11.0, 15.5),
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'data_source' => DataSource::Manual,
            'critic_scores' => [],
            'production_notes' => [],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => ProductLifecycleStatus::Draft,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => ProductLifecycleStatus::Published,
        ]);
    }
}
