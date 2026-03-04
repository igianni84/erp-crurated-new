<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\FormatResource\Pages\CreateFormat;
use App\Filament\Resources\Pim\FormatResource\Pages\EditFormat;
use App\Filament\Resources\Pim\FormatResource\Pages\ListFormats;
use App\Models\Pim\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class FormatResourceTest extends TestCase
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

        Livewire::test(ListFormats::class)
            ->assertSuccessful();
    }

    public function test_list_shows_formats(): void
    {
        $this->actingAsSuperAdmin();

        $formats = Format::factory()->count(3)->create();

        Livewire::test(ListFormats::class)
            ->assertCanSeeTableRecords($formats);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateFormat::class)
            ->assertSuccessful();
    }

    public function test_can_create_format(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateFormat::class)
            ->fillForm([
                'name' => 'Test Format',
                'volume_ml' => 750,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('formats', [
            'name' => 'Test Format',
            'volume_ml' => 750,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateFormat::class)
            ->fillForm([
                'name' => null,
                'volume_ml' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'volume_ml' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $format = Format::factory()->create();

        Livewire::test(EditFormat::class, ['record' => $format->id])
            ->assertSuccessful();
    }

    public function test_can_update_format(): void
    {
        $this->actingAsSuperAdmin();

        $format = Format::factory()->create();

        Livewire::test(EditFormat::class, ['record' => $format->id])
            ->fillForm([
                'name' => 'Updated Format',
                'volume_ml' => 1500,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('formats', [
            'id' => $format->id,
            'name' => 'Updated Format',
            'volume_ml' => 1500,
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListFormats::class)
            ->assertSuccessful();
    }
}
