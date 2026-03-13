<?php

namespace Database\Factories\Finance;

use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'payment_id' => Payment::factory(),
            'amount_applied' => fake()->randomFloat(2, 100, 5000),
            'applied_at' => now(),
            'applied_by' => User::factory()->superAdmin(),
            'created_by' => User::factory()->superAdmin(),
        ];
    }

    public function forInvoiceAndPayment(Invoice $invoice, Payment $payment): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
        ]);
    }
}
