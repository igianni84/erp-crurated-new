<?php

namespace Database\Seeders;

use App\Enums\Finance\BillingCycle;
use App\Enums\Finance\SubscriptionPlanType;
use App\Enums\Finance\SubscriptionStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\Subscription;
use Illuminate\Database\Seeder;

/**
 * SubscriptionSeeder - Creates customer subscriptions
 */
class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get customers
        $customers = Customer::all();

        if ($customers->isEmpty()) {
            $this->command->warn('No customers found. Run CustomerSeeder first.');

            return;
        }

        // Define subscription plans with realistic pricing
        $membershipPlans = [
            [
                'plan_name' => 'Collector Essential',
                'billing_cycle' => BillingCycle::Annual,
                'amount' => '299.00',
                'metadata' => [
                    'tier' => 'essential',
                    'allocation_access' => 'standard',
                    'storage_included_bottles' => 24,
                ],
            ],
            [
                'plan_name' => 'Collector Premium',
                'billing_cycle' => BillingCycle::Annual,
                'amount' => '599.00',
                'metadata' => [
                    'tier' => 'premium',
                    'allocation_access' => 'priority',
                    'storage_included_bottles' => 72,
                ],
            ],
            [
                'plan_name' => 'Collector Elite',
                'billing_cycle' => BillingCycle::Annual,
                'amount' => '1499.00',
                'metadata' => [
                    'tier' => 'elite',
                    'allocation_access' => 'exclusive',
                    'storage_included_bottles' => 240,
                    'concierge' => true,
                ],
            ],
            [
                'plan_name' => 'Collector Monthly',
                'billing_cycle' => BillingCycle::Monthly,
                'amount' => '49.00',
                'metadata' => [
                    'tier' => 'essential',
                    'allocation_access' => 'standard',
                    'storage_included_bottles' => 6,
                ],
            ],
            [
                'plan_name' => 'Quarterly Tasting Club',
                'billing_cycle' => BillingCycle::Quarterly,
                'amount' => '199.00',
                'metadata' => [
                    'tier' => 'tasting',
                    'quarterly_tastings' => 1,
                    'priority_events' => true,
                ],
            ],
        ];

        // Assign subscriptions to active customers
        $activeCustomers = $customers->where('status', Customer::STATUS_ACTIVE);

        foreach ($activeCustomers as $index => $customer) {
            // Each customer gets 1-2 subscriptions
            $numSubscriptions = fake()->numberBetween(1, 2);

            for ($i = 0; $i < $numSubscriptions; $i++) {
                $plan = fake()->randomElement($membershipPlans);

                // Calculate dates
                $startedAt = fake()->dateTimeBetween('-2 years', '-1 month');
                $startedAtCarbon = \Carbon\Carbon::instance($startedAt);

                // Calculate next billing date based on cycle
                $nextBillingDate = match ($plan['billing_cycle']) {
                    BillingCycle::Monthly => $startedAtCarbon->copy()->addMonth()->startOfDay(),
                    BillingCycle::Quarterly => $startedAtCarbon->copy()->addMonths(3)->startOfDay(),
                    BillingCycle::Annual => $startedAtCarbon->copy()->addYear()->startOfDay(),
                    default => $startedAtCarbon->copy()->addMonth()->startOfDay(),
                };

                // Ensure next billing is in the future or recent past
                while ($nextBillingDate->isPast()) {
                    $nextBillingDate = match ($plan['billing_cycle']) {
                        BillingCycle::Monthly => $nextBillingDate->addMonth(),
                        BillingCycle::Quarterly => $nextBillingDate->addMonths(3),
                        BillingCycle::Annual => $nextBillingDate->addYear(),
                        default => $nextBillingDate->addMonth(),
                    };
                }

                // Determine status
                $statusRandom = fake()->numberBetween(1, 100);
                if ($statusRandom <= 85) {
                    $status = SubscriptionStatus::Active;
                    $cancelledAt = null;
                    $cancellationReason = null;
                } elseif ($statusRandom <= 95) {
                    $status = SubscriptionStatus::Suspended;
                    $cancelledAt = null;
                    $cancellationReason = null;
                } else {
                    $status = SubscriptionStatus::Cancelled;
                    $cancelledAt = fake()->dateTimeBetween($startedAt, 'now');
                    $cancellationReason = fake()->randomElement([
                        'Customer requested cancellation',
                        'Payment failed multiple times',
                        'Relocated abroad',
                        'Downsizing collection',
                    ]);
                }

                // Check for existing subscription of same plan
                $exists = Subscription::where('customer_id', $customer->id)
                    ->where('plan_name', $plan['plan_name'])
                    ->exists();

                if (! $exists) {
                    Subscription::create([
                        'customer_id' => $customer->id,
                        'plan_type' => SubscriptionPlanType::Membership,
                        'plan_name' => $plan['plan_name'],
                        'billing_cycle' => $plan['billing_cycle'],
                        'amount' => $plan['amount'],
                        'currency' => 'EUR',
                        'status' => $status,
                        'started_at' => $startedAtCarbon->toDateString(),
                        'next_billing_date' => $nextBillingDate->toDateString(),
                        'cancelled_at' => $cancelledAt ? \Carbon\Carbon::instance($cancelledAt) : null,
                        'cancellation_reason' => $cancellationReason,
                        'stripe_subscription_id' => $status !== SubscriptionStatus::Cancelled
                            ? 'sub_'.fake()->regexify('[A-Za-z0-9]{14}')
                            : null,
                        'metadata' => $plan['metadata'],
                    ]);
                }
            }
        }

        // Add suspended customer's subscription
        $suspendedCustomer = $customers->where('status', Customer::STATUS_SUSPENDED)->first();
        if ($suspendedCustomer) {
            $exists = Subscription::where('customer_id', $suspendedCustomer->id)->exists();

            if (! $exists) {
                Subscription::create([
                    'customer_id' => $suspendedCustomer->id,
                    'plan_type' => SubscriptionPlanType::Membership,
                    'plan_name' => 'Collector Premium',
                    'billing_cycle' => BillingCycle::Annual,
                    'amount' => '599.00',
                    'currency' => 'EUR',
                    'status' => SubscriptionStatus::Suspended,
                    'started_at' => now()->subMonths(8)->toDateString(),
                    'next_billing_date' => now()->subMonths(2)->toDateString(),
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                    'stripe_subscription_id' => 'sub_'.fake()->regexify('[A-Za-z0-9]{14}'),
                    'metadata' => [
                        'tier' => 'premium',
                        'suspension_reason' => 'Overdue invoice',
                    ],
                ]);
            }
        }
    }
}
