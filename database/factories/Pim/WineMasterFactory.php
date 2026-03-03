<?php

namespace Database\Factories\Pim;

use App\Models\Pim\WineMaster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WineMaster>
 */
class WineMasterFactory extends Factory
{
    protected $model = WineMaster::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'producer' => fake()->company(),
            'appellation' => fake()->city(),
            'country' => fake()->country(),
            'region' => fake()->state(),
        ];
    }
}
