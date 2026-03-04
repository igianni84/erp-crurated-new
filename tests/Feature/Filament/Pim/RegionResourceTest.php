<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\RegionResource\Pages\CreateRegion;
use App\Filament\Resources\Pim\RegionResource\Pages\EditRegion;
use App\Filament\Resources\Pim\RegionResource\Pages\ListRegions;
use App\Models\Pim\Country;
use App\Models\Pim\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class RegionResourceTest extends TestCase
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

        Livewire::test(ListRegions::class)
            ->assertSuccessful();
    }

    public function test_list_shows_regions(): void
    {
        $this->actingAsSuperAdmin();

        $regions = Region::factory()->count(3)->create();

        Livewire::test(ListRegions::class)
            ->assertCanSeeTableRecords($regions);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateRegion::class)
            ->assertSuccessful();
    }

    public function test_can_create_region(): void
    {
        $this->actingAsSuperAdmin();

        $country = Country::factory()->create();

        Livewire::test(CreateRegion::class)
            ->fillForm([
                'name' => 'Test Region',
                'country_id' => $country->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('regions', [
            'name' => 'Test Region',
            'country_id' => $country->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateRegion::class)
            ->fillForm([
                'name' => null,
                'country_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'country_id' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $region = Region::factory()->create();

        Livewire::test(EditRegion::class, ['record' => $region->id])
            ->assertSuccessful();
    }

    public function test_can_update_region(): void
    {
        $this->actingAsSuperAdmin();

        $region = Region::factory()->create();

        Livewire::test(EditRegion::class, ['record' => $region->id])
            ->fillForm([
                'name' => 'Updated Region',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('regions', [
            'id' => $region->id,
            'name' => 'Updated Region',
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListRegions::class)
            ->assertSuccessful();
    }
}
