<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payment_reference' => 'PAY-'.Str::upper(Str::random(10)),
            'source' => PaymentSource::Stripe,
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'EUR',
            'status' => PaymentStatus::Pending,
            'reconciliation_status' => ReconciliationStatus::Pending,
            'customer_id' => Customer::factory(),
            'received_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Confirmed,
        ]);
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Confirmed,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => PaymentSource::BankTransfer,
            'bank_reference' => 'BNK-'.Str::upper(Str::random(12)),
            'stripe_payment_intent_id' => null,
            'stripe_charge_id' => null,
        ]);
    }

    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => PaymentSource::Stripe,
            'stripe_payment_intent_id' => 'pi_'.Str::random(24),
            'stripe_charge_id' => 'ch_'.Str::random(24),
        ]);
    }

    public function mismatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'reconciliation_status' => ReconciliationStatus::Mismatched,
        ]);
    }
}
