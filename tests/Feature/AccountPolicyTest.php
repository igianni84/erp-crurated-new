<?php

namespace Tests\Feature;

use App\Enums\Customer\AccountUserRole;
use App\Models\Customer\Account;
use App\Models\Customer\AccountUser;
use App\Models\Customer\Customer;
use App\Models\User;
use App\Policies\AccountPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AccountPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AccountPolicy;
    }

    protected function createCustomerWithAccount(): array
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
        ]);

        $account = Account::create([
            'customer_id' => $customer->id,
            'name' => 'Test Account',
            'channel_scope' => 'b2c',
            'status' => 'active',
        ]);

        return [$customer, $account];
    }

    public function test_all_users_can_view_any_accounts(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertTrue($this->policy->viewAny($viewer));
    }

    public function test_all_users_can_view_account(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $superAdmin = User::factory()->superAdmin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->view($superAdmin, $account));
        $this->assertTrue($this->policy->view($viewer, $account));
    }

    public function test_editor_and_above_can_create_accounts(): void
    {
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->create($editor));
        $this->assertFalse($this->policy->create($viewer));
    }

    public function test_editor_and_above_can_update_accounts(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->update($editor, $account));
        $this->assertFalse($this->policy->update($viewer, $account));
    }

    public function test_account_user_with_operate_permission_can_update(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        // Create a user with Operator role on the account
        $operator = User::factory()->viewer()->create();
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $operator->id,
            'role' => AccountUserRole::Operator,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        // Create a user with Viewer role on the account
        $accountViewer = User::factory()->viewer()->create();
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $accountViewer->id,
            'role' => AccountUserRole::Viewer,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->assertTrue($this->policy->update($operator, $account));
        $this->assertFalse($this->policy->update($accountViewer, $account));
    }

    public function test_manager_and_above_can_delete_accounts(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->delete($manager, $account));
        $this->assertFalse($this->policy->delete($editor, $account));
        $this->assertFalse($this->policy->delete($viewer, $account));
    }

    public function test_account_owner_can_delete_own_account(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        // Create an account owner (even if they're a viewer system-wide)
        $owner = User::factory()->viewer()->create();
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'role' => AccountUserRole::Owner,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->assertTrue($this->policy->delete($owner, $account));
    }

    public function test_manager_and_above_can_restore_accounts(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();

        $this->assertTrue($this->policy->restore($manager, $account));
        $this->assertFalse($this->policy->restore($editor, $account));
    }

    public function test_admin_and_above_can_force_delete_accounts(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();

        $this->assertTrue($this->policy->forceDelete($admin, $account));
        $this->assertFalse($this->policy->forceDelete($manager, $account));
    }

    public function test_manager_and_above_can_manage_users(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->manageUsers($superAdmin, $account));
        $this->assertTrue($this->policy->manageUsers($admin, $account));
        $this->assertTrue($this->policy->manageUsers($manager, $account));
        $this->assertFalse($this->policy->manageUsers($editor, $account));
        $this->assertFalse($this->policy->manageUsers($viewer, $account));
    }

    public function test_account_owner_or_admin_can_manage_users(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        // Create an account owner (even if they're a viewer system-wide)
        $owner = User::factory()->viewer()->create();
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'role' => AccountUserRole::Owner,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        // Create an account admin (even if they're a viewer system-wide)
        $accountAdmin = User::factory()->viewer()->create();
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $accountAdmin->id,
            'role' => AccountUserRole::Admin,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        // Create an account operator
        $accountOperator = User::factory()->viewer()->create();
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $accountOperator->id,
            'role' => AccountUserRole::Operator,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->assertTrue($this->policy->manageUsers($owner, $account));
        $this->assertTrue($this->policy->manageUsers($accountAdmin, $account));
        $this->assertFalse($this->policy->manageUsers($accountOperator, $account));
    }

    public function test_editor_and_above_can_suspend_accounts(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->suspend($editor, $account));
        $this->assertFalse($this->policy->suspend($viewer, $account));
    }

    public function test_editor_and_above_can_activate_accounts(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->activate($editor, $account));
        $this->assertFalse($this->policy->activate($viewer, $account));
    }

    public function test_manager_and_above_can_manage_blocks(): void
    {
        [$customer, $account] = $this->createCustomerWithAccount();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->manageBlocks($superAdmin, $account));
        $this->assertTrue($this->policy->manageBlocks($admin, $account));
        $this->assertTrue($this->policy->manageBlocks($manager, $account));
        $this->assertFalse($this->policy->manageBlocks($editor, $account));
        $this->assertFalse($this->policy->manageBlocks($viewer, $account));
    }
}
