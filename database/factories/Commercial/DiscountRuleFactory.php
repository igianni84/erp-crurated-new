<?php

namespace Database\Factories\Commercial;

use App\Enums\Commercial\DiscountRuleStatus;
use App\Enums\Commercial\DiscountRuleType;
use App\Models\Commercial\DiscountRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountRule>
 */
class DiscountRuleFactory extends Factory
{
    protected $model = DiscountRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Discount',
            'rule_type' => DiscountRuleType::Percentage,
            'logic_definition' => ['value' => 15],
            'status' => DiscountRuleStatus::Active,
        ];
    }

    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => DiscountRuleType::FixedAmount,
            'logic_definition' => ['value' => 25],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscountRuleStatus::Inactive,
        ]);
    }
}
