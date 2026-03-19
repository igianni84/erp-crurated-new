<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class FeatureFlagTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    public function test_api_returns_503_when_feature_disabled(): void
    {
        Feature::define(CustomerApi::class, false);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The Customer API is currently unavailable.');
    }

    public function test_api_works_when_feature_enabled(): void
    {
        Feature::define(CustomerApi::class, true);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        // Should NOT be 503 — the feature flag is active, so the request passes through.
        // We expect 401 (invalid credentials) since the user does not exist.
        $response->assertStatus(401);
    }
}
