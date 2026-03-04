<?php

namespace Tests\Feature\Filament\Pim;

use App\Enums\Pim\AppellationSystem;
use App\Filament\Resources\Pim\AppellationResource\Pages\CreateAppellation;
use App\Filament\Resources\Pim\AppellationResource\Pages\EditAppellation;
use App\Filament\Resources\Pim\AppellationResource\Pages\ListAppellations;
use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class AppellationResourceTest extends TestCase
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

        Livewire::test(ListAppellations::class)
            ->assertSuccessful();
    }

    public function test_list_shows_appellations(): void
    {
        $this->actingAsSuperAdmin();

        $appellations = Appellation::factory()->count(3)->create();

        Livewire::test(ListAppellations::class)
            ->assertCanSeeTableRecords($appellations);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateAppellation::class)
            ->assertSuccessful();
    }

    public function test_can_create_appellation(): void
    {
        $this->actingAsSuperAdmin();

        $country = Country::factory()->create();

        Livewire::test(CreateAppellation::class)
            ->fillForm([
                'name' => 'Test Appellation',
                'country_id' => $country->id,
                'system' => AppellationSystem::DOC->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('appellations', [
            'name' => 'Test Appellation',
            'country_id' => $country->id,
            'system' => AppellationSystem::DOC->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateAppellation::class)
            ->fillForm([
                'name' => null,
                'country_id' => null,
                'system' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'country_id' => 'required', 'system' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $appellation = Appellation::factory()->create();

        Livewire::test(EditAppellation::class, ['record' => $appellation->id])
            ->assertSuccessful();
    }

    public function test_can_update_appellation(): void
    {
        $this->actingAsSuperAdmin();

        $appellation = Appellation::factory()->create();

        Livewire::test(EditAppellation::class, ['record' => $appellation->id])
            ->fillForm([
                'name' => 'Updated Appellation',
                'system' => AppellationSystem::DOCG->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('appellations', [
            'id' => $appellation->id,
            'name' => 'Updated Appellation',
            'system' => AppellationSystem::DOCG->value,
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListAppellations::class)
            ->assertSuccessful();
    }
}
