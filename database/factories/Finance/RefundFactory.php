<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\RefundMethod;
use App\Enums\Finance\RefundStatus;
use App\Enums\Finance\RefundType;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 2000);
        $invoice = Invoice::factory()->create(['total_amount' => $amount]);
        $payment = Payment::factory()->create(['amount' => $amount]);

        // Create the required invoice-payment pivot link
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'amount_applied' => $amount,
            'applied_at' => now(),
        ]);

        return [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'refund_type' => RefundType::Full,
            'method' => RefundMethod::Stripe,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => RefundStatus::Pending,
            'reason' => fake()->sentence(),
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Processed,
            'processed_at' => now(),
        ]);
    }
}
