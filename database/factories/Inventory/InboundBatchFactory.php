<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundBatch>
 */
class InboundBatchFactory extends Factory
{
    protected $model = InboundBatch::class;

    public function definition(): array
    {
        $quantityExpected = fake()->numberBetween(6, 120);

        return [
            'source_type' => 'producer',
            'product_reference_type' => WineVariant::class,
            'product_reference_id' => WineVariant::factory(),
            'allocation_id' => Allocation::factory(),
            'quantity_expected' => $quantityExpected,
            'quantity_received' => $quantityExpected,
            'packaging_type' => 'bottles',
            'receiving_location_id' => Location::factory(),
            'ownership_type' => OwnershipType::CururatedOwned,
            'received_date' => now()->toDateString(),
            'serialization_status' => InboundBatchStatus::PendingSerialization,
        ];
    }

    public function fullySerialized(): static
    {
        return $this->state(fn (array $attributes) => [
            'serialization_status' => InboundBatchStatus::FullySerialized,
        ]);
    }

    public function withDiscrepancy(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => max(0, ($attributes['quantity_expected'] ?? 12) - 2),
            'serialization_status' => InboundBatchStatus::Discrepancy,
        ]);
    }
}
