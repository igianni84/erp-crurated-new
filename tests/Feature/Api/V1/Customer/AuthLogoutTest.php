<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Customer\CustomerUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class AuthLogoutTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_logout_success(): void
    {
        $auth = $this->createAuthenticatedCustomerUser();

        // Logout
        $response = $this->customerPost('/api/v1/customer/auth/logout', [], $auth['token']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out successfully.');

        // Verify the token was deleted from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $auth['customer_user']->id,
            'tokenable_type' => CustomerUser::class,
        ]);
    }

    public function test_logout_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/customer/auth/logout');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $auth = $this->createAuthenticatedCustomerUser();
        $customerUser = $auth['customer_user'];

        // Create a second token
        $secondToken = $customerUser->createToken('second-token')->plainTextToken;

        // Logout using the first token
        $response = $this->customerPost('/api/v1/customer/auth/logout', [], $auth['token']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Only one token should remain (the second one)
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Reset the auth guard cache so Sanctum re-resolves tokens
        $this->app['auth']->forgetGuards();

        // Second token should still work
        $response = $this->customerGet('/api/v1/customer/auth/me', $secondToken);
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
