<?php

namespace Database\Factories\Pim;

use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellableSku>
 */
class SellableSkuFactory extends Factory
{
    protected $model = SellableSku::class;

    public function definition(): array
    {
        return [
            'wine_variant_id' => WineVariant::factory(),
            'format_id' => Format::factory(),
            'case_configuration_id' => CaseConfiguration::factory(),
            'lifecycle_status' => 'draft',
            'is_intrinsic' => false,
            'is_producer_original' => true,
            'is_verified' => false,
            'source' => 'manual',
            'is_composite' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'active',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'draft',
        ]);
    }
}
