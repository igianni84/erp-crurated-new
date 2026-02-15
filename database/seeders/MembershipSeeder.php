<?php

namespace Database\Seeders;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\MembershipStatus;
use App\Enums\Customer\MembershipTier;
use App\Models\Customer\Customer;
use App\Models\Customer\Membership;
use Illuminate\Database\Seeder;

/**
 * MembershipSeeder - Creates membership records respecting the state machine.
 *
 * All memberships start as Applied and transition through the proper chain:
 * Applied → UnderReview → Approved|Rejected
 * Approved → Suspended
 */
class MembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();

        if ($customers->isEmpty()) {
            $this->command->warn('No customers found. Run CustomerSeeder first.');

            return;
        }

        foreach ($customers as $customer) {
            // Skip if membership already exists for this customer
            if (Membership::where('customer_id', $customer->id)->exists()) {
                continue;
            }

            // Closed customers: Applied → UnderReview → Approved → Suspended
            if ($customer->status === CustomerStatus::Closed) {
                $membership = Membership::create([
                    'customer_id' => $customer->id,
                    'tier' => MembershipTier::Member,
                    'status' => MembershipStatus::Applied,
                    'effective_from' => now()->subYears(2),
                ]);
                $membership->submitForReview();
                $membership->approve('Standard membership approved.');
                $membership->suspend('Account closed - membership suspended.');

                continue;
            }

            // Suspended customers: Applied → UnderReview → Approved → Suspended
            if ($customer->status === CustomerStatus::Suspended) {
                $membership = Membership::create([
                    'customer_id' => $customer->id,
                    'tier' => MembershipTier::Member,
                    'status' => MembershipStatus::Applied,
                    'effective_from' => now()->subYears(1),
                ]);
                $membership->submitForReview();
                $membership->approve('Standard membership approved.');
                $membership->suspend('Membership suspended due to payment issues.');

                continue;
            }

            // Active customers - distribute across tiers and target statuses
            $tierRandom = fake()->numberBetween(1, 100);

            if ($tierRandom <= 15) {
                // Legacy members - grandfathered: Applied → UnderReview → Approved
                $membership = Membership::create([
                    'customer_id' => $customer->id,
                    'tier' => MembershipTier::Legacy,
                    'status' => MembershipStatus::Applied,
                    'effective_from' => now()->subYears(fake()->numberBetween(3, 7)),
                ]);
                $membership->submitForReview();
                $membership->approve('Legacy member - founding customer.');
            } elseif ($tierRandom <= 85) {
                // Standard members: Applied → UnderReview → Approved
                $membership = Membership::create([
                    'customer_id' => $customer->id,
                    'tier' => MembershipTier::Member,
                    'status' => MembershipStatus::Applied,
                    'effective_from' => now()->subMonths(fake()->numberBetween(1, 24)),
                ]);
                $membership->submitForReview();
                $membership->approve('Standard membership approved.');
            } elseif ($tierRandom <= 95) {
                // Invitation-only: Applied → UnderReview → Approved
                $membership = Membership::create([
                    'customer_id' => $customer->id,
                    'tier' => MembershipTier::InvitationOnly,
                    'status' => MembershipStatus::Applied,
                    'effective_from' => now()->subMonths(fake()->numberBetween(1, 12)),
                ]);
                $membership->submitForReview();
                $membership->approve('Invitation extended and accepted - premium collector.');
            } else {
                // Under review: Applied → UnderReview
                $membership = Membership::create([
                    'customer_id' => $customer->id,
                    'tier' => MembershipTier::Member,
                    'status' => MembershipStatus::Applied,
                ]);
                $membership->submitForReview();
            }
        }

        // Create historical rejected membership applications
        $activeCustomers = Customer::where('status', CustomerStatus::Active)->get();
        $rejectedCustomers = $activeCustomers->random(min(2, $activeCustomers->count()));

        foreach ($rejectedCustomers as $customer) {
            $membership = Membership::create([
                'customer_id' => $customer->id,
                'tier' => MembershipTier::InvitationOnly,
                'status' => MembershipStatus::Applied,
            ]);
            $membership->submitForReview();
            $membership->reject('Invitation request not approved - insufficient purchase history.');
        }
    }
}
