<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Finance\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class SubscriptionTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_index_returns_subscriptions(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Subscription::factory()->count(2)->create(['customer_id' => $customer->id]);

        $response = $this->customerGet('/api/v1/customer/subscriptions', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscriptions retrieved.')
            ->assertJsonCount(2, 'data');
    }

    public function test_index_scoped_to_customer(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Subscription::factory()->count(2)->create(['customer_id' => $customer->id]);
        Subscription::factory()->count(3)->create(); // other customer

        $response = $this->customerGet('/api/v1/customer/subscriptions', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_show_returns_subscription(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $subscription = Subscription::factory()->create(['customer_id' => $customer->id]);

        $response = $this->customerGet("/api/v1/customer/subscriptions/{$subscription->id}", $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscription retrieved.')
            ->assertJsonPath('data.id', $subscription->id);
    }

    public function test_show_scope_enforcement(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $otherSubscription = Subscription::factory()->create(); // belongs to another customer

        $response = $this->customerGet("/api/v1/customer/subscriptions/{$otherSubscription->id}", $token);

        $response->assertStatus(403);
    }
}
