<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\Customer\AddressType;
use App\Features\CustomerApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class AddressTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_index_returns_addresses(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $customer->addresses()->create([
            'type' => AddressType::Billing,
            'line_1' => '10 Billing St',
            'city' => 'Rome',
            'postal_code' => '00100',
            'country' => 'IT',
            'is_default' => false,
        ]);
        $customer->addresses()->create([
            'type' => AddressType::Shipping,
            'line_1' => '20 Shipping Ave',
            'city' => 'Milan',
            'postal_code' => '20100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerGet('/api/v1/customer/addresses', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Addresses retrieved.')
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_address(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerPost('/api/v1/customer/addresses', [
            'type' => 'billing',
            'line_1' => '123 New Street',
            'city' => 'Florence',
            'postal_code' => '50100',
            'country' => 'IT',
        ], $token);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Address created.')
            ->assertJsonPath('data.type', 'billing')
            ->assertJsonPath('data.line_1', '123 New Street')
            ->assertJsonPath('data.city', 'Florence');
    }

    public function test_store_validates_required_fields(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerPost('/api/v1/customer/addresses', [], $token);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'line_1', 'city', 'postal_code', 'country']);
    }

    public function test_update_address(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $address = $customer->addresses()->create([
            'type' => AddressType::Billing,
            'line_1' => 'Old Street',
            'city' => 'Rome',
            'postal_code' => '00100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerPatch("/api/v1/customer/addresses/{$address->id}", [
            'line_1' => 'New Street',
        ], $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Address updated.')
            ->assertJsonPath('data.line_1', 'New Street');
    }

    public function test_update_scope_enforcement(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        // Create address for a different customer
        ['customer' => $otherCustomer] = $this->createAuthenticatedCustomerUser();
        $otherAddress = $otherCustomer->addresses()->create([
            'type' => AddressType::Billing,
            'line_1' => 'Other Street',
            'city' => 'Naples',
            'postal_code' => '80100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerPatch("/api/v1/customer/addresses/{$otherAddress->id}", [
            'line_1' => 'Hacked Street',
        ], $token);

        $response->assertStatus(403);
    }

    public function test_destroy_address(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $address = $customer->addresses()->create([
            'type' => AddressType::Shipping,
            'line_1' => 'Temporary St',
            'city' => 'Turin',
            'postal_code' => '10100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerDelete("/api/v1/customer/addresses/{$address->id}", $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Address deleted.');
    }

    public function test_destroy_scope_enforcement(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        ['customer' => $otherCustomer] = $this->createAuthenticatedCustomerUser();
        $otherAddress = $otherCustomer->addresses()->create([
            'type' => AddressType::Billing,
            'line_1' => 'Other Street',
            'city' => 'Naples',
            'postal_code' => '80100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerDelete("/api/v1/customer/addresses/{$otherAddress->id}", $token);

        $response->assertStatus(403);
    }

    public function test_store_sets_default(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $response = $this->customerPost('/api/v1/customer/addresses', [
            'type' => 'shipping',
            'line_1' => '99 Default Lane',
            'city' => 'Venice',
            'postal_code' => '30100',
            'country' => 'IT',
            'is_default' => true,
        ], $token);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_default', true);
    }
}
