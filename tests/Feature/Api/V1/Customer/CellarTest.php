<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Features\CustomerApi;
use App\Models\Allocation\Voucher;
use App\Models\Pim\SellableSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Tests\Traits\CustomerApiTestHelper;

class CellarTest extends TestCase
{
    use CustomerApiTestHelper;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::define(CustomerApi::class, true);
    }

    public function test_cellar_index_returns_vouchers(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Voucher::factory()->count(3)->create(['customer_id' => $customer->id]);

        $response = $this->customerGet('/api/v1/customer/cellar', $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Cellar retrieved.')
            ->assertJsonCount(3, 'data');
    }

    public function test_cellar_index_scoped_to_customer(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Voucher::factory()->count(2)->create(['customer_id' => $customer->id]);
        Voucher::factory()->count(3)->create(); // other customer's vouchers

        $response = $this->customerGet('/api/v1/customer/cellar', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_cellar_index_filter_by_lifecycle_state(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Voucher::factory()->count(2)->create(['customer_id' => $customer->id]); // Issued (default)
        Voucher::factory()->locked()->create(['customer_id' => $customer->id]);

        $response = $this->customerGet('/api/v1/customer/cellar?lifecycle_state=issued', $token);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_cellar_index_pagination(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $sku = SellableSku::factory()->create();

        Voucher::factory()->count(25)->create([
            'customer_id' => $customer->id,
            'wine_variant_id' => $sku->wine_variant_id,
            'format_id' => $sku->format_id,
            'sellable_sku_id' => $sku->id,
        ]);

        $response = $this->customerGet('/api/v1/customer/cellar', $token);

        $response->assertStatus(200)
            ->assertJsonCount(20, 'data');
    }

    public function test_cellar_show_returns_voucher(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        $voucher = Voucher::factory()->create(['customer_id' => $customer->id]);

        $response = $this->customerGet("/api/v1/customer/cellar/{$voucher->id}", $token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Voucher retrieved.')
            ->assertJsonPath('data.id', $voucher->id);
    }

    public function test_cellar_show_scope_enforcement(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $otherVoucher = Voucher::factory()->create(); // belongs to another customer

        $response = $this->customerGet("/api/v1/customer/cellar/{$otherVoucher->id}", $token);

        $response->assertStatus(403);
    }

    public function test_cellar_show_not_found(): void
    {
        ['token' => $token] = $this->createAuthenticatedCustomerUser();

        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->customerGet("/api/v1/customer/cellar/{$fakeUuid}", $token);

        $response->assertStatus(404);
    }

    public function test_cellar_index_includes_wine_data(): void
    {
        ['customer' => $customer, 'token' => $token] = $this->createAuthenticatedCustomerUser();

        Voucher::factory()->create(['customer_id' => $customer->id]);

        $response = $this->customerGet('/api/v1/customer/cellar', $token);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'lifecycle_state',
                        'wine_variant',
                        'format',
                    ],
                ],
            ]);
    }
}
