<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_enum_has_all_expected_values(): void
    {
        $cases = UserRole::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(UserRole::SuperAdmin, $cases);
        $this->assertContains(UserRole::Admin, $cases);
        $this->assertContains(UserRole::Manager, $cases);
        $this->assertContains(UserRole::Editor, $cases);
        $this->assertContains(UserRole::Viewer, $cases);
    }

    public function test_user_role_values_are_correct(): void
    {
        $this->assertEquals('super_admin', UserRole::SuperAdmin->value);
        $this->assertEquals('admin', UserRole::Admin->value);
        $this->assertEquals('manager', UserRole::Manager->value);
        $this->assertEquals('editor', UserRole::Editor->value);
        $this->assertEquals('viewer', UserRole::Viewer->value);
    }

    public function test_user_role_labels_are_human_readable(): void
    {
        $this->assertEquals('Super Admin', UserRole::SuperAdmin->label());
        $this->assertEquals('Admin', UserRole::Admin->label());
        $this->assertEquals('Manager', UserRole::Manager->label());
        $this->assertEquals('Editor', UserRole::Editor->label());
        $this->assertEquals('Viewer', UserRole::Viewer->label());
    }

    public function test_user_role_has_level_hierarchy(): void
    {
        $this->assertGreaterThan(UserRole::Admin->level(), UserRole::SuperAdmin->level());
        $this->assertGreaterThan(UserRole::Manager->level(), UserRole::Admin->level());
        $this->assertGreaterThan(UserRole::Editor->level(), UserRole::Manager->level());
        $this->assertGreaterThan(UserRole::Viewer->level(), UserRole::Editor->level());
    }

    public function test_super_admin_has_at_least_all_roles(): void
    {
        $superAdmin = UserRole::SuperAdmin;

        $this->assertTrue($superAdmin->hasAtLeast(UserRole::SuperAdmin));
        $this->assertTrue($superAdmin->hasAtLeast(UserRole::Admin));
        $this->assertTrue($superAdmin->hasAtLeast(UserRole::Manager));
        $this->assertTrue($superAdmin->hasAtLeast(UserRole::Editor));
        $this->assertTrue($superAdmin->hasAtLeast(UserRole::Viewer));
    }

    public function test_viewer_only_has_viewer_permissions(): void
    {
        $viewer = UserRole::Viewer;

        $this->assertFalse($viewer->hasAtLeast(UserRole::SuperAdmin));
        $this->assertFalse($viewer->hasAtLeast(UserRole::Admin));
        $this->assertFalse($viewer->hasAtLeast(UserRole::Manager));
        $this->assertFalse($viewer->hasAtLeast(UserRole::Editor));
        $this->assertTrue($viewer->hasAtLeast(UserRole::Viewer));
    }

    public function test_only_super_admin_can_manage_users(): void
    {
        $this->assertTrue(UserRole::SuperAdmin->canManageUsers());
        $this->assertFalse(UserRole::Admin->canManageUsers());
        $this->assertFalse(UserRole::Manager->canManageUsers());
        $this->assertFalse(UserRole::Editor->canManageUsers());
        $this->assertFalse(UserRole::Viewer->canManageUsers());
    }

    public function test_editor_and_above_can_edit(): void
    {
        $this->assertTrue(UserRole::SuperAdmin->canEdit());
        $this->assertTrue(UserRole::Admin->canEdit());
        $this->assertTrue(UserRole::Manager->canEdit());
        $this->assertTrue(UserRole::Editor->canEdit());
        $this->assertFalse(UserRole::Viewer->canEdit());
    }

    public function test_only_viewer_is_read_only(): void
    {
        $this->assertFalse(UserRole::SuperAdmin->isReadOnly());
        $this->assertFalse(UserRole::Admin->isReadOnly());
        $this->assertFalse(UserRole::Manager->isReadOnly());
        $this->assertFalse(UserRole::Editor->isReadOnly());
        $this->assertTrue(UserRole::Viewer->isReadOnly());
    }

    public function test_user_model_is_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertFalse($viewer->isSuperAdmin());
    }

    public function test_user_model_is_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();

        $this->assertTrue($superAdmin->isAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($manager->isAdmin());
    }

    public function test_user_model_can_manage_users(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($superAdmin->canManageUsers());
        $this->assertFalse($admin->canManageUsers());
    }

    public function test_user_model_can_edit(): void
    {
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($editor->canEdit());
        $this->assertFalse($viewer->canEdit());
    }

    public function test_user_model_is_viewer(): void
    {
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertFalse($editor->isViewer());
        $this->assertTrue($viewer->isViewer());
    }

    public function test_user_factory_creates_all_role_types(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertEquals(UserRole::SuperAdmin, $superAdmin->role);
        $this->assertEquals(UserRole::Admin, $admin->role);
        $this->assertEquals(UserRole::Manager, $manager->role);
        $this->assertEquals(UserRole::Editor, $editor->role);
        $this->assertEquals(UserRole::Viewer, $viewer->role);
    }
}
