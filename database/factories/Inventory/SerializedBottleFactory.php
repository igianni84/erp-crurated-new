<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\OwnershipType;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerializedBottle>
 */
class SerializedBottleFactory extends Factory
{
    protected $model = SerializedBottle::class;

    public function definition(): array
    {
        return [
            'serial_number' => 'SB-'.fake()->unique()->numerify('############'),
            'wine_variant_id' => WineVariant::factory(),
            'format_id' => Format::factory(),
            'allocation_id' => Allocation::factory(),
            'inbound_batch_id' => InboundBatch::factory(),
            'current_location_id' => Location::factory(),
            'ownership_type' => OwnershipType::CururatedOwned,
            'state' => BottleState::Stored,
            'serialized_at' => now(),
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => BottleState::Shipped,
        ]);
    }
}
