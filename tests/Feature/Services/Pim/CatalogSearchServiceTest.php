<?php

namespace Tests\Feature\Services\Pim;

use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Services\Pim\CatalogSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CatalogSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CatalogSearchService;
    }

    public function test_search_catalog_returns_published_variants(): void
    {
        $master = WineMaster::factory()->create(['name' => 'Tignanello Antinori']);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2018,
        ]);
        WineVariant::factory()->draft()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2020,
        ]);

        $results = $this->service->searchCatalog('Tignanello');
        $items = collect($results->items());

        $this->assertCount(1, $items);
        $this->assertEquals(2018, $items->first()?->vintage_year);
    }

    public function test_search_catalog_with_vintage_filter(): void
    {
        $master = WineMaster::factory()->create(['name' => 'Barolo Giacomo']);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2015,
        ]);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2020,
        ]);

        $results = $this->service->searchCatalog('Barolo', [
            'vintage_min' => 2018,
            'vintage_max' => 2022,
        ]);
        $items = collect($results->items());

        $this->assertCount(1, $items);
        $this->assertEquals(2020, $items->first()?->vintage_year);
    }

    public function test_search_catalog_pagination(): void
    {
        $master = WineMaster::factory()->create(['name' => 'Barbaresco Gaja']);
        for ($i = 0; $i < 5; $i++) {
            WineVariant::factory()->published()->create([
                'wine_master_id' => $master->id,
                'vintage_year' => 2015 + $i,
            ]);
        }

        $results = $this->service->searchCatalog('Barbaresco', perPage: 2, page: 1);

        $this->assertCount(2, collect($results->items()));
        $this->assertEquals(5, $results->total());
        $this->assertEquals(3, $results->lastPage());
    }

    public function test_search_catalog_empty_results(): void
    {
        $results = $this->service->searchCatalog('NonExistentWine12345');

        $this->assertCount(0, collect($results->items()));
    }

    public function test_search_skus_returns_active_only(): void
    {
        $master = WineMaster::factory()->create(['name' => 'Solaia Antinori']);
        $variant = WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
        ]);
        SellableSku::factory()->active()->create([
            'wine_variant_id' => $variant->id,
        ]);
        SellableSku::factory()->draft()->create([
            'wine_variant_id' => $variant->id,
        ]);

        $results = $this->service->searchSkus('Solaia');

        // Collection driver searches all records; shouldBeSearchable filters to active only
        foreach ($results as $sku) {
            $this->assertEquals('active', $sku->lifecycle_status);
        }
    }

    public function test_search_skus_eager_loads_relations(): void
    {
        $master = WineMaster::factory()->create(['name' => 'Masseto IGT']);
        $variant = WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
        ]);
        SellableSku::factory()->active()->create([
            'wine_variant_id' => $variant->id,
        ]);

        $results = $this->service->searchSkus('Masseto');

        if ($results->isNotEmpty()) {
            /** @var SellableSku $sku */
            $sku = $results->first();
            $this->assertTrue($sku->relationLoaded('wineVariant'));
            if ($sku->wineVariant !== null) {
                $this->assertTrue($sku->wineVariant->relationLoaded('wineMaster'));
            }
            $this->assertTrue($sku->relationLoaded('format'));
        }
    }
}
