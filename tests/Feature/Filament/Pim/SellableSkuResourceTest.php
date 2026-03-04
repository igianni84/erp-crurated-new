<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\SellableSkuResource\Pages\CreateSellableSku;
use App\Filament\Resources\Pim\SellableSkuResource\Pages\EditSellableSku;
use App\Filament\Resources\Pim\SellableSkuResource\Pages\ListSellableSkus;
use App\Models\Pim\SellableSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class SellableSkuResourceTest extends TestCase
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

        Livewire::test(ListSellableSkus::class)
            ->assertSuccessful();
    }

    public function test_list_shows_sellable_skus(): void
    {
        $this->actingAsSuperAdmin();

        $skus = SellableSku::factory()->count(3)->create();

        Livewire::test(ListSellableSkus::class)
            ->assertCanSeeTableRecords($skus);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateSellableSku::class)
            ->assertSuccessful();
    }

    public function test_can_create_sellable_sku(): void
    {
        $this->actingAsSuperAdmin();

        // Create fresh dependencies (not from createPimStack which also creates a SKU)
        $wineVariant = \App\Models\Pim\WineVariant::factory()->create();
        $format = \App\Models\Pim\Format::factory()->standard()->create();
        $caseConfig = \App\Models\Pim\CaseConfiguration::factory()->create(['format_id' => $format->id]);

        Livewire::test(CreateSellableSku::class)
            ->fillForm([
                'wine_variant_id' => $wineVariant->id,
                'format_id' => $format->id,
                'case_configuration_id' => $caseConfig->id,
                'lifecycle_status' => 'draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sellable_skus', [
            'wine_variant_id' => $wineVariant->id,
            'format_id' => $format->id,
            'case_configuration_id' => $caseConfig->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateSellableSku::class)
            ->fillForm([
                'wine_variant_id' => null,
                'format_id' => null,
                'case_configuration_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['wine_variant_id' => 'required', 'format_id' => 'required', 'case_configuration_id' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $sku = SellableSku::factory()->create();

        Livewire::test(EditSellableSku::class, ['record' => $sku->id])
            ->assertSuccessful();
    }

    public function test_can_update_sellable_sku(): void
    {
        $this->actingAsSuperAdmin();

        // Use createPimStack for properly linked format → case_configuration
        $pim = $this->createPimStack();
        $sku = $pim['sellable_sku'];

        Livewire::test(EditSellableSku::class, ['record' => $sku->id])
            ->fillForm([
                'lifecycle_status' => 'active',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sellable_skus', [
            'id' => $sku->id,
            'lifecycle_status' => 'active',
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListSellableSkus::class)
            ->assertSuccessful();
    }
}
