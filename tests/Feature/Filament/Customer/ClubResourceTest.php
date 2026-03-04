<?php

namespace Tests\Feature\Filament\Customer;

use App\Enums\Customer\ClubStatus;
use App\Filament\Resources\Customer\ClubResource\Pages\CreateClub;
use App\Filament\Resources\Customer\ClubResource\Pages\EditClub;
use App\Filament\Resources\Customer\ClubResource\Pages\ListClubs;
use App\Filament\Resources\Customer\ClubResource\Pages\ViewClub;
use App\Models\Customer\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ClubResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page ───────────────────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListClubs::class)
            ->assertSuccessful();
    }

    public function test_list_shows_clubs(): void
    {
        $this->actingAsSuperAdmin();

        $clubs = Club::factory()->count(3)->create();

        Livewire::test(ListClubs::class)
            ->assertCanSeeTableRecords($clubs);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $active = Club::factory()->active()->create();
        $suspended = Club::factory()->suspended()->create();

        Livewire::test(ListClubs::class)
            ->filterTable('status', ClubStatus::Active->value)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$suspended]);
    }

    public function test_list_can_search_by_partner_name(): void
    {
        $this->actingAsSuperAdmin();

        $target = Club::factory()->create(['partner_name' => 'Unique Prestige Club']);
        $other = Club::factory()->create(['partner_name' => 'Another Wine Club']);

        Livewire::test(ListClubs::class)
            ->searchTable('Unique Prestige')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateClub::class)
            ->assertSuccessful();
    }

    public function test_can_create_club(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateClub::class)
            ->fillForm([
                'partner_name' => 'Test Wine Club',
                'status' => ClubStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clubs', [
            'partner_name' => 'Test Wine Club',
            'status' => ClubStatus::Active->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateClub::class)
            ->fillForm([
                'partner_name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['partner_name' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $club = Club::factory()->create();

        Livewire::test(EditClub::class, ['record' => $club->id])
            ->assertSuccessful();
    }

    public function test_can_update_club(): void
    {
        $this->actingAsSuperAdmin();

        $club = Club::factory()->active()->create();

        Livewire::test(EditClub::class, ['record' => $club->id])
            ->fillForm([
                'partner_name' => 'Updated Club Name',
                'status' => ClubStatus::Suspended->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clubs', [
            'id' => $club->id,
            'partner_name' => 'Updated Club Name',
            'status' => ClubStatus::Suspended->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $club = Club::factory()->create();

        Livewire::test(ViewClub::class, ['record' => $club->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListClubs::class)
            ->assertSuccessful();
    }
}
