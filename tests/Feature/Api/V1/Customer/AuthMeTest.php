<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\Customer\CustomerUserStatus;
use App\Features\CustomerApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class AuthMeTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_me_returns_user_data(): void
    {
        $auth = $this->createAuthenticatedCustomerUser();

        $response = $this->customerGet('/api/v1/customer/auth/me', $auth['token']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User profile retrieved.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'status',
                    'customer',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.id', $auth['customer_user']->id)
            ->assertJsonPath('data.name', $auth['customer_user']->name)
            ->assertJsonPath('data.email', $auth['customer_user']->email);
    }

    public function test_me_includes_customer_and_party(): void
    {
        $auth = $this->createAuthenticatedCustomerUser();
        $customer = $auth['customer'];
        $party = $customer->party;
        $this->assertNotNull($party);

        $response = $this->customerGet('/api/v1/customer/auth/me', $auth['token']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.customer.party.legal_name', $party->legal_name);
    }

    public function test_me_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/customer/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_suspended_after_login(): void
    {
        $auth = $this->createAuthenticatedCustomerUser();

        // Verify the token works before suspension
        $response = $this->customerGet('/api/v1/customer/auth/me', $auth['token']);
        $response->assertStatus(200);

        // Suspend the user after they already have a token
        $auth['customer_user']->update(['status' => CustomerUserStatus::Suspended]);

        // Reset the auth guard cache so Sanctum re-resolves the user from DB
        $this->app['auth']->forgetGuards();

        // The EnsureCustomerActive middleware should block the request
        $response = $this->customerGet('/api/v1/customer/auth/me', $auth['token']);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your account has been suspended. Please contact support.');
    }
}
