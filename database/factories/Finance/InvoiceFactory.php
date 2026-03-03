<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'invoice_number' => null,
            'invoice_type' => InvoiceType::VoucherSale,
            'customer_id' => Customer::factory(),
            'currency' => 'EUR',
            'subtotal' => fake()->randomFloat(2, 100, 10000),
            'tax_amount' => fake()->randomFloat(2, 10, 2000),
            'total_amount' => fake()->randomFloat(2, 110, 12000),
            'amount_paid' => '0',
            'status' => InvoiceStatus::Draft,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
        ]);
    }

    public function membership(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_type' => InvoiceType::MembershipService,
        ]);
    }

    public function storageFee(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_type' => InvoiceType::StorageFee,
        ]);
    }
}
