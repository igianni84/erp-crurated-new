<?php

namespace Database\Factories\Fulfillment;

use App\Enums\Fulfillment\PackagingPreference;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Customer\Customer;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Inventory\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingOrder>
 */
class ShippingOrderFactory extends Factory
{
    protected $model = ShippingOrder::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'source_warehouse_id' => Location::factory(),
            'destination_address' => fake()->address(),
            'status' => ShippingOrderStatus::Draft,
            'packaging_preference' => PackagingPreference::Loose,
            'shipping_method' => fake()->randomElement(['Standard', 'Express', 'Temperature Controlled']),
            'carrier' => fake()->randomElement(['DHL', 'FedEx', 'UPS']),
            'incoterms' => fake()->randomElement(['EXW', 'FCA', 'DDP', 'DAP']),
            'requested_ship_date' => now()->addDays(7),
            'created_by' => User::factory()->superAdmin(),
        ];
    }
}
