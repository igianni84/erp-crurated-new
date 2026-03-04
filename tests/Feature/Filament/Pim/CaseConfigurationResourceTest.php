<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\CaseConfigurationResource\Pages\CreateCaseConfiguration;
use App\Filament\Resources\Pim\CaseConfigurationResource\Pages\EditCaseConfiguration;
use App\Filament\Resources\Pim\CaseConfigurationResource\Pages\ListCaseConfigurations;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class CaseConfigurationResourceTest extends TestCase
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

        Livewire::test(ListCaseConfigurations::class)
            ->assertSuccessful();
    }

    public function test_list_shows_case_configurations(): void
    {
        $this->actingAsSuperAdmin();

        $configs = CaseConfiguration::factory()->count(3)->create();

        Livewire::test(ListCaseConfigurations::class)
            ->assertCanSeeTableRecords($configs);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCaseConfiguration::class)
            ->assertSuccessful();
    }

    public function test_can_create_case_configuration(): void
    {
        $this->actingAsSuperAdmin();

        $format = Format::factory()->create();

        Livewire::test(CreateCaseConfiguration::class)
            ->fillForm([
                'name' => '6x750ml OWC',
                'format_id' => $format->id,
                'bottles_per_case' => 6,
                'case_type' => 'owc',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('case_configurations', [
            'name' => '6x750ml OWC',
            'format_id' => $format->id,
            'bottles_per_case' => 6,
            'case_type' => 'owc',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCaseConfiguration::class)
            ->fillForm([
                'name' => null,
                'format_id' => null,
                'bottles_per_case' => null,
                'case_type' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'format_id' => 'required', 'bottles_per_case' => 'required', 'case_type' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $config = CaseConfiguration::factory()->create();

        Livewire::test(EditCaseConfiguration::class, ['record' => $config->id])
            ->assertSuccessful();
    }

    public function test_can_update_case_configuration(): void
    {
        $this->actingAsSuperAdmin();

        $config = CaseConfiguration::factory()->create();

        Livewire::test(EditCaseConfiguration::class, ['record' => $config->id])
            ->fillForm([
                'name' => 'Updated Config',
                'bottles_per_case' => 12,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('case_configurations', [
            'id' => $config->id,
            'name' => 'Updated Config',
            'bottles_per_case' => 12,
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListCaseConfigurations::class)
            ->assertSuccessful();
    }
}
