<?php

namespace Tests\Unit\AI\Tools\Allocation;

use App\AI\Tools\Allocation\AllocationStatusOverviewTool;
use App\AI\Tools\Allocation\BottlesSoldByProducerTool;
use App\AI\Tools\Allocation\VoucherCountsByStateTool;
use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\UserRole;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Pim\Country;
use App\Models\Pim\Format;
use App\Models\Pim\Producer;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Unit tests for Allocation AI tools.
 *
 * Covers AllocationStatusOverviewTool, BottlesSoldByProducerTool,
 * and VoucherCountsByStateTool including happy path, filtering,
 * and authorization scenarios.
 */
class AllocationToolsTest extends TestCase
{
    use RefreshDatabase;

    protected WineMaster $wineMaster;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected Customer $customer;

    protected Customer $otherCustomer;

    protected Producer $producer;

    protected Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create([
            'name' => 'Italy',
            'iso_code' => 'IT',
        ]);

        $this->producer = Producer::create([
            'name' => 'Tenuta San Guido',
            'country_id' => $this->country->id,
            'is_active' => true,
        ]);

        $this->wineMaster = WineMaster::create([
            'name' => 'Sassicaia',
            'producer' => 'Tenuta San Guido',
            'producer_id' => $this->producer->id,
            'country' => 'Italy',
        ]);

        $this->wineVariant = WineVariant::create([
            'wine_master_id' => $this->wineMaster->id,
            'vintage_year' => 2020,
        ]);

        $this->format = Format::create([
            'name' => 'Standard 750ml',
            'volume_ml' => 750,
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'status' => 'active',
        ]);

