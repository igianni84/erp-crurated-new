<?php

namespace Tests\Feature\Services\Pim;

use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use App\Services\Pim\PimCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PimCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private PimCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PimCacheService::class);
    }

    public function test_get_active_countries_returns_cached_data(): void
    {
        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
            'iso_code_3' => 'FRA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Country::create([
            'name' => 'Inactive',
            'iso_code' => 'XX',
            'iso_code_3' => 'XXX',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $result = $this->service->getActiveCountries();

        $this->assertCount(1, $result);
        $this->assertEquals('France', $result[$country->id]);
    }

    public function test_get_active_countries_serves_from_cache(): void
    {
        Country::create([
            'name' => 'Italy',
            'iso_code' => 'IT',
            'iso_code_3' => 'ITA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // First call populates cache
        $this->service->getActiveCountries();

        // Verify cache key exists
        $this->assertTrue(Cache::has('pim:countries:active'));

        // Second call returns same data from cache
        $result = $this->service->getActiveCountries();
        $this->assertCount(1, $result);
    }

    public function test_get_active_producers_returns_active_only(): void
    {
        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
            'iso_code_3' => 'FRA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $producer = Producer::create([
            'name' => 'Château Margaux',
            'country_id' => $country->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Producer::create([
            'name' => 'Inactive Producer',
            'country_id' => $country->id,
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $result = $this->service->getActiveProducers();

        $this->assertCount(1, $result);
        $this->assertEquals('Château Margaux', $result[$producer->id]);
    }

    public function test_get_regions_for_country(): void
    {
        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
            'iso_code_3' => 'FRA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $parent = Region::create([
            'name' => 'Bordeaux',
            'country_id' => $country->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $child = Region::create([
            'name' => 'Pauillac',
            'country_id' => $country->id,
            'parent_region_id' => $parent->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $result = $this->service->getRegionsForCountry($country->id);

        $this->assertCount(2, $result);
        $this->assertEquals('Bordeaux', $result[$parent->id]);
        $this->assertEquals('Bordeaux > Pauillac', $result[$child->id]);
    }

    public function test_get_appellations_for_country_with_region_filter(): void
    {
        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
            'iso_code_3' => 'FRA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $region = Region::create([
            'name' => 'Bordeaux',
            'country_id' => $country->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $appellationWithRegion = Appellation::create([
            'name' => 'Pauillac AOC',
            'country_id' => $country->id,
            'region_id' => $region->id,
            'system' => 'aoc',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $appellationNoRegion = Appellation::create([
            'name' => 'Vin de France',
            'country_id' => $country->id,
            'region_id' => null,
            'system' => 'igt',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // With region filter: should include region-specific + null region appellations
        $result = $this->service->getAppellationsForCountry($country->id, $region->id);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($appellationWithRegion->id, $result);
        $this->assertArrayHasKey($appellationNoRegion->id, $result);
    }

    public function test_cache_invalidation_on_country_change(): void
    {
        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
            'iso_code_3' => 'FRA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Populate cache
        $this->service->getActiveCountries();
        $this->assertTrue(Cache::has('pim:countries:active'));

        // Update triggers observer -> clears cache
        $country->update(['name' => 'France Updated']);

        $this->assertFalse(Cache::has('pim:countries:active'));
    }

    public function test_cache_invalidation_on_producer_create(): void
    {
        $country = Country::create([
            'name' => 'Italy',
            'iso_code' => 'IT',
            'iso_code_3' => 'ITA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Populate cache
        $this->service->getActiveProducers();
        $this->assertTrue(Cache::has('pim:producers:active'));

        // Creating a new producer clears the cache
        Producer::create([
            'name' => 'New Producer',
            'country_id' => $country->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertFalse(Cache::has('pim:producers:active'));
    }

    public function test_clear_all_clears_all_caches(): void
    {
        $country = Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
            'iso_code_3' => 'FRA',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Populate caches
        $this->service->getActiveCountries();
        $this->service->getActiveProducers();
        $this->service->getRegionsForCountry($country->id);

        $this->service->clearAll();

        $this->assertFalse(Cache::has('pim:countries:active'));
        $this->assertFalse(Cache::has('pim:producers:active'));
        $this->assertFalse(Cache::has("pim:regions:{$country->id}"));
    }
}
