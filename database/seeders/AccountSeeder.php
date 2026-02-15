<?php

namespace Database\Seeders;

use App\Enums\Customer\AccountStatus;
use App\Enums\Customer\AccountUserRole;
use App\Enums\Customer\ChannelScope;
use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Models\Customer\Account;
use App\Models\Customer\AccountUser;
use App\Models\Customer\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * AccountSeeder - Creates operational accounts for customers
 *
 * Accounts represent operational contexts for customers.
 * A Customer can have multiple Accounts (e.g., different channel scopes).
 * B2B accounts are only created for customers with customer_type = B2B.
 */
class AccountSeeder extends Seeder
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

        // Get admin user for account creation
        $adminUser = User::first();

        foreach ($customers as $customer) {
            // Note: AccountStatus only has Active and Suspended values
            $accountStatus = match ($customer->status) {
                CustomerStatus::Active => AccountStatus::Active,
                default => AccountStatus::Suspended,
            };

            // All non-closed customers get a B2C account
            if ($customer->status !== CustomerStatus::Closed) {
                $b2cAccount = Account::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'channel_scope' => ChannelScope::B2C,
                    ],
                    [
                        'name' => $customer->name.' - Personal',
                        'status' => $accountStatus,
                    ]
                );

                // Link account to user if a user exists with the same email
                $user = User::where('email', $customer->email)->first();
                if ($user) {
                    $invitedAt = now()->subMonths(fake()->numberBetween(3, 12));
                    $acceptedAt = $invitedAt->copy()->addDays(fake()->numberBetween(1, 60));

                    AccountUser::firstOrCreate(
                        [
                            'account_id' => $b2cAccount->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'role' => AccountUserRole::Owner,
                            'invited_at' => $invitedAt,
                            'accepted_at' => $acceptedAt,
                        ]
                    );
                }
            }

            // B2B accounts ONLY for customers with customer_type = B2B
            if ($customer->status === CustomerStatus::Active && $customer->customer_type === CustomerType::B2B) {
                Account::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'channel_scope' => ChannelScope::B2B,
                    ],
                    [
                        'name' => $customer->name.' - Business',
                        'status' => AccountStatus::Active,
                    ]
                );
            }

            // Some premium customers get Club accounts (15% of active customers)
            if ($customer->status === CustomerStatus::Active && fake()->boolean(15)) {
                Account::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'channel_scope' => ChannelScope::Club,
                    ],
                    [
                        'name' => $customer->name.' - Club Member',
                        'status' => AccountStatus::Active,
                    ]
                );
            }
        }

        // Create some accounts with multiple users (shared family/business accounts)
        $multiUserAccounts = Account::where('channel_scope', ChannelScope::B2C)
            ->where('status', AccountStatus::Active)
            ->inRandomOrder()
            ->take(3)
            ->get();

        foreach ($multiUserAccounts as $account) {
            if ($adminUser) {
                $invitedAt = now()->subMonths(fake()->numberBetween(1, 6));
                $acceptedAt = $invitedAt->copy()->addDays(fake()->numberBetween(1, 30));

                AccountUser::firstOrCreate(
                    [
                        'account_id' => $account->id,
                        'user_id' => $adminUser->id,
                    ],
                    [
                        'role' => AccountUserRole::Operator,
                        'invited_at' => $invitedAt,
                        'accepted_at' => $acceptedAt,
                    ]
                );
            }
        }
    }
}
