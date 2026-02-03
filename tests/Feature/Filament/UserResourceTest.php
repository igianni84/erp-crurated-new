<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->viewer = User::factory()->viewer()->create();
    }

    public function test_super_admin_can_access_user_list(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->assertSuccessful();
    }

    public function test_viewer_cannot_access_user_list(): void
    {
        $this->actingAs($this->viewer);

        Livewire::test(ListUsers::class)
            ->assertForbidden();
    }

    public function test_super_admin_can_see_users_in_table(): void
    {
        $this->actingAs($this->superAdmin);

        $users = User::factory()->count(3)->create();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords($users);
    }

    public function test_super_admin_can_filter_users_by_role(): void
    {
        $this->actingAs($this->superAdmin);

        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();

        Livewire::test(ListUsers::class)
            ->filterTable('role', UserRole::Admin->value)
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$editor, $this->viewer]);
    }

    public function test_super_admin_can_search_users_by_name(): void
    {
        $this->actingAs($this->superAdmin);

        $searchableUser = User::factory()->create(['name' => 'Unique Searchable Name']);
        $otherUser = User::factory()->create(['name' => 'Different Name']);

        Livewire::test(ListUsers::class)
            ->searchTable('Unique Searchable')
            ->assertCanSeeTableRecords([$searchableUser])
            ->assertCanNotSeeTableRecords([$otherUser]);
    }

    public function test_super_admin_can_access_create_user_page(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->assertSuccessful();
    }

    public function test_viewer_cannot_access_create_user_page(): void
    {
        $this->actingAs($this->viewer);

        Livewire::test(CreateUser::class)
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'role' => UserRole::Editor->value,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => UserRole::Editor->value,
        ]);
    }

    public function test_super_admin_can_access_edit_user_page(): void
    {
        $this->actingAs($this->superAdmin);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->assertSuccessful();
    }

    public function test_viewer_cannot_access_edit_user_page(): void
    {
        $this->actingAs($this->viewer);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->assertForbidden();
    }

    public function test_super_admin_can_update_user(): void
    {
        $this->actingAs($this->superAdmin);

        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
                'email' => $user->email,
                'role' => UserRole::Manager->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => UserRole::Manager->value,
        ]);
    }

    public function test_super_admin_can_update_password(): void
    {
        $this->actingAs($this->superAdmin);

        $user = User::factory()->create();
        $oldPasswordHash = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertNotEquals($oldPasswordHash, $user->password);
    }

    public function test_super_admin_can_delete_other_user(): void
    {
        $this->actingAs($this->superAdmin);

        $userToDelete = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $userToDelete);

        $this->assertDatabaseMissing('users', [
            'id' => $userToDelete->id,
        ]);
    }

    public function test_delete_action_hidden_for_self(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('delete', $this->superAdmin);
    }

    public function test_user_resource_has_correct_navigation_group(): void
    {
        $this->assertEquals('System', UserResource::getNavigationGroup());
    }
}
