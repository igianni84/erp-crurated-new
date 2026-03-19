<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class AuthLoginTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_login_success(): void
    {
        $customer = Customer::factory()->active()->create();
        CustomerUser::factory()->create([
            'customer_id' => $customer->id,
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_login_returns_token_and_user_data(): void
    {
        $customer = Customer::factory()->active()->create();
        $customerUser = CustomerUser::factory()->create([
            'customer_id' => $customer->id,
            'email' => 'tokenuser@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'tokenuser@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'status',
                        'customer',
                        'created_at',
                    ],
                ],
            ])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $customerUser->id)
            ->assertJsonPath('data.user.email', 'tokenuser@example.com');
    }

    public function test_login_invalid_password(): void
    {
        $customer = Customer::factory()->active()->create();
        CustomerUser::factory()->create([
            'customer_id' => $customer->id,
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_email_not_found(): void
    {
        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_suspended_user(): void
    {
        $customer = Customer::factory()->active()->create();
        CustomerUser::factory()->suspended()->create([
            'customer_id' => $customer->id,
            'email' => 'suspended@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your account has been suspended. Please contact support.');
    }

    public function test_login_suspended_customer(): void
    {
        $customer = Customer::factory()->suspended()->create();
        CustomerUser::factory()->create([
            'customer_id' => $customer->id,
            'email' => 'custsusp@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'custsusp@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your customer account is not active. Please contact support.');
    }

    public function test_login_deactivated_user(): void
    {
        $customer = Customer::factory()->active()->create();
        CustomerUser::factory()->deactivated()->create([
            'customer_id' => $customer->id,
            'email' => 'deactivated@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'deactivated@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your account has been deactivated. Please contact support.');
    }

    public function test_login_rate_limiting(): void
    {
        $customer = Customer::factory()->active()->create();
        CustomerUser::factory()->create([
            'customer_id' => $customer->id,
            'email' => 'ratelimit@example.com',
            'password' => 'password',
        ]);

        // The rate limiter allows 5 per minute. Send 5 failed attempts.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/customer/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6th request should be rate-limited
        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_login_missing_fields(): void
    {
        // Missing both fields
        $response = $this->postJson('/api/v1/customer/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        // Missing password only
        $response = $this->postJson('/api/v1/customer/auth/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Missing email only
        $response = $this->postJson('/api/v1/customer/auth/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_feature_flag_disabled(): void
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
}
