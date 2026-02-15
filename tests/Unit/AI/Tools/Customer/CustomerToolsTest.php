<?php

namespace Tests\Unit\AI\Tools\Customer;

use App\AI\Tools\Customer\CustomerSearchTool;
use App\AI\Tools\Customer\CustomerStatusSummaryTool;
use App\AI\Tools\Customer\CustomerVoucherCountTool;
use App\AI\Tools\Customer\TopCustomersByRevenueTool;
use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Enums\Customer\PartyType;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\UserRole;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Customer\Party;
use App\Models\Finance\Invoice;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CustomerToolsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create a customer with optional party relationship.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCustomer(array $overrides = []): Customer
    {
        $defaults = [
            'name' => 'Test Customer',
            'email' => 'test-'.uniqid().'@example.com',
            'status' => CustomerStatus::Active,
            'customer_type' => CustomerType::B2C,
        ];

        return Customer::create(array_merge($defaults, $overrides));
    }

    /**
     * Create a customer with an associated party.
     *
     * @param  array<string, mixed>  $customerOverrides
     * @param  array<string, mixed>  $partyOverrides
     */
    private function createCustomerWithParty(array $customerOverrides = [], array $partyOverrides = []): Customer
    {
        $party = Party::create(array_merge([
            'legal_name' => $customerOverrides['name'] ?? 'Test Legal Name',
            'party_type' => PartyType::Individual,
        ], $partyOverrides));

        return $this->createCustomer(array_merge([
            'party_id' => $party->id,
        ], $customerOverrides));
    }

    /**
     * Create base PIM models required for vouchers and allocations.
     *
     * @return array{wineMaster: WineMaster, wineVariant: WineVariant, format: Format, allocation: Allocation}
     */
    private function createPimAndAllocation(): array
    {
        $wineMaster = WineMaster::create([
            'name' => 'Test Wine',
            'producer' => 'Test Producer',
            'country' => 'Italy',
        ]);

        $wineVariant = WineVariant::create([
            'wine_master_id' => $wineMaster->id,
            'vintage_year' => 2020,
        ]);

        $format = Format::create([
            'name' => 'Standard 750ml',
            'volume_ml' => 750,
        ]);

        $allocation = Allocation::create([
            'wine_variant_id' => $wineVariant->id,
            'format_id' => $format->id,
            'source_type' => AllocationSourceType::ProducerAllocation,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 100,
            'sold_quantity' => 0,
            'remaining_quantity' => 100,
            'status' => AllocationStatus::Active,
            'serialization_required' => true,
        ]);

        return compact('wineMaster', 'wineVariant', 'format', 'allocation');
    }

    /**
     * Create a voucher for a given customer.
     *
     * @param  array<string, mixed>  $pim  Output of createPimAndAllocation()
     */
    private function createVoucher(Customer $customer, array $pim, VoucherLifecycleState $state = VoucherLifecycleState::Issued): Voucher
    {
        return Voucher::create([
            'customer_id' => $customer->id,
            'allocation_id' => $pim['allocation']->id,
            'wine_variant_id' => $pim['wineVariant']->id,
            'format_id' => $pim['format']->id,
            'quantity' => 1,
            'lifecycle_state' => $state,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'sale_reference' => 'TEST-'.uniqid(),
        ]);
    }

    /**
     * Create an invoice for a given customer.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoice(Customer $customer, array $overrides = []): Invoice
    {
        $defaults = [
            'customer_id' => $customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'status' => InvoiceStatus::Issued,
            'total_amount' => '1000.00',
            'subtotal' => '1000.00',
            'tax_amount' => '0.00',
            'amount_paid' => '0.00',
            'currency' => 'EUR',
            'issued_at' => now(),
        ];

        return Invoice::create(array_merge($defaults, $overrides));
    }

    // =========================================================================
    // CustomerSearchTool Tests
    // =========================================================================

    public function test_customer_search_returns_matching_customers_by_name(): void
    {
        $this->createCustomer(['name' => 'Alice Wonderland', 'email' => 'alice@example.com']);
        $this->createCustomer(['name' => 'Bob Builder', 'email' => 'bob@example.com']);
        $this->createCustomer(['name' => 'Alice Springs', 'email' => 'springs@example.com']);

        $tool = new CustomerSearchTool;
        $result = json_decode($tool->handle(new Request(['query' => 'Alice'])), true);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(2, $result['count']);

        $names = array_column($result['results'], 'customer_name');
        $this->assertContains('Alice Wonderland', $names);
        $this->assertContains('Alice Springs', $names);
    }

    public function test_customer_search_returns_matching_customers_by_email(): void
    {
        $this->createCustomer(['name' => 'John Doe', 'email' => 'john.doe@crurated.com']);
        $this->createCustomer(['name' => 'Jane Doe', 'email' => 'jane@other.com']);

        $tool = new CustomerSearchTool;
        $result = json_decode($tool->handle(new Request(['query' => 'crurated'])), true);

        $this->assertEquals(1, $result['count']);
        $this->assertEquals('John Doe', $result['results'][0]['customer_name']);
    }

    public function test_customer_search_returns_correct_output_structure(): void
    {
        $this->createCustomer([
            'name' => 'Structured Customer',
            'email' => 'structured@example.com',
            'status' => CustomerStatus::Active,
            'customer_type' => CustomerType::B2B,
        ]);

        $tool = new CustomerSearchTool;
        $result = json_decode($tool->handle(new Request(['query' => 'Structured'])), true);

        $this->assertEquals(1, $result['count']);
        $customer = $result['results'][0];

        $this->assertArrayHasKey('customer_name', $customer);
        $this->assertArrayHasKey('email', $customer);
        $this->assertArrayHasKey('status', $customer);
        $this->assertArrayHasKey('customer_type', $customer);
        $this->assertArrayHasKey('membership_tier', $customer);
        $this->assertArrayHasKey('voucher_count', $customer);
        $this->assertArrayHasKey('shipping_order_count', $customer);

        $this->assertEquals('structured@example.com', $customer['email']);
        $this->assertEquals('Active', $customer['status']);
        $this->assertEquals('B2B', $customer['customer_type']);
    }

    public function test_customer_search_rejects_short_query(): void
    {
        $tool = new CustomerSearchTool;
        $result = json_decode($tool->handle(new Request(['query' => 'A'])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('at least 2 characters', $result['error']);
    }

    public function test_customer_search_finds_by_party_legal_name(): void
    {
        $this->createCustomerWithParty(
            ['name' => 'Customer Display', 'email' => 'party-search@example.com'],
            ['legal_name' => 'Acme Corporation']
        );

        $tool = new CustomerSearchTool;
        $result = json_decode($tool->handle(new Request(['query' => 'Acme'])), true);

        $this->assertEquals(1, $result['count']);
        $this->assertEquals('Acme Corporation', $result['results'][0]['customer_name']);
    }

    public function test_customer_search_authorization_denied_for_viewer(): void
    {
        // CustomerSearchTool requires Basic access level.
        // Viewer maps to Overview (10), which is below Basic (20).
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new CustomerSearchTool;
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_customer_search_authorization_granted_for_editor(): void
    {
        // Editor maps to Basic (20) which equals the required level.
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new CustomerSearchTool;
        $this->assertTrue($tool->authorizeForUser($editor));
    }

    // =========================================================================
    // CustomerStatusSummaryTool Tests
    // =========================================================================

    public function test_status_summary_returns_correct_counts(): void
    {
        $this->createCustomer(['name' => 'Active 1', 'email' => 'a1@test.com', 'status' => CustomerStatus::Active]);
        $this->createCustomer(['name' => 'Active 2', 'email' => 'a2@test.com', 'status' => CustomerStatus::Active]);
        $this->createCustomer(['name' => 'Prospect 1', 'email' => 'p1@test.com', 'status' => CustomerStatus::Prospect]);
        $this->createCustomer(['name' => 'Suspended 1', 'email' => 's1@test.com', 'status' => CustomerStatus::Suspended]);

        $tool = new CustomerStatusSummaryTool;
        $result = json_decode($tool->handle(new Request([])), true);

        $this->assertArrayHasKey('total_customers', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('by_type', $result);
        $this->assertArrayHasKey('with_active_blocks', $result);

        $this->assertEquals(4, $result['total_customers']);
        $this->assertEquals(2, $result['by_status']['Active']);
        $this->assertEquals(1, $result['by_status']['Prospect']);
        $this->assertEquals(1, $result['by_status']['Suspended']);
        $this->assertEquals(0, $result['by_status']['Closed']);
    }

    public function test_status_summary_groups_by_customer_type(): void
    {
        $this->createCustomer(['name' => 'B2C 1', 'email' => 'b2c1@test.com', 'customer_type' => CustomerType::B2C]);
        $this->createCustomer(['name' => 'B2C 2', 'email' => 'b2c2@test.com', 'customer_type' => CustomerType::B2C]);
        $this->createCustomer(['name' => 'B2B 1', 'email' => 'b2b1@test.com', 'customer_type' => CustomerType::B2B]);
        $this->createCustomer(['name' => 'Partner 1', 'email' => 'partner1@test.com', 'customer_type' => CustomerType::Partner]);

        $tool = new CustomerStatusSummaryTool;
        $result = json_decode($tool->handle(new Request([])), true);

        $this->assertEquals(2, $result['by_type']['B2C']);
        $this->assertEquals(1, $result['by_type']['B2B']);
        $this->assertEquals(1, $result['by_type']['Partner']);
    }

    public function test_status_summary_returns_all_status_keys_even_when_zero(): void
    {
        // Create only one customer - other statuses should still appear with 0
        $this->createCustomer(['name' => 'Only Active', 'email' => 'only@test.com', 'status' => CustomerStatus::Active]);

        $tool = new CustomerStatusSummaryTool;
        $result = json_decode($tool->handle(new Request([])), true);

        // All CustomerStatus cases should be represented
        foreach (CustomerStatus::cases() as $status) {
            $this->assertArrayHasKey($status->label(), $result['by_status']);
        }

        // All CustomerType cases should be represented
        foreach (CustomerType::cases() as $type) {
            $this->assertArrayHasKey($type->label(), $result['by_type']);
        }
    }

    public function test_status_summary_authorization_denied_for_null_role(): void
    {
        // CustomerStatusSummaryTool requires Overview (10).
        // A user with null role should be denied.
        // The DB column has a NOT NULL constraint with default, so we bypass
        // the database by manually setting the property after creation.
        $user = User::factory()->create();
        // Force null role in-memory to test the guard in authorizeForUser()
        $user->role = null;

        $tool = new CustomerStatusSummaryTool;
        $this->assertFalse($tool->authorizeForUser($user));
    }

    public function test_status_summary_authorization_granted_for_viewer(): void
    {
        // Viewer maps to Overview (10) which equals the required level.
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new CustomerStatusSummaryTool;
        $this->assertTrue($tool->authorizeForUser($viewer));
    }

    // =========================================================================
    // CustomerVoucherCountTool Tests
    // =========================================================================

    public function test_voucher_count_by_customer_id(): void
    {
        $customer = $this->createCustomer(['name' => 'Voucher Customer', 'email' => 'voucher@test.com']);
        $pim = $this->createPimAndAllocation();

        // Create 3 issued vouchers and 1 locked
        $this->createVoucher($customer, $pim, VoucherLifecycleState::Issued);
        $this->createVoucher($customer, $pim, VoucherLifecycleState::Issued);
        $this->createVoucher($customer, $pim, VoucherLifecycleState::Issued);
        $this->createVoucher($customer, $pim, VoucherLifecycleState::Locked);

        $tool = new CustomerVoucherCountTool;
        $result = json_decode($tool->handle(new Request(['customer_id' => $customer->id])), true);

        $this->assertArrayHasKey('customer_name', $result);
        $this->assertArrayHasKey('total_vouchers', $result);
        $this->assertArrayHasKey('by_state', $result);

        $this->assertEquals(4, $result['total_vouchers']);
        $this->assertEquals(3, $result['by_state']['Issued']);
        $this->assertEquals(1, $result['by_state']['Locked']);
        $this->assertEquals(0, $result['by_state']['Redeemed']);
        $this->assertEquals(0, $result['by_state']['Cancelled']);
    }

    public function test_voucher_count_by_customer_name(): void
    {
        $customer = $this->createCustomer(['name' => 'Unique Wine Buyer', 'email' => 'wine@test.com']);
        $pim = $this->createPimAndAllocation();

        $this->createVoucher($customer, $pim, VoucherLifecycleState::Issued);
        $this->createVoucher($customer, $pim, VoucherLifecycleState::Redeemed);

        $tool = new CustomerVoucherCountTool;
        $result = json_decode($tool->handle(new Request(['customer_name' => 'Unique Wine'])), true);

        $this->assertEquals(2, $result['total_vouchers']);
        $this->assertEquals(1, $result['by_state']['Issued']);
        $this->assertEquals(1, $result['by_state']['Redeemed']);
    }

    public function test_voucher_count_returns_error_without_parameters(): void
    {
        $tool = new CustomerVoucherCountTool;
        $result = json_decode($tool->handle(new Request([])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('customer_id or customer_name', $result['error']);
    }

    public function test_voucher_count_returns_error_for_invalid_customer_id(): void
    {
        $tool = new CustomerVoucherCountTool;
        $result = json_decode($tool->handle(new Request(['customer_id' => 'nonexistent-uuid'])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('No customer found', $result['error']);
    }

    public function test_voucher_count_returns_all_lifecycle_state_keys(): void
    {
        $customer = $this->createCustomer(['name' => 'State Key Customer', 'email' => 'statekeys@test.com']);
        $pim = $this->createPimAndAllocation();

        // Create just one voucher
        $this->createVoucher($customer, $pim);

        $tool = new CustomerVoucherCountTool;
        $result = json_decode($tool->handle(new Request(['customer_id' => $customer->id])), true);

        // All VoucherLifecycleState labels should be keys in by_state
        foreach (VoucherLifecycleState::cases() as $state) {
            $this->assertArrayHasKey($state->label(), $result['by_state']);
        }
    }

    public function test_voucher_count_authorization_denied_for_viewer(): void
    {
        // CustomerVoucherCountTool requires Basic (20).
        // Viewer maps to Overview (10), which is below Basic.
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new CustomerVoucherCountTool;
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_voucher_count_authorization_granted_for_editor(): void
    {
        // Editor maps to Basic (20) which equals the required level.
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new CustomerVoucherCountTool;
        $this->assertTrue($tool->authorizeForUser($editor));
    }

    // =========================================================================
    // TopCustomersByRevenueTool Tests
    // =========================================================================

    public function test_top_customers_returns_ranked_results(): void
    {
        $customerA = $this->createCustomer(['name' => 'Top Spender', 'email' => 'top@test.com']);
        $customerB = $this->createCustomer(['name' => 'Medium Spender', 'email' => 'med@test.com']);
        $customerC = $this->createCustomer(['name' => 'Low Spender', 'email' => 'low@test.com']);

        // Create invoices with different amounts for this_month period
        $this->createInvoice($customerA, ['total_amount' => '5000.00']);
        $this->createInvoice($customerA, ['total_amount' => '3000.00']);
        $this->createInvoice($customerB, ['total_amount' => '2000.00']);
        $this->createInvoice($customerC, ['total_amount' => '500.00']);

        $tool = new TopCustomersByRevenueTool;
        $result = json_decode($tool->handle(new Request(['period' => 'this_month'])), true);

        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('top_customers', $result);
        $this->assertArrayHasKey('count', $result);

        $this->assertEquals('this_month', $result['period']);
        $this->assertEquals(3, $result['count']);

        // First customer should be the top spender
        $this->assertEquals('Top Spender', $result['top_customers'][0]['customer_name']);
        $this->assertEquals('top@test.com', $result['top_customers'][0]['email']);
        $this->assertEquals(2, $result['top_customers'][0]['invoice_count']);

        // Verify descending order
        $this->assertEquals('Medium Spender', $result['top_customers'][1]['customer_name']);
        $this->assertEquals('Low Spender', $result['top_customers'][2]['customer_name']);
    }

    public function test_top_customers_respects_limit_parameter(): void
    {
        // Create 5 customers with invoices
        for ($i = 1; $i <= 5; $i++) {
            $customer = $this->createCustomer([
                'name' => "Customer {$i}",
                'email' => "cust{$i}@test.com",
            ]);
            $this->createInvoice($customer, ['total_amount' => (string) ($i * 1000)]);
        }

        $tool = new TopCustomersByRevenueTool;
        $result = json_decode($tool->handle(new Request(['period' => 'this_month', 'limit' => 3])), true);

        $this->assertEquals(3, $result['count']);
        $this->assertCount(3, $result['top_customers']);
    }

    public function test_top_customers_output_structure(): void
    {
        $customer = $this->createCustomer(['name' => 'Structure Test', 'email' => 'struct@test.com']);
        $this->createInvoice($customer, ['total_amount' => '1500.00']);

        $tool = new TopCustomersByRevenueTool;
        $result = json_decode($tool->handle(new Request(['period' => 'this_month'])), true);

        $this->assertCount(1, $result['top_customers']);
        $entry = $result['top_customers'][0];

        $this->assertArrayHasKey('customer_name', $entry);
        $this->assertArrayHasKey('email', $entry);
        $this->assertArrayHasKey('total_revenue', $entry);
        $this->assertArrayHasKey('invoice_count', $entry);
        $this->assertArrayHasKey('membership_tier', $entry);
    }

    public function test_top_customers_excludes_draft_and_cancelled_invoices(): void
    {
        $customer = $this->createCustomer(['name' => 'Filter Test', 'email' => 'filter@test.com']);

        // Create a draft invoice (should NOT count)
        $this->createInvoice($customer, [
            'total_amount' => '9999.00',
            'status' => InvoiceStatus::Draft,
            'issued_at' => now(),
        ]);

        // Create a cancelled invoice (should NOT count)
        $this->createInvoice($customer, [
            'total_amount' => '8888.00',
            'status' => InvoiceStatus::Cancelled,
            'issued_at' => now(),
        ]);

        // Create a paid invoice (should count)
        $this->createInvoice($customer, [
            'total_amount' => '1000.00',
            'status' => InvoiceStatus::Paid,
            'issued_at' => now(),
        ]);

        $tool = new TopCustomersByRevenueTool;
        $result = json_decode($tool->handle(new Request(['period' => 'this_month'])), true);

        $this->assertEquals(1, $result['count']);
        // Total should be only 1000, not 9999 + 8888 + 1000
        $this->assertStringContainsString('1,000.00', $result['top_customers'][0]['total_revenue']);
    }

    public function test_top_customers_authorization_denied_for_editor(): void
    {
        // TopCustomersByRevenueTool requires Standard (40).
        // Editor maps to Basic (20), which is below Standard.
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new TopCustomersByRevenueTool;
        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_top_customers_authorization_granted_for_manager(): void
    {
        // Manager maps to Standard (40) which equals the required level.
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $tool = new TopCustomersByRevenueTool;
        $this->assertTrue($tool->authorizeForUser($manager));
    }

    public function test_top_customers_authorization_granted_for_admin(): void
    {
        // Admin maps to Full (60), above the required Standard (40).
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $tool = new TopCustomersByRevenueTool;
        $this->assertTrue($tool->authorizeForUser($admin));
    }

    public function test_top_customers_returns_empty_for_period_with_no_invoices(): void
    {
        $customer = $this->createCustomer(['name' => 'Past Customer', 'email' => 'past@test.com']);

        // Create an invoice far in the past (outside any current period)
        $this->createInvoice($customer, [
            'total_amount' => '5000.00',
            'issued_at' => now()->subYears(3),
        ]);

        $tool = new TopCustomersByRevenueTool;
        $result = json_decode($tool->handle(new Request(['period' => 'today'])), true);

        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['top_customers']);
    }
}
