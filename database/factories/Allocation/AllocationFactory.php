<?php

namespace Database\Factories\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Models\Allocation\Allocation;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allocation>
 */
class AllocationFactory extends Factory
{
    protected $model = Allocation::class;

    public function definition(): array
    {
        return [
            'wine_variant_id' => WineVariant::factory(),
            'format_id' => Format::factory(),
            'source_type' => AllocationSourceType::ProducerAllocation,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => fake()->numberBetween(6, 120),
            'sold_quantity' => 0,
            'expected_availability_start' => now(),
            'expected_availability_end' => now()->addMonths(6),
            'serialization_required' => false,
            'status' => AllocationStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AllocationStatus::Active,
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_quantity' => 10,
            'sold_quantity' => 10,
            'status' => AllocationStatus::Exhausted,
        ]);
    }
}
