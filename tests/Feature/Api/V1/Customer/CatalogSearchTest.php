<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CatalogSearch;
use App\Features\CustomerApi;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class CatalogSearchTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
        Feature::define(CatalogSearch::class, true);
    }

    public function test_search_requires_query_parameter(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerGet('/api/v1/customer/catalog/search', $token);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }

    public function test_search_requires_minimum_query_length(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=a', $token);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }

    public function test_search_returns_matching_wines(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $master = WineMaster::factory()->create(['name' => 'Sassicaia Bolgheri']);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2018,
        ]);

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=Sassicaia', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Catalog search results.')
            ->assertJsonCount(1, 'data');
    }

    public function test_search_excludes_draft_variants(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $master = WineMaster::factory()->create(['name' => 'Barolo Riserva']);
        WineVariant::factory()->draft()->create([
            'wine_master_id' => $master->id,
        ]);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
        ]);

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=Barolo', $token);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_search_filters_by_country(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $italianMaster = WineMaster::factory()->create([
            'name' => 'Brunello di Montalcino',
            'country' => 'Italy',
        ]);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $italianMaster->id,
        ]);

        $frenchMaster = WineMaster::factory()->create([
            'name' => 'Brunello Fake French',
            'country' => 'France',
        ]);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $frenchMaster->id,
        ]);

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=Brunello&country=Italy', $token);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_search_filters_by_vintage_range(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $master = WineMaster::factory()->create(['name' => 'Amarone della Valpolicella']);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2015,
        ]);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2020,
        ]);

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=Amarone&vintage_min=2018&vintage_max=2022', $token);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_search_pagination(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $master = WineMaster::factory()->create(['name' => 'Chianti Classico']);
        for ($i = 0; $i < 5; $i++) {
            WineVariant::factory()->published()->create([
                'wine_master_id' => $master->id,
                'vintage_year' => 2015 + $i,
            ]);
        }

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=Chianti&per_page=2', $token);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonCount(2, 'data');
    }

    public function test_search_returns_503_when_feature_disabled(): void
    {
        Feature::define(CatalogSearch::class, false);

        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=Sassicaia', $token);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Catalog search is currently unavailable.');
    }

    public function test_search_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/customer/catalog/search?q=Sassicaia');

        $response->assertStatus(401);
    }

    public function test_search_response_structure(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $master = WineMaster::factory()->create(['name' => 'Ornellaia Bolgheri']);
        WineVariant::factory()->published()->create([
            'wine_master_id' => $master->id,
            'vintage_year' => 2019,
        ]);

        $response = $this->customerGet('/api/v1/customer/catalog/search?q=Ornellaia', $token);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'wine_name',
                        'producer',
                        'vintage_year',
                        'country',
                        'region',
                        'appellation',
                        'formats',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }
}
