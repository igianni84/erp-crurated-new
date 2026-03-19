<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class ProfileTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_show_profile(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerGet('/api/v1/customer/profile', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile retrieved.');
    }

    public function test_update_profile(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerPatch('/api/v1/customer/profile', [
            'name' => 'New Name',
        ], $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_update_profile_validation(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerPatch('/api/v1/customer/profile', [], $token);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_profile_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/customer/profile');

        $response->assertStatus(401);
    }
}
