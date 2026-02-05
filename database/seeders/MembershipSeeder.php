<?php

namespace Database\Seeders;

use App\Enums\Customer\MembershipStatus;
use App\Enums\Customer\MembershipTier;
use App\Models\Customer\Customer;
use App\Models\Customer\Membership;
use Illuminate\Database\Seeder;

/**
 * MembershipSeeder - Creates membership records for customers
 *
 * Memberships represent a customer's membership status and tier in the system.
 * - Legacy: Grandfathered members with full access
 * - Member: Standard membership requiring approval
 * - InvitationOnly: Exclusive tier for select customers
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

        foreach ($customers as $index => $customer) {
            // Skip closed customers
            if ($customer->status === Customer::STATUS_CLOSED) {
                // Closed customers may have a rejected/suspended membership
                Membership::firstOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'tier' => MembershipTier::Member,
                        'status' => MembershipStatus::Suspended,
                        'effective_from' => now()->subYears(2),
                        'effective_to' => now()->subMonths(3),
                        'decision_notes' => 'Account closed - membership suspended.',
                    ]
                );

                continue;
            }

            // Suspended customers have suspended memberships
            if ($customer->status === Customer::STATUS_SUSPENDED) {
                Membership::firstOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'tier' => MembershipTier::Member,
                        'status' => MembershipStatus::Suspended,
                        'effective_from' => now()->subYears(1),
                        'effective_to' => null,
                        'decision_notes' => 'Membership suspended due to payment issues.',
                    ]
                );

                continue;
            }

            // Active customers - distribute across tiers and statuses
            // 15% Legacy, 70% Member (approved), 10% InvitationOnly, 5% Under Review
            $tierRandom = fake()->numberBetween(1, 100);

            if ($tierRandom <= 15) {
                // Legacy members - grandfathered with full access
                Membership::firstOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'tier' => MembershipTier::Legacy,
                        'status' => MembershipStatus::Approved,
                        'effective_from' => now()->subYears(fake()->numberBetween(3, 7)),
                        'effective_to' => null,
                        'decision_notes' => 'Legacy member - founding customer.',
                    ]
                );
            } elseif ($tierRandom <= 85) {
                // Standard members - majority of customers
                Membership::firstOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'tier' => MembershipTier::Member,
                        'status' => MembershipStatus::Approved,
                        'effective_from' => now()->subMonths(fake()->numberBetween(1, 24)),
                        'effective_to' => null,
                        'decision_notes' => 'Standard membership approved.',
                    ]
                );
            } elseif ($tierRandom <= 95) {
                // Invitation-only members - exclusive tier
                Membership::firstOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'tier' => MembershipTier::InvitationOnly,
                        'status' => MembershipStatus::Approved,
                        'effective_from' => now()->subMonths(fake()->numberBetween(1, 12)),
                        'effective_to' => null,
                        'decision_notes' => 'Invitation extended and accepted - premium collector.',
                    ]
                );
            } else {
                // Under review - pending approval
                Membership::firstOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'tier' => MembershipTier::Member,
                        'status' => MembershipStatus::UnderReview,
                        'effective_from' => null,
                        'effective_to' => null,
                        'decision_notes' => null,
                    ]
                );
            }
        }

        // Create some historical membership records (tier upgrades)
        $upgradedCustomers = Customer::where('status', Customer::STATUS_ACTIVE)
            ->inRandomOrder()
            ->take(3)
            ->get();

        foreach ($upgradedCustomers as $customer) {
            // Check if this customer already has an InvitationOnly membership
            $currentMembership = Membership::where('customer_id', $customer->id)->first();

            if ($currentMembership && $currentMembership->tier === MembershipTier::InvitationOnly) {
                // Create historical Member record showing the upgrade path
                Membership::create([
                    'customer_id' => $customer->id,
                    'tier' => MembershipTier::Member,
                    'status' => MembershipStatus::Approved,
                    'effective_from' => now()->subYears(2),
                    'effective_to' => now()->subMonths(6),
                    'decision_notes' => 'Upgraded to Invitation Only tier.',
                ]);
            }
        }

        // Create some rejected membership applications
        $rejectedCount = 2;
        for ($i = 0; $i < $rejectedCount; $i++) {
            $randomCustomer = Customer::where('status', Customer::STATUS_ACTIVE)
                ->inRandomOrder()
                ->first();

            if ($randomCustomer) {
                // Create a rejected application in the past (not the current membership)
                Membership::create([
                    'customer_id' => $randomCustomer->id,
                    'tier' => MembershipTier::InvitationOnly,
                    'status' => MembershipStatus::Rejected,
                    'effective_from' => null,
                    'effective_to' => null,
                    'decision_notes' => 'Invitation request not approved - insufficient purchase history.',
                ]);
            }
        }
    }
}
