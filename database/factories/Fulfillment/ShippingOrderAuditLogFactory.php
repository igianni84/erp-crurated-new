<?php

namespace Database\Factories\Fulfillment;

use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingOrderAuditLog>
 */
class ShippingOrderAuditLogFactory extends Factory
{
    protected $model = ShippingOrderAuditLog::class;

    public function definition(): array
    {
        return [
            'shipping_order_id' => ShippingOrder::factory(),
            'event_type' => fake()->randomElement([
                'status_changed',
                'line_added',
                'line_removed',
                'binding_confirmed',
                'shipment_created',
            ]),
            'description' => fake()->sentence(),
            'created_at' => now(),
        ];
    }

    public function statusChange(string $from, string $to): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'status_changed',
            'description' => "Status changed from {$from} to {$to}",
            'old_values' => ['status' => $from],
            'new_values' => ['status' => $to],
        ]);
    }

    public function byUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->superAdmin(),
        ]);
    }
}