        $this->otherCustomer = Customer::create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'status' => 'active',
        ]);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Create an allocation with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createAllocation(array $overrides = []): Allocation
    {
        return Allocation::create(array_merge([
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'source_type' => AllocationSourceType::ProducerAllocation,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 100,
            'sold_quantity' => 0,
            'status' => AllocationStatus::Active,
            'serialization_required' => true,
        ], $overrides));
    }

    /**
     * Create a voucher tied to an allocation and customer.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createVoucher(Allocation $allocation, Customer $customer, array $overrides = []): Voucher
    {
        return Voucher::create(array_merge([
            'customer_id' => $customer->id,
            'allocation_id' => $allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'sale_reference' => 'SALE-'.uniqid(),
        ], $overrides));
    }

    // =========================================================================
    // AllocationStatusOverviewTool
    // =========================================================================

    public function test_allocation_status_overview_happy_path(): void
    {
        // Create allocations in various statuses
        $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 100,
            'sold_quantity' => 40,
        ]);
        $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 50,
            'sold_quantity' => 10,
        ]);
        $this->createAllocation([
            'status' => AllocationStatus::Draft,
            'total_quantity' => 30,
            'sold_quantity' => 0,
        ]);
        $this->createAllocation([
            'status' => AllocationStatus::Closed,
            'total_quantity' => 20,
            'sold_quantity' => 20,
        ]);

        $tool = new AllocationStatusOverviewTool;
        $request = new Request([]);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // Verify top-level structure
        $this->assertArrayHasKey('total_allocations', $data);
        $this->assertArrayHasKey('by_status', $data);
        $this->assertArrayHasKey('active_summary', $data);

        // Verify counts
        $this->assertEquals(4, $data['total_allocations']);

        // Verify by_status has all AllocationStatus labels
        $this->assertArrayHasKey('Draft', $data['by_status']);
        $this->assertArrayHasKey('Active', $data['by_status']);
        $this->assertArrayHasKey('Exhausted', $data['by_status']);
        $this->assertArrayHasKey('Closed', $data['by_status']);
        $this->assertEquals(1, $data['by_status']['Draft']);
        $this->assertEquals(2, $data['by_status']['Active']);
        $this->assertEquals(0, $data['by_status']['Exhausted']);
        $this->assertEquals(1, $data['by_status']['Closed']);

        // Verify active_summary with correct utilization math
        $activeSummary = $data['active_summary'];
        $this->assertEquals(150, $activeSummary['total_quantity']); // 100 + 50
        $this->assertEquals(50, $activeSummary['sold_quantity']);    // 40 + 10
        $this->assertEquals(100, $activeSummary['remaining_quantity']); // 150 - 50
        $expectedUtilization = round((50 / 150) * 100, 1);
        $this->assertEquals($expectedUtilization, $activeSummary['utilization_percentage']);
    }

    public function test_allocation_status_overview_with_empty_database(): void
    {
        $tool = new AllocationStatusOverviewTool;
        $request = new Request([]);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertEquals(0, $data['total_allocations']);

        // All status counts should be 0
        foreach ($data['by_status'] as $count) {
            $this->assertEquals(0, $count);
        }

        // Active summary should be zeroed out
        $this->assertEquals(0, $data['active_summary']['total_quantity']);
        $this->assertEquals(0, $data['active_summary']['sold_quantity']);
        $this->assertEquals(0, $data['active_summary']['remaining_quantity']);
        $this->assertEquals(0, $data['active_summary']['utilization_percentage']);
    }

    public function test_allocation_status_overview_authorization_viewer_denied(): void
    {
        // AllocationStatusOverviewTool requires Basic (ToolAccessLevel 20).
        // Viewer maps to Overview (ToolAccessLevel 10), which is insufficient.
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new AllocationStatusOverviewTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_allocation_status_overview_authorization_editor_allowed(): void
    {
        // Editor maps to Basic (ToolAccessLevel 20), which meets the requirement.
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $tool = new AllocationStatusOverviewTool;

        $this->assertTrue($tool->authorizeForUser($editor));
    }

    // =========================================================================
    // BottlesSoldByProducerTool
    // =========================================================================

    public function test_bottles_sold_by_producer_happy_path_top_producers(): void
    {
        $allocation = $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 50,
            'sold_quantity' => 5,
        ]);

        // Create vouchers in "sold" states (Issued, Locked, Redeemed are counted)
        for ($i = 0; $i < 5; $i++) {
            $this->createVoucher($allocation, $this->customer, [
                'lifecycle_state' => VoucherLifecycleState::Issued,
            ]);
        }

        $tool = new BottlesSoldByProducerTool;
        $request = new Request(['period' => 'this_year', 'limit' => 10]);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // Verify structure
        $this->assertArrayHasKey('producers', $data);
        $this->assertNotEmpty($data['producers']);

        // Verify the producer data
        $first = $data['producers'][0];
        $this->assertArrayHasKey('producer_name', $first);
        $this->assertArrayHasKey('bottles_sold', $first);
        $this->assertEquals('Tenuta San Guido', $first['producer_name']);
        $this->assertEquals(5, $first['bottles_sold']);
    }

    public function test_bottles_sold_by_producer_filter_by_producer_name(): void
    {
        $allocation = $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 30,
            'sold_quantity' => 3,
        ]);

        // Create 3 vouchers for the main producer
        for ($i = 0; $i < 3; $i++) {
            $this->createVoucher($allocation, $this->customer, [
                'lifecycle_state' => VoucherLifecycleState::Issued,
            ]);
        }

        // Create a second producer with its own wine chain
        $otherProducer = Producer::create([
            'name' => 'Antinori',
            'country_id' => $this->country->id,
            'is_active' => true,
        ]);
        $otherWineMaster = WineMaster::create([
            'name' => 'Tignanello',
            'producer' => 'Antinori',
            'producer_id' => $otherProducer->id,
            'country' => 'Italy',
        ]);
        $otherWineVariant = WineVariant::create([
            'wine_master_id' => $otherWineMaster->id,
            'vintage_year' => 2019,
        ]);
        $otherAllocation = Allocation::create([
            'wine_variant_id' => $otherWineVariant->id,
            'format_id' => $this->format->id,
            'source_type' => AllocationSourceType::ProducerAllocation,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 20,
            'sold_quantity' => 2,
            'status' => AllocationStatus::Active,
            'serialization_required' => true,
        ]);
        for ($i = 0; $i < 2; $i++) {
            Voucher::create([
                'customer_id' => $this->customer->id,
                'allocation_id' => $otherAllocation->id,
                'wine_variant_id' => $otherWineVariant->id,
                'format_id' => $this->format->id,
                'quantity' => 1,
                'lifecycle_state' => VoucherLifecycleState::Issued,
                'tradable' => true,
                'giftable' => true,
                'suspended' => false,
                'sale_reference' => 'SALE-OTHER-'.uniqid(),
            ]);
        }

        // Filter by specific producer name
        $tool = new BottlesSoldByProducerTool;
        $request = new Request(['producer_name' => 'Tenuta San Guido', 'period' => 'this_year']);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertArrayHasKey('producers', $data);
        $this->assertCount(1, $data['producers']);
        $this->assertEquals('Tenuta San Guido', $data['producers'][0]['producer_name']);
        $this->assertEquals(3, $data['producers'][0]['bottles_sold']);

        // Should also have top_wines breakdown
        $this->assertArrayHasKey('top_wines', $data['producers'][0]);
    }

    public function test_bottles_sold_by_producer_authorization_viewer_denied(): void
    {
        // BottlesSoldByProducerTool requires Standard (ToolAccessLevel 40).
        // Viewer maps to Overview (10) — insufficient.
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new BottlesSoldByProducerTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_bottles_sold_by_producer_authorization_editor_denied(): void
    {
        // Editor maps to Basic (20), Standard requires 40 — insufficient.
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $tool = new BottlesSoldByProducerTool;

        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_bottles_sold_by_producer_authorization_manager_allowed(): void
    {
        // Manager maps to Standard (40) — meets the requirement.
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $tool = new BottlesSoldByProducerTool;

        $this->assertTrue($tool->authorizeForUser($manager));
    }

    public function test_bottles_sold_by_producer_no_matching_producer(): void
    {
        $tool = new BottlesSoldByProducerTool;
        $request = new Request(['producer_name' => 'NonExistentProducerXYZ', 'period' => 'this_year']);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('NonExistentProducerXYZ', $data['message']);
    }

    // =========================================================================
    // VoucherCountsByStateTool
    // =========================================================================

    public function test_voucher_counts_by_state_happy_path(): void
    {
        $allocation = $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 100,
            'sold_quantity' => 10,
        ]);

        // Create vouchers in various lifecycle states
        for ($i = 0; $i < 4; $i++) {
            $this->createVoucher($allocation, $this->customer, [
                'lifecycle_state' => VoucherLifecycleState::Issued,
            ]);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->createVoucher($allocation, $this->customer, [
                'lifecycle_state' => VoucherLifecycleState::Locked,
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createVoucher($allocation, $this->customer, [
                'lifecycle_state' => VoucherLifecycleState::Redeemed,
            ]);
        }
        $this->createVoucher($allocation, $this->customer, [
            'lifecycle_state' => VoucherLifecycleState::Cancelled,
        ]);

        $tool = new VoucherCountsByStateTool;
        $request = new Request([]);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // Verify structure
        $this->assertArrayHasKey('total_vouchers', $data);
        $this->assertArrayHasKey('by_state', $data);
        $this->assertArrayHasKey('active_vouchers', $data);

        // Verify totals
        $this->assertEquals(10, $data['total_vouchers']);

        // Verify by_state using enum labels
        $this->assertEquals(4, $data['by_state']['Issued']);
        $this->assertEquals(3, $data['by_state']['Locked']);
        $this->assertEquals(2, $data['by_state']['Redeemed']);
        $this->assertEquals(1, $data['by_state']['Cancelled']);

        // Active vouchers = Issued + Locked
        $this->assertEquals(7, $data['active_vouchers']);
    }

    public function test_voucher_counts_by_state_filter_by_customer_id(): void
    {
        $allocation = $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 100,
            'sold_quantity' => 6,
        ]);

        // 4 vouchers for the main customer
        for ($i = 0; $i < 4; $i++) {
            $this->createVoucher($allocation, $this->customer, [
                'lifecycle_state' => VoucherLifecycleState::Issued,
            ]);
        }

        // 2 vouchers for the other customer
        for ($i = 0; $i < 2; $i++) {
            $this->createVoucher($allocation, $this->otherCustomer, [
                'lifecycle_state' => VoucherLifecycleState::Locked,
            ]);
        }

        $tool = new VoucherCountsByStateTool;

        // Filter by main customer
        $request = new Request(['customer_id' => $this->customer->id]);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertEquals(4, $data['total_vouchers']);
        $this->assertEquals(4, $data['by_state']['Issued']);
        $this->assertEquals(0, $data['by_state']['Locked']);
        $this->assertEquals(4, $data['active_vouchers']);

        // Filter by other customer
        $request2 = new Request(['customer_id' => $this->otherCustomer->id]);
        $result2 = $tool->handle($request2);
        $data2 = json_decode((string) $result2, true);

        $this->assertEquals(2, $data2['total_vouchers']);
        $this->assertEquals(0, $data2['by_state']['Issued']);
        $this->assertEquals(2, $data2['by_state']['Locked']);
        $this->assertEquals(2, $data2['active_vouchers']);
    }

    public function test_voucher_counts_by_state_authorization_viewer_denied(): void
    {
        // VoucherCountsByStateTool requires Basic (ToolAccessLevel 20).
        // Viewer maps to Overview (10) — insufficient.
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new VoucherCountsByStateTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_voucher_counts_by_state_authorization_editor_allowed(): void
    {
        // Editor maps to Basic (20) — meets the requirement.
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $tool = new VoucherCountsByStateTool;

        $this->assertTrue($tool->authorizeForUser($editor));
    }

    public function test_voucher_counts_by_state_with_empty_database(): void
    {
        $tool = new VoucherCountsByStateTool;
        $request = new Request([]);
        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertEquals(0, $data['total_vouchers']);
        $this->assertEquals(0, $data['active_vouchers']);

        // All state counts should be 0
        foreach (VoucherLifecycleState::cases() as $state) {
            $this->assertEquals(0, $data['by_state'][$state->label()]);
        }
    }
}
