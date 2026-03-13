<?php

namespace Database\Factories\Finance;

use App\Models\Finance\StripeWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StripeWebhook>
 */
class StripeWebhookFactory extends Factory
{
    protected $model = StripeWebhook::class;

    public function definition(): array
    {
        return [
            'event_id' => 'evt_'.Str::random(24),
            'event_type' => fake()->randomElement([
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
                'charge.refunded',
                'invoice.paid',
            ]),
            'payload' => [
                'id' => 'evt_'.Str::random(24),
                'type' => 'payment_intent.succeeded',
                'data' => [
                    'object' => [
                        'id' => 'pi_'.Str::random(24),
                        'amount' => fake()->numberBetween(1000, 500000),
                        'currency' => 'eur',
                    ],
                ],
            ],
            'processed' => false,
            'retry_count' => 0,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed' => true,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed' => false,
            'error_message' => fake()->sentence(),
        ]);
    }
}
