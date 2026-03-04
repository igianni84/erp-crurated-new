<?php

namespace Database\Factories\Fulfillment;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Inventory\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'shipping_order_id' => ShippingOrder::factory(),
            'carrier' => fake()->randomElement(['DHL', 'FedEx', 'UPS', 'TNT']),
            'tracking_number' => fake()->unique()->numerify('TRK##########'),
            'status' => ShipmentStatus::Preparing,
            'shipped_bottle_serials' => [],
            'origin_warehouse_id' => Location::factory(),
            'destination_address' => fake()->address(),
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now(),
        ]);
    }
}
