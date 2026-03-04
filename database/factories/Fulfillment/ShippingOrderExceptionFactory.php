<?php

namespace Database\Factories\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingOrderException>
 */
class ShippingOrderExceptionFactory extends Factory
{
    protected $model = ShippingOrderException::class;

    public function definition(): array
    {
        return [
            'shipping_order_id' => ShippingOrder::factory(),
            'exception_type' => fake()->randomElement(ShippingOrderExceptionType::cases()),
            'description' => fake()->sentence(),
            'status' => ShippingOrderExceptionStatus::Active,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShippingOrderExceptionStatus::Resolved,
            'resolved_at' => now(),
            'resolution_path' => 'Manually resolved by operator',
        ]);
    }
}
