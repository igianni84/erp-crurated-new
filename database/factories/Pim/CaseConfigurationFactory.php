<?php

namespace Database\Factories\Pim;

use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseConfiguration>
 */
class CaseConfigurationFactory extends Factory
{
    protected $model = CaseConfiguration::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['6-pack OWC', '12-pack OC', 'Single Bottle']),
            'format_id' => Format::factory(),
            'bottles_per_case' => fake()->randomElement([1, 3, 6, 12]),
            'case_type' => fake()->randomElement(['owc', 'oc', 'none']),
            'is_original_from_producer' => fake()->boolean(),
            'is_breakable' => true,
        ];
    }
}
