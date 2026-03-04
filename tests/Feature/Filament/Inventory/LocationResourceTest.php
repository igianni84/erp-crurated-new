<?php

namespace Tests\Feature\Filament\Inventory;

use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Filament\Resources\Inventory\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\Inventory\LocationResource\Pages\EditLocation;
use App\Filament\Resources\Inventory\LocationResource\Pages\ListLocations;
use App\Filament\Resources\Inventory\LocationResource\Pages\ViewLocation;
use App\Models\Inventory\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class LocationResourceTest extends TestCase
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

        Livewire::test(ListLocations::class)
            ->assertSuccessful();
    }

    public function test_list_shows_locations(): void
    {
        $this->actingAsSuperAdmin();

        $locations = Location::factory()->count(3)->create();

        Livewire::test(ListLocations::class)
            ->assertCanSeeTableRecords($locations);
    }

    public function test_list_can_filter_by_location_type(): void
    {
        $this->actingAsSuperAdmin();

        $warehouse = Location::factory()->warehouse()->create();
        $bonded = Location::factory()->bonded()->create();

        Livewire::test(ListLocations::class)
            ->filterTable('location_type', LocationType::MainWarehouse->value)
            ->assertCanSeeTableRecords([$warehouse])
            ->assertCanNotSeeTableRecords([$bonded]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $active = Location::factory()->create(['status' => LocationStatus::Active]);
        $inactive = Location::factory()->create(['status' => LocationStatus::Inactive]);

        Livewire::test(ListLocations::class)
            ->filterTable('status', LocationStatus::Active->value)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_list_can_search_by_name(): void
    {
        $this->actingAsSuperAdmin();

        $target = Location::factory()->create(['name' => 'Unique Bordeaux Cellar']);
        $other = Location::factory()->create(['name' => 'Another Storage']);

        Livewire::test(ListLocations::class)
            ->searchTable('Unique Bordeaux')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateLocation::class)
            ->assertSuccessful();
    }

    public function test_can_create_location(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => 'Test Main Warehouse',
                'location_type' => LocationType::MainWarehouse->value,
                'country' => 'France',
                'status' => LocationStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('locations', [
            'name' => 'Test Main Warehouse',
            'location_type' => LocationType::MainWarehouse->value,
            'country' => 'France',
            'status' => LocationStatus::Active->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => null,
                'location_type' => null,
                'country' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'location_type' => 'required', 'country' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $location = Location::factory()->create();

        Livewire::test(EditLocation::class, ['record' => $location->id])
            ->assertSuccessful();
    }

    public function test_can_update_location(): void
    {
        $this->actingAsSuperAdmin();

        $location = Location::factory()->warehouse()->create();

        Livewire::test(EditLocation::class, ['record' => $location->id])
            ->fillForm([
                'name' => 'Updated Warehouse Name',
                'location_type' => LocationType::ThirdPartyStorage->value,
                'country' => 'Italy',
                'status' => LocationStatus::Inactive->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Updated Warehouse Name',
            'location_type' => LocationType::ThirdPartyStorage->value,
            'country' => 'Italy',
            'status' => LocationStatus::Inactive->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $location = Location::factory()->create();

        Livewire::test(ViewLocation::class, ['record' => $location->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListLocations::class)
            ->assertSuccessful();
    }
}
