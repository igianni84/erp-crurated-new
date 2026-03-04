<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Models\Pim\LiquidProduct;
use App\Models\Procurement\BottlingInstruction;
use App\Models\Procurement\ProcurementIntent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BottlingInstruction>
 */
class BottlingInstructionFactory extends Factory
{
    protected $model = BottlingInstruction::class;

    public function definition(): array
    {
        return [
            'procurement_intent_id' => ProcurementIntent::factory(),
            'liquid_product_id' => LiquidProduct::factory(),
            'bottle_equivalents' => fake()->numberBetween(6, 120),
            'allowed_formats' => ['750ml'],
            'allowed_case_configurations' => ['6x750ml'],
            'bottling_deadline' => now()->addMonths(3),
            'preference_status' => BottlingPreferenceStatus::Pending,
            'personalised_bottling_required' => false,
            'early_binding_required' => false,
            'status' => BottlingInstructionStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BottlingInstructionStatus::Active,
        ]);
    }
}
