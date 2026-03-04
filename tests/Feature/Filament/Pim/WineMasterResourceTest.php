<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\WineMasterResource\Pages\CreateWineMaster;
use App\Filament\Resources\Pim\WineMasterResource\Pages\EditWineMaster;
use App\Filament\Resources\Pim\WineMasterResource\Pages\ListWineMasters;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\WineMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class WineMasterResourceTest extends TestCase
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

        Livewire::test(ListWineMasters::class)
            ->assertSuccessful();
    }

    public function test_list_shows_wine_masters(): void
    {
        $this->actingAsSuperAdmin();

        $wineMasters = WineMaster::factory()->count(3)->create();

        Livewire::test(ListWineMasters::class)
            ->assertCanSeeTableRecords($wineMasters);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateWineMaster::class)
            ->assertSuccessful();
    }

    public function test_can_create_wine_master(): void
    {
        $this->actingAsSuperAdmin();

        $country = Country::factory()->create();
        $producer = Producer::factory()->create(['country_id' => $country->id]);

        Livewire::test(CreateWineMaster::class)
            ->fillForm([
                'name' => 'Test Wine Master',
                'producer_id' => $producer->id,
                'country_id' => $country->id,
                'producer' => $producer->name,
                'country' => $country->name,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wine_masters', [
            'name' => 'Test Wine Master',
            'producer_id' => $producer->id,
            'country_id' => $country->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateWineMaster::class)
            ->fillForm([
                'name' => null,
                'producer_id' => null,
                'country_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'producer_id' => 'required', 'country_id' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $wineMaster = WineMaster::factory()->create();

        Livewire::test(EditWineMaster::class, ['record' => $wineMaster->id])
            ->assertSuccessful();
    }

    public function test_can_update_wine_master(): void
    {
        $this->actingAsSuperAdmin();

        $country = Country::factory()->create();
        $producer = Producer::factory()->create(['country_id' => $country->id]);
        $wineMaster = WineMaster::factory()->create([
            'producer_id' => $producer->id,
            'country_id' => $country->id,
            'producer' => $producer->name,
            'country' => $country->name,
        ]);

        Livewire::test(EditWineMaster::class, ['record' => $wineMaster->id])
            ->fillForm([
                'name' => 'Updated Wine Master',
                'description' => 'Updated description',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wine_masters', [
            'id' => $wineMaster->id,
            'name' => 'Updated Wine Master',
            'description' => 'Updated description',
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListWineMasters::class)
            ->assertSuccessful();
    }
}
