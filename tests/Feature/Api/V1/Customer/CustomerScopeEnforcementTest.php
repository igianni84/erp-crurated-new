<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Allocation\Voucher;
use App\Models\Finance\Invoice;
use App\Models\Finance\Subscription;
use App\Models\Fulfillment\ShippingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class CustomerScopeEnforcementTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_cellar_scope(): void
    {
        $authA = $this->createAuthenticatedCustomerUser();
        $authB = $this->createAuthenticatedCustomerUser();

        $voucher = Voucher::factory()->create([
            'customer_id' => $authB['customer']->id,
        ]);

        $response = $this->customerGet("/api/v1/customer/cellar/{$voucher->id}", $authA['token']);

        $response->assertStatus(403);
    }

    public function test_shipping_order_scope(): void
    {
        $authA = $this->createAuthenticatedCustomerUser();
        $authB = $this->createAuthenticatedCustomerUser();

        $shippingOrder = ShippingOrder::factory()->create([
            'customer_id' => $authB['customer']->id,
        ]);

        $response = $this->customerGet("/api/v1/customer/shipping-orders/{$shippingOrder->id}", $authA['token']);

        $response->assertStatus(403);
    }

    public function test_invoice_scope(): void
    {
        $authA = $this->createAuthenticatedCustomerUser();
        $authB = $this->createAuthenticatedCustomerUser();

        $invoice = Invoice::factory()->create([
            'customer_id' => $authB['customer']->id,
        ]);

        $response = $this->customerGet("/api/v1/customer/invoices/{$invoice->id}", $authA['token']);

        $response->assertStatus(403);
    }

    public function test_subscription_scope(): void
    {
        $authA = $this->createAuthenticatedCustomerUser();
        $authB = $this->createAuthenticatedCustomerUser();

        $subscription = Subscription::factory()->create([
            'customer_id' => $authB['customer']->id,
        ]);

        $response = $this->customerGet("/api/v1/customer/subscriptions/{$subscription->id}", $authA['token']);

        $response->assertStatus(403);
    }

    public function test_address_scope_update(): void
    {
        $authA = $this->createAuthenticatedCustomerUser();
        $authB = $this->createAuthenticatedCustomerUser();

        $address = $authB['customer']->addresses()->create([
            'type' => 'shipping',
            'line_1' => '123 Test Street',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB',
            'is_default' => false,
        ]);

        $response = $this->customerPatch(
            "/api/v1/customer/addresses/{$address->id}",
            ['line_1' => 'Hacked Address'],
            $authA['token'],
        );

        $response->assertStatus(403);
    }

    public function test_address_scope_delete(): void
    {
        $authA = $this->createAuthenticatedCustomerUser();
        $authB = $this->createAuthenticatedCustomerUser();

        $address = $authB['customer']->addresses()->create([
            'type' => 'shipping',
            'line_1' => '456 Other Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
            'is_default' => false,
        ]);

        $response = $this->customerDelete("/api/v1/customer/addresses/{$address->id}", $authA['token']);

        $response->assertStatus(403);
    }
}
