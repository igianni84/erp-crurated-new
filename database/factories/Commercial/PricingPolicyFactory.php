<?php

namespace Database\Factories\Commercial;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\PricingPolicyInputSource;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Models\Commercial\PricingPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingPolicy>
 */
class PricingPolicyFactory extends Factory
{
    protected $model = PricingPolicy::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Policy',
            'policy_type' => PricingPolicyType::CostPlusMargin,
            'input_source' => PricingPolicyInputSource::Cost,
            'logic_definition' => ['margin_percentage' => 20],
            'execution_cadence' => ExecutionCadence::Manual,
            'status' => PricingPolicyStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PricingPolicyStatus::Active,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PricingPolicyStatus::Paused,
        ]);
    }
}
