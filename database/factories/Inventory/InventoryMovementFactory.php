<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'movement_type' => MovementType::InternalTransfer,
            'trigger' => MovementTrigger::ErpOperator,
            'source_location_id' => Location::factory(),
            'destination_location_id' => Location::factory(),
            'custody_changed' => false,
            'reason' => fake()->sentence(),
            'executed_at' => now(),
        ];
    }
}
