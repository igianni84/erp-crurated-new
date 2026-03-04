<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\BillingCycle;
use App\Enums\Finance\SubscriptionPlanType;
use App\Enums\Finance\SubscriptionStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'plan_type' => SubscriptionPlanType::Membership,
            'plan_name' => fake()->randomElement(['Gold', 'Silver', 'Bronze']).' Membership',
            'billing_cycle' => BillingCycle::Annual,
            'amount' => fake()->randomFloat(2, 100, 2000),
            'currency' => 'EUR',
            'status' => SubscriptionStatus::Active,
            'started_at' => now()->subMonths(3),
            'next_billing_date' => now()->addMonths(9),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Customer requested cancellation',
        ]);
    }
}
