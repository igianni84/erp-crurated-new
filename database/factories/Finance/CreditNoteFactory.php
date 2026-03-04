<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'customer_id' => Customer::factory(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'EUR',
            'reason' => fake()->sentence(),
            'status' => CreditNoteStatus::Draft,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_note_number' => 'CN-'.fake()->unique()->numerify('######'),
            'status' => CreditNoteStatus::Issued,
            'issued_at' => now(),
        ]);
    }
}
