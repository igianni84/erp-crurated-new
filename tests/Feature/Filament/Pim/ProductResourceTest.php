<?php

namespace Tests\Feature\Filament\Pim;

use App\Filament\Resources\Pim\ProductResource\Pages\ListProducts;
use App\Models\Pim\WineVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ProductResourceTest extends TestCase
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

        Livewire::test(ListProducts::class)
            ->assertSuccessful();
    }

    public function test_list_shows_products(): void
    {
        $this->actingAsSuperAdmin();

        $products = WineVariant::factory()->count(3)->create();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords($products);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListProducts::class)
            ->assertSuccessful();
    }
}
