<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\CustomerCreditStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\CustomerCredit;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCredit>
 */
class CustomerCreditFactory extends Factory
{
    protected $model = CustomerCredit::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 2000);

        return [
            'customer_id' => Customer::factory(),
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'currency' => 'EUR',
            'status' => CustomerCreditStatus::Available,
            'reason' => fake()->sentence(),
        ];
    }

    public function withSource(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_payment_id' => Payment::factory(),
            'source_invoice_id' => Invoice::factory(),
        ]);
    }

    public function partiallyUsed(): static
    {
        return $this->state(function (array $attributes) {
            $original = $attributes['original_amount'] ?? '500.00';
            $remaining = bcdiv((string) $original, '2', 2);

            return [
                'original_amount' => $original,
                'remaining_amount' => $remaining,
                'status' => CustomerCreditStatus::PartiallyUsed,
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerCreditStatus::Expired,
            'expires_at' => now()->subDays(7),
        ]);
    }
}
