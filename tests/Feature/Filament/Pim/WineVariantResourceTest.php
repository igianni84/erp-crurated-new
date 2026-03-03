<?php

namespace Tests\Feature\Filament\Pim;

use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\WineVariantResource\Pages\CreateWineVariant;
use App\Filament\Resources\Pim\WineVariantResource\Pages\EditWineVariant;
use App\Filament\Resources\Pim\WineVariantResource\Pages\ListWineVariants;
use App\Filament\Resources\Pim\WineVariantResource\Pages\ViewWineVariant;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class WineVariantResourceTest extends TestCase
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

        Livewire::test(ListWineVariants::class)
            ->assertSuccessful();
    }

    public function test_list_shows_wine_variants(): void
    {
        $this->actingAsSuperAdmin();

        $variants = WineVariant::factory()->count(3)->create();

        Livewire::test(ListWineVariants::class)
            ->assertCanSeeTableRecords($variants);
    }

    public function test_list_can_filter_by_lifecycle_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = WineVariant::factory()->draft()->create();
        $published = WineVariant::factory()->published()->create();

        Livewire::test(ListWineVariants::class)
            ->filterTable('lifecycle_status', ProductLifecycleStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$published]);
    }

    public function test_list_can_search_by_wine_master_name(): void
    {
        $this->actingAsSuperAdmin();

        $master = WineMaster::factory()->create(['name' => 'Chateau Margaux Grand Vin']);
        $target = WineVariant::factory()->create(['wine_master_id' => $master->id]);

        $otherMaster = WineMaster::factory()->create(['name' => 'Opus One']);
        $other = WineVariant::factory()->create(['wine_master_id' => $otherMaster->id]);

        Livewire::test(ListWineVariants::class)
            ->searchTable('Chateau Margaux')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateWineVariant::class)
            ->assertSuccessful();
    }

    public function test_can_create_wine_variant(): void
    {
        $this->actingAsSuperAdmin();

        $wineMaster = WineMaster::factory()->create();

        Livewire::test(CreateWineVariant::class)
            ->fillForm([
                'wine_master_id' => $wineMaster->id,
                'vintage_year' => 2020,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wine_variants', [
            'wine_master_id' => $wineMaster->id,
            'vintage_year' => 2020,
        ]);
    }

    public function test_create_validates_required_wine_master(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateWineVariant::class)
            ->fillForm([
                'wine_master_id' => null,
                'vintage_year' => 2020,
            ])
            ->call('create')
            ->assertHasFormErrors(['wine_master_id' => 'required']);
    }

    public function test_create_validates_required_vintage_year(): void
    {
        $this->actingAsSuperAdmin();

        $wineMaster = WineMaster::factory()->create();

        Livewire::test(CreateWineVariant::class)
            ->fillForm([
                'wine_master_id' => $wineMaster->id,
                'vintage_year' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['vintage_year' => 'required']);
    }

    public function test_create_validates_vintage_year_range(): void
    {
        $this->actingAsSuperAdmin();

        $wineMaster = WineMaster::factory()->create();

        Livewire::test(CreateWineVariant::class)
            ->fillForm([
                'wine_master_id' => $wineMaster->id,
                'vintage_year' => 1500,
            ])
            ->call('create')
            ->assertHasFormErrors(['vintage_year']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $variant = WineVariant::factory()->create();

        Livewire::test(EditWineVariant::class, ['record' => $variant->id])
            ->assertSuccessful();
    }

    public function test_can_update_wine_variant(): void
    {
        $this->actingAsSuperAdmin();

        $variant = WineVariant::factory()->create(['vintage_year' => 2018]);

        Livewire::test(EditWineVariant::class, ['record' => $variant->id])
            ->fillForm([
                'vintage_year' => 2019,
                'alcohol_percentage' => 13.5,
                'description' => 'An exceptional vintage.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wine_variants', [
            'id' => $variant->id,
            'vintage_year' => 2019,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $variant = WineVariant::factory()->create();

        Livewire::test(ViewWineVariant::class, ['record' => $variant->id])
            ->assertSuccessful();
    }
}
