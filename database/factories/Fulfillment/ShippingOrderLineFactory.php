<?php

namespace Database\Factories\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingOrderLine>
 */
class ShippingOrderLineFactory extends Factory
{
    protected $model = ShippingOrderLine::class;

    public function definition(): array
    {
        return [
            'shipping_order_id' => ShippingOrder::factory(),
            'voucher_id' => Voucher::factory(),
            'allocation_id' => Allocation::factory(),
            'status' => ShippingOrderLineStatus::Pending,
            'created_by' => User::factory()->superAdmin(),
        ];
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShippingOrderLineStatus::Validated,
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShippingOrderLineStatus::Shipped,
            'bound_bottle_serial' => 'SER-'.fake()->unique()->numerify('######'),
            'binding_confirmed_at' => now(),
            'binding_confirmed_by' => User::factory()->superAdmin(),
        ]);
    }
}
