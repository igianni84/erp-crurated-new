<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\LiquidProductResource\Pages\CreateLiquidProduct;
use App\Filament\Resources\Pim\LiquidProductResource\Pages\EditLiquidProduct;
use App\Filament\Resources\Pim\LiquidProductResource\Pages\ListLiquidProducts;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\WineVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class LiquidProductResourceTest extends TestCase
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

        Livewire::test(ListLiquidProducts::class)
            ->assertSuccessful();
    }

    public function test_list_shows_liquid_products(): void
    {
        $this->actingAsSuperAdmin();

        $products = LiquidProduct::factory()->count(3)->create();

        Livewire::test(ListLiquidProducts::class)
            ->assertCanSeeTableRecords($products);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateLiquidProduct::class)
            ->assertSuccessful();
    }

    public function test_can_create_liquid_product(): void
    {
        $this->actingAsSuperAdmin();

        $wineVariant = WineVariant::factory()->create();

        Livewire::test(CreateLiquidProduct::class)
            ->fillForm([
                'wine_variant_id' => $wineVariant->id,
                'lifecycle_status' => 'draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('liquid_products', [
            'wine_variant_id' => $wineVariant->id,
            'lifecycle_status' => 'draft',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateLiquidProduct::class)
            ->fillForm([
                'wine_variant_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['wine_variant_id' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $product = LiquidProduct::factory()->create();

        Livewire::test(EditLiquidProduct::class, ['record' => $product->id])
            ->assertSuccessful();
    }

    public function test_can_update_liquid_product(): void
    {
        $this->actingAsSuperAdmin();

        $product = LiquidProduct::factory()->create();

        Livewire::test(EditLiquidProduct::class, ['record' => $product->id])
            ->fillForm([
                'lifecycle_status' => 'published',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('liquid_products', [
            'id' => $product->id,
            'lifecycle_status' => 'published',
        ]);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListLiquidProducts::class)
            ->assertSuccessful();
    }
}
