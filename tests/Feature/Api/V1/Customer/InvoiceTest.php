<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Finance\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class InvoiceTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_index_returns_invoices(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Invoice::factory()->count(3)->create(['customer_id' => $customer->id]);

        $response = $this->customerGet('/api/v1/customer/invoices', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Invoices retrieved.')
            ->assertJsonCount(3, 'data');
    }

    public function test_index_scoped_to_customer(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Invoice::factory()->count(2)->create(['customer_id' => $customer->id]);
        Invoice::factory()->count(3)->create(); // other customer

        $response = $this->customerGet('/api/v1/customer/invoices', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filter_by_status(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Invoice::factory()->issued()->count(2)->create(['customer_id' => $customer->id]);
        Invoice::factory()->create(['customer_id' => $customer->id]); // Draft

        $response = $this->customerGet('/api/v1/customer/invoices?status=issued', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filter_by_type(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Invoice::factory()->membership()->count(2)->create(['customer_id' => $customer->id]);
        Invoice::factory()->create(['customer_id' => $customer->id]); // VoucherSale (default)

        $response = $this->customerGet('/api/v1/customer/invoices?invoice_type=membership_service', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_show_returns_invoice_with_lines(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        $response = $this->customerGet("/api/v1/customer/invoices/{$invoice->id}", $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Invoice retrieved.')
            ->assertJsonPath('data.id', $invoice->id);
    }

    public function test_show_scope_enforcement(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $otherInvoice = Invoice::factory()->create(); // belongs to another customer

        $response = $this->customerGet("/api/v1/customer/invoices/{$otherInvoice->id}", $token);

        $response->assertStatus(403);
    }

    public function test_show_amounts_as_strings(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subtotal' => '500.00',
            'tax_amount' => '100.00',
            'total_amount' => '600.00',
            'amount_paid' => '0.00',
        ]);

        $response = $this->customerGet("/api/v1/customer/invoices/{$invoice->id}", $token);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertIsString($data['subtotal']);
        $this->assertIsString($data['tax_amount']);
        $this->assertIsString($data['total_amount']);
        $this->assertIsString($data['amount_paid']);
    }
}
