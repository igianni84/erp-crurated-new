<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\CountryResource\Pages\CreateCountry;
use App\Filament\Resources\Pim\CountryResource\Pages\EditCountry;
use App\Filament\Resources\Pim\CountryResource\Pages\ListCountries;
use App\Models\Pim\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class CountryResourceTest extends TestCase
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

        Livewire::test(ListCountries::class)
            ->assertSuccessful();
    }

    public function test_list_shows_countries(): void
    {
        $this->actingAsSuperAdmin();

        $countries = Country::factory()->count(3)->create();

        Livewire::test(ListCountries::class)
            ->assertCanSeeTableRecords($countries);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCountry::class)
            ->assertSuccessful();
    }

    public function test_can_create_country(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCountry::class)
            ->fillForm([
                'name' => 'Test Country',
                'iso_code' => 'TC',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('countries', [
            'name' => 'Test Country',
            'iso_code' => 'TC',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCountry::class)
            ->fillForm([
                'name' => null,
                'iso_code' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'iso_code' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $country = Country::factory()->create();

        Livewire::test(EditCountry::class, ['record' => $country->id])
            ->assertSuccessful();
    }

    public function test_can_update_country(): void
    {
        $this->actingAsSuperAdmin();

        $country = Country::factory()->create();

        Livewire::test(EditCountry::class, ['record' => $country->id])
            ->fillForm([
                'name' => 'Updated Country',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'name' => 'Updated Country',
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListCountries::class)
            ->assertSuccessful();
    }
}
