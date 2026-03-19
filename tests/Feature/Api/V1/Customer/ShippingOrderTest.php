<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Customer\AddressType;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Features\CustomerApi;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\ShippingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class ShippingOrderTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_index_returns_orders(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        ShippingOrder::factory()->count(2)->create(['customer_id' => $customer->id]);

        $response = $this->customerGet('/api/v1/customer/shipping-orders', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Shipping orders retrieved.')
            ->assertJsonCount(2, 'data');
    }

    public function test_index_scoped_to_customer(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        ShippingOrder::factory()->count(2)->create(['customer_id' => $customer->id]);
        ShippingOrder::factory()->count(3)->create(); // other customer

        $response = $this->customerGet('/api/v1/customer/shipping-orders', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filter_by_status(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        ShippingOrder::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'status' => ShippingOrderStatus::Draft,
        ]);
        ShippingOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => ShippingOrderStatus::Planned,
        ]);

        $response = $this->customerGet('/api/v1/customer/shipping-orders?status=draft', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_show_returns_order_with_lines(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $order = ShippingOrder::factory()->create(['customer_id' => $customer->id]);

        $response = $this->customerGet("/api/v1/customer/shipping-orders/{$order->id}", $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Shipping order retrieved.')
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_show_scope_enforcement(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $otherOrder = ShippingOrder::factory()->create(); // belongs to another customer

        $response = $this->customerGet("/api/v1/customer/shipping-orders/{$otherOrder->id}", $token);

        $response->assertStatus(403);
    }

    public function test_store_creates_order(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $vouchers = Voucher::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $address = $customer->addresses()->create([
            'type' => AddressType::Shipping,
            'line_1' => '123 Wine Street',
            'city' => 'Milan',
            'postal_code' => '20100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerPost('/api/v1/customer/shipping-orders', [
            'voucher_ids' => $vouchers->pluck('id')->toArray(),
            'shipping_address_id' => $address->id,
        ], $token);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Shipping order created.');
    }

    public function test_store_validates_voucher_ownership(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $otherVoucher = Voucher::factory()->create(); // belongs to another customer

        $address = $customer->addresses()->create([
            'type' => AddressType::Shipping,
            'line_1' => '123 Wine Street',
            'city' => 'Milan',
            'postal_code' => '20100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerPost('/api/v1/customer/shipping-orders', [
            'voucher_ids' => [$otherVoucher->id],
            'shipping_address_id' => $address->id,
        ], $token);

        $response->assertStatus(422);
    }

    public function test_store_validates_voucher_state(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $voucher = Voucher::factory()->redeemed()->create([
            'customer_id' => $customer->id,
        ]);

        $address = $customer->addresses()->create([
            'type' => AddressType::Shipping,
            'line_1' => '123 Wine Street',
            'city' => 'Milan',
            'postal_code' => '20100',
            'country' => 'IT',
            'is_default' => false,
        ]);

        $response = $this->customerPost('/api/v1/customer/shipping-orders', [
            'voucher_ids' => [$voucher->id],
            'shipping_address_id' => $address->id,
        ], $token);

        $response->assertStatus(422);
    }
}
