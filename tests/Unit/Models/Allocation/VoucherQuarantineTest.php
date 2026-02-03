<?php

namespace Tests\Unit\Models\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Services\Allocation\VoucherAnomalyService;
use App\Services\Allocation\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VoucherQuarantineTest extends TestCase
{
    use RefreshDatabase;

    protected Allocation $allocation;

    protected Customer $customer;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected function setUp(): void
    {
        parent::setUp();

        // Create necessary models for testing
        $wineMaster = WineMaster::create([
            'name' => 'Test Wine',
            'producer' => 'Test Producer',
            'region' => 'Test Region',
            'country' => 'IT',
            'type' => 'red',
        ]);

        $this->wineVariant = WineVariant::create([
            'wine_master_id' => $wineMaster->id,
            'vintage_year' => 2020,
        ]);

        $this->format = Format::create([
            'name' => 'Standard Bottle',
            'volume_ml' => 750,
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $this->allocation = Allocation::create([
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'source_type' => AllocationSourceType::ProducerAllocation,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 100,
            'sold_quantity' => 0,
            'remaining_quantity' => 100,
            'status' => AllocationStatus::Active,
            'serialization_required' => true,
        ]);
    }

    /**
     * Test that allocation_id column has NOT NULL constraint at database level.
     */
    public function test_allocation_id_has_not_null_constraint(): void
    {
        // Verify the column is not nullable in the schema
        $columns = Schema::getColumnListing('vouchers');
        $this->assertContains('allocation_id', $columns);

        // Try to create a voucher without allocation_id - should fail at database level
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Using raw insert to bypass model validation
        \DB::table('vouchers')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customer->id,
            'allocation_id' => null, // This should cause a constraint violation
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued->value,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'requires_attention' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test VoucherAnomalyService validates missing allocation_id.
     */
    public function test_anomaly_service_validates_missing_allocation(): void
    {
        $service = app(VoucherAnomalyService::class);

        $result = $service->validateVoucherData([
            'customer_id' => $this->customer->id,
            'allocation_id' => null, // Missing
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains(VoucherAnomalyService::REASON_MISSING_ALLOCATION, $result['errors']);
    }

    /**
     * Test VoucherAnomalyService validates missing customer_id.
     */
    public function test_anomaly_service_validates_missing_customer(): void
    {
        $service = app(VoucherAnomalyService::class);

        $result = $service->validateVoucherData([
            'customer_id' => null, // Missing
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains(VoucherAnomalyService::REASON_MISSING_CUSTOMER, $result['errors']);
    }

    /**
     * Test VoucherAnomalyService validates missing bottle SKU.
     */
    public function test_anomaly_service_validates_missing_bottle_sku(): void
    {
        $service = app(VoucherAnomalyService::class);

        $result = $service->validateVoucherData([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => null, // Missing
            'format_id' => $this->format->id,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains(VoucherAnomalyService::REASON_MISSING_BOTTLE_SKU, $result['errors']);
    }

    /**
     * Test valid data passes validation.
     */
    public function test_valid_voucher_data_passes_validation(): void
    {
        $service = app(VoucherAnomalyService::class);

        $result = $service->validateVoucherData([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test quarantine method sets requires_attention flag.
     */
    public function test_quarantine_sets_requires_attention(): void
    {
        $voucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'sale_reference' => 'TEST-001',
        ]);

        $this->assertFalse($voucher->requires_attention);
        $this->assertNull($voucher->attention_reason);

        $service = app(VoucherAnomalyService::class);
        $service->quarantine($voucher, 'Test reason');

        $voucher->refresh();

        $this->assertTrue($voucher->requires_attention);
        $this->assertEquals('Test reason', $voucher->attention_reason);
        $this->assertTrue($voucher->isQuarantined());
        $this->assertFalse($voucher->canParticipateInNormalOperations());
    }

    /**
     * Test unquarantine removes requires_attention flag.
     */
    public function test_unquarantine_removes_requires_attention(): void
    {
        $voucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'requires_attention' => true,
            'attention_reason' => 'Test reason',
            'sale_reference' => 'TEST-002',
        ]);

        $this->assertTrue($voucher->isQuarantined());

        $service = app(VoucherAnomalyService::class);
        $service->unquarantine($voucher);

        $voucher->refresh();

        $this->assertFalse($voucher->requires_attention);
        $this->assertNull($voucher->attention_reason);
        $this->assertFalse($voucher->isQuarantined());
        $this->assertTrue($voucher->canParticipateInNormalOperations());
    }

    /**
     * Test cannot quarantine already quarantined voucher.
     */
    public function test_cannot_quarantine_already_quarantined_voucher(): void
    {
        $voucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'requires_attention' => true,
            'attention_reason' => 'Already quarantined',
            'sale_reference' => 'TEST-003',
        ]);

        $service = app(VoucherAnomalyService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already quarantined');

        $service->quarantine($voucher, 'New reason');
    }

    /**
     * Test VoucherService validates import data.
     */
    public function test_voucher_service_validates_import_data(): void
    {
        $service = app(VoucherService::class);

        // Valid data should pass
        $result = $service->validateForImport([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
        ]);

        $this->assertTrue($result['valid']);

        // Missing allocation should fail
        $result = $service->validateForImport([
            'customer_id' => $this->customer->id,
            'allocation_id' => null,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test requires_attention flag is included in fillable.
     */
    public function test_requires_attention_is_fillable(): void
    {
        $voucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'requires_attention' => true,
            'attention_reason' => 'Manual flag',
            'sale_reference' => 'TEST-004',
        ]);

        $this->assertTrue($voucher->requires_attention);
        $this->assertEquals('Manual flag', $voucher->attention_reason);
    }

    /**
     * Test getDetectedAnomalies returns empty array for normal voucher.
     */
    public function test_normal_voucher_has_no_detected_anomalies(): void
    {
        $voucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'sale_reference' => 'TEST-005',
        ]);

        $anomalies = $voucher->getDetectedAnomalies();

        $this->assertEmpty($anomalies);
        $this->assertTrue($voucher->canParticipateInNormalOperations());
    }

    /**
     * Test getAttentionReason returns stored reason.
     */
    public function test_get_attention_reason_returns_stored_reason(): void
    {
        $voucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'requires_attention' => true,
            'attention_reason' => 'Specific issue found',
            'sale_reference' => 'TEST-006',
        ]);

        $this->assertEquals('Specific issue found', $voucher->getAttentionReason());
    }
}
