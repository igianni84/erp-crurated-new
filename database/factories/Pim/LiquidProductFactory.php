<?php

namespace Database\Factories\Pim;

use App\Models\Pim\LiquidProduct;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiquidProduct>
 */
class LiquidProductFactory extends Factory
{
    protected $model = LiquidProduct::class;

    public function definition(): array
    {
        return [
            'wine_variant_id' => WineVariant::factory(),
            'allowed_equivalent_units' => ['bottle' => '1', 'magnum' => '2'],
            'allowed_final_formats' => ['750ml', '1500ml'],
            'allowed_case_configurations' => ['6x750ml'],
            'bottling_constraints' => null,
            'serialization_required' => true,
            'lifecycle_status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_status' => 'published',
        ]);
    }
}
