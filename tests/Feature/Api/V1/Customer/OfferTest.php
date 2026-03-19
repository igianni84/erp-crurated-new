<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Commercial\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class OfferTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_offers_index_returns_active_offers(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        Offer::factory()->active()->count(2)->create([
            'valid_from' => now()->subDay(),
        ]);
        Offer::factory()->create(); // Draft — should not appear

        $response = $this->customerGet('/api/v1/customer/offers', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Offers retrieved.')
            ->assertJsonCount(2, 'data');
    }

    public function test_offers_index_excludes_expired(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        Offer::factory()->active()->create([
            'valid_from' => now()->subDays(30),
            'valid_to' => now()->subDay(), // expired yesterday
        ]);
        Offer::factory()->active()->create([
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(), // still valid
        ]);

        $response = $this->customerGet('/api/v1/customer/offers', $token);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_offers_show_returns_offer(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $offer = Offer::factory()->active()->create([
            'valid_from' => now()->subDay(),
        ]);

        $response = $this->customerGet("/api/v1/customer/offers/{$offer->id}", $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Offer retrieved.')
            ->assertJsonPath('data.id', $offer->id);
    }

    public function test_offers_show_hides_inactive(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $offer = Offer::factory()->create(); // Draft status

        $response = $this->customerGet("/api/v1/customer/offers/{$offer->id}", $token);

        $response->assertStatus(404);
    }

    public function test_offers_index_pagination(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        Offer::factory()->active()->count(5)->create([
            'valid_from' => now()->subDay(),
        ]);

        $response = $this->customerGet('/api/v1/customer/offers', $token);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.total', 5);
    }

    public function test_offers_index_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/customer/offers');

        $response->assertStatus(401);
    }
}
