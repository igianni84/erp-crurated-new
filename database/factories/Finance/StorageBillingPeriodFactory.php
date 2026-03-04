<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\StorageBillingStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\StorageBillingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageBillingPeriod>
 */
class StorageBillingPeriodFactory extends Factory
{
    protected $model = StorageBillingPeriod::class;

    public function definition(): array
    {
        $bottleCount = fake()->numberBetween(10, 500);
        $bottleDays = $bottleCount * fake()->numberBetween(20, 30);
        $unitRate = '0.0500';
        $calculatedAmount = (string) round($bottleDays * 0.05, 2);

        return [
            'customer_id' => Customer::factory(),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'bottle_count' => $bottleCount,
            'bottle_days' => $bottleDays,
            'unit_rate' => $unitRate,
            'calculated_amount' => $calculatedAmount,
            'currency' => 'EUR',
            'status' => StorageBillingStatus::Pending,
            'calculated_at' => now(),
        ];
    }

    public function invoiced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StorageBillingStatus::Invoiced,
        ]);
    }
}
