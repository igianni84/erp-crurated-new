<?php

namespace Tests\Feature;

use App\Models\Customer\Customer;
use App\Models\User;
use App\Policies\CustomerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CustomerPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CustomerPolicy;
    }

    public function test_all_users_can_view_any_customers(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->viewAny($manager));
        $this->assertTrue($this->policy->viewAny($editor));
        $this->assertTrue($this->policy->viewAny($viewer));
    }

    public function test_all_users_can_view_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->view($superAdmin, $customer));
        $this->assertTrue($this->policy->view($viewer, $customer));
    }

    public function test_editor_and_above_can_create_customers(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->create($superAdmin));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->create($editor));
        $this->assertFalse($this->policy->create($viewer));
    }

    public function test_editor_and_above_can_update_customers(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->update($editor, $customer));
        $this->assertFalse($this->policy->update($viewer, $customer));
    }

    public function test_manager_and_above_can_delete_customers(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->delete($superAdmin, $customer));
        $this->assertTrue($this->policy->delete($admin, $customer));
        $this->assertTrue($this->policy->delete($manager, $customer));
        $this->assertFalse($this->policy->delete($editor, $customer));
        $this->assertFalse($this->policy->delete($viewer, $customer));
    }

    public function test_manager_and_above_can_restore_customers(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();

        $this->assertTrue($this->policy->restore($manager, $customer));
        $this->assertFalse($this->policy->restore($editor, $customer));
    }

    public function test_admin_and_above_can_force_delete_customers(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();

        $this->assertTrue($this->policy->forceDelete($superAdmin, $customer));
        $this->assertTrue($this->policy->forceDelete($admin, $customer));
        $this->assertFalse($this->policy->forceDelete($manager, $customer));
        $this->assertFalse($this->policy->forceDelete($editor, $customer));
    }

    public function test_manager_and_above_can_manage_blocks(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->manageBlocks($superAdmin, $customer));
        $this->assertTrue($this->policy->manageBlocks($admin, $customer));
        $this->assertTrue($this->policy->manageBlocks($manager, $customer));
        $this->assertFalse($this->policy->manageBlocks($editor, $customer));
        $this->assertFalse($this->policy->manageBlocks($viewer, $customer));
    }

    public function test_manager_and_above_can_manage_payments(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->managePayments($superAdmin, $customer));
        $this->assertTrue($this->policy->managePayments($admin, $customer));
        $this->assertTrue($this->policy->managePayments($manager, $customer));
        $this->assertFalse($this->policy->managePayments($editor, $customer));
        $this->assertFalse($this->policy->managePayments($viewer, $customer));
    }

    public function test_editor_and_above_can_suspend_customers(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->suspend($editor, $customer));
        $this->assertFalse($this->policy->suspend($viewer, $customer));
    }

    public function test_editor_and_above_can_activate_customers(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->activate($editor, $customer));
        $this->assertFalse($this->policy->activate($viewer, $customer));
    }

    public function test_editor_and_above_can_manage_membership(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->manageMembership($editor, $customer));
        $this->assertFalse($this->policy->manageMembership($viewer, $customer));
    }
}
