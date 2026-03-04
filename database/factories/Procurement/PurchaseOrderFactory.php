<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Customer\Party;
use App\Models\Pim\SellableSku;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'procurement_intent_id' => ProcurementIntent::factory(),
            'supplier_party_id' => Party::factory(),
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => SellableSku::factory(),
            'quantity' => fake()->numberBetween(6, 60),
            'unit_cost' => fake()->randomFloat(2, 50, 500),
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'expected_delivery_start' => now()->addDays(14),
            'expected_delivery_end' => now()->addDays(30),
            'destination_warehouse' => 'main_warehouse',
            'status' => PurchaseOrderStatus::Draft,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Sent,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Closed,
            'confirmed_at' => now()->subDays(30),
        ]);
    }
}
