<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Models\Pim\SellableSku;
use App\Models\Procurement\Inbound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inbound>
 */
class InboundFactory extends Factory
{
    protected $model = Inbound::class;

    public function definition(): array
    {
        return [
            'warehouse' => 'main_warehouse',
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => SellableSku::factory(),
            'quantity' => fake()->numberBetween(6, 60),
            'packaging' => InboundPackaging::Cases,
            'ownership_flag' => OwnershipFlag::Owned,
            'received_date' => now(),
            'serialization_required' => false,
            'status' => InboundStatus::Recorded,
            'handed_to_module_b' => false,
        ];
    }

    public function routed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InboundStatus::Routed,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InboundStatus::Completed,
        ]);
    }

    public function pendingOwnership(): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_flag' => OwnershipFlag::Pending,
        ]);
    }

    public function linked(?ProcurementIntent $intent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'procurement_intent_id' => $intent?->id ?? ProcurementIntent::factory(),
        ]);
    }
}
