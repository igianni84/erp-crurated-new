<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\PimDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pim_dashboard_page_exists(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user);

        Livewire::test(PimDashboard::class)
            ->assertSuccessful();
    }

    public function test_pim_dashboard_has_correct_navigation_group(): void
    {
        $this->assertEquals('PIM', PimDashboard::getNavigationGroup());
    }

    public function test_pim_dashboard_has_correct_navigation_icon(): void
    {
        $this->assertEquals('heroicon-o-cube', PimDashboard::getNavigationIcon());
    }

    public function test_pim_dashboard_navigation_sort_is_first(): void
    {
        $this->assertEquals(0, PimDashboard::getNavigationSort());
    }

    public function test_user_resource_in_system_navigation_group(): void
    {
        $this->assertEquals('System', \App\Filament\Resources\UserResource::getNavigationGroup());
    }

    public function test_authenticated_user_can_access_pim_dashboard(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)
            ->get('/admin/pim-dashboard');

        $response->assertSuccessful();
    }

    public function test_unauthenticated_user_cannot_access_pim_dashboard(): void
    {
        $response = $this->get('/admin/pim-dashboard');

        $response->assertRedirect('/admin/login');
    }
}
