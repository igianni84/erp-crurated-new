<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy;
    }

    public function test_super_admin_can_view_any_users(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertFalse($this->policy->viewAny($admin));
    }

    public function test_super_admin_can_view_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->view($superAdmin, $targetUser));
        $this->assertFalse($this->policy->view($admin, $targetUser));
    }

    public function test_super_admin_can_create_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->create($superAdmin));
        $this->assertFalse($this->policy->create($admin));
    }

    public function test_super_admin_can_update_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->update($superAdmin, $targetUser));
        $this->assertFalse($this->policy->update($admin, $targetUser));
    }

    public function test_super_admin_can_delete_other_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $targetUser = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->delete($superAdmin, $targetUser));
    }

    public function test_super_admin_cannot_delete_self(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertFalse($this->policy->delete($superAdmin, $superAdmin));
    }

    public function test_non_super_admin_cannot_delete_users(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->viewer()->create();

        $this->assertFalse($this->policy->delete($admin, $targetUser));
    }

    public function test_super_admin_can_restore_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->restore($superAdmin, $targetUser));
        $this->assertFalse($this->policy->restore($admin, $targetUser));
    }

    public function test_super_admin_can_force_delete_other_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $targetUser = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->forceDelete($superAdmin, $targetUser));
    }

    public function test_super_admin_cannot_force_delete_self(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertFalse($this->policy->forceDelete($superAdmin, $superAdmin));
    }

    public function test_viewer_cannot_perform_any_user_actions(): void
    {
        $viewer = User::factory()->viewer()->create();
        $targetUser = User::factory()->viewer()->create();

        $this->assertFalse($this->policy->viewAny($viewer));
        $this->assertFalse($this->policy->view($viewer, $targetUser));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $targetUser));
        $this->assertFalse($this->policy->delete($viewer, $targetUser));
    }
}
