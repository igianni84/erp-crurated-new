<?php

namespace Database\Factories\Commercial;

use App\Enums\Commercial\BundlePricingLogic;
use App\Enums\Commercial\BundleStatus;
use App\Models\Commercial\Bundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
{
    protected $model = Bundle::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Bundle',
            'bundle_sku' => fake()->unique()->bothify('BDL-????-####'),
            'pricing_logic' => BundlePricingLogic::SumComponents,
            'status' => BundleStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BundleStatus::Active,
        ]);
    }

    public function fixedPrice(string $price = '99.99'): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_logic' => BundlePricingLogic::FixedPrice,
            'fixed_price' => $price,
        ]);
    }
}
