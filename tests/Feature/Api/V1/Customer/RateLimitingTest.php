<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class RateLimitingTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_login_rate_limiting(): void
    {
        $customer = Customer::factory()->active()->create();
        CustomerUser::factory()->create([
            'customer_id' => $customer->id,
            'email' => 'ratelimit@example.com',
            'password' => 'password',
        ]);

        // The customer-login rate limiter allows 5 requests per minute.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/customer/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // The 6th request should be rate-limited.
        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
