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
use App\Services\Allocation\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Voucher allocation_id immutability and lineage enforcement.
 *
 * These tests verify that:
 * 1. allocation_id cannot be modified after voucher creation
 * 2. VoucherService properly validates fulfillment lineage
 * 3. The Voucher model provides correct lineage constraint methods
 */
class VoucherLineageTest extends TestCase
{
    use RefreshDatabase;

    protected WineMaster $wineMaster;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected Customer $customer;

    protected Allocation $allocation;

    protected Allocation $otherAllocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required base models for testing
        $this->wineMaster = WineMaster::create([
            'name' => 'Test Wine',
            'producer' => 'Test Producer',
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
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        // Create two allocations for lineage testing
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

        $this->otherAllocation = Allocation::create([
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'source_type' => AllocationSourceType::OwnedStock,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 50,
            'sold_quantity' => 0,
            'remaining_quantity' => 50,
            'status' => AllocationStatus::Active,
            'serialization_required' => true,
        ]);
    }

    /**
     * Test that allocation_id cannot be modified after voucher creation.
     */
    public function test_allocation_id_is_immutable(): void
    {
        // Create a voucher
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

        // Attempt to modify allocation_id should throw an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Allocation lineage cannot be modified after voucher creation');

        $voucher->allocation_id = $this->otherAllocation->id;
        $voucher->save();
    }

    /**
     * Test that validateFulfillmentLineage throws exception for mismatched allocations.
     */
    public function test_validate_fulfillment_lineage_throws_for_mismatch(): void
    {
        // Create a voucher from the first allocation
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
            'sale_reference' => 'TEST-002',
        ]);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Attempting to validate fulfillment with a different allocation should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot fulfill voucher with bottle from different allocation lineage');

        $voucherService->validateFulfillmentLineage($voucher, $this->otherAllocation);
    }

    /**
     * Test that validateFulfillmentLineage succeeds for matching allocations.
     */
    public function test_validate_fulfillment_lineage_succeeds_for_match(): void
    {
        // Create a voucher from the first allocation
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
            'sale_reference' => 'TEST-003',
        ]);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // This should not throw - same allocation
        $voucherService->validateFulfillmentLineage($voucher, $this->allocation);

        // If we got here without exception, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test canFulfillWithBottleFromAllocation returns correct boolean.
     */
    public function test_can_fulfill_with_bottle_from_allocation(): void
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
            'sale_reference' => 'TEST-004',
        ]);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Should return true for matching allocation
        $this->assertTrue(
            $voucherService->canFulfillWithBottleFromAllocation($voucher, $this->allocation)
        );

        // Should return false for different allocation
        $this->assertFalse(
            $voucherService->canFulfillWithBottleFromAllocation($voucher, $this->otherAllocation)
        );
    }

    /**
     * Test voucher model helper method canBeFulfilledFromAllocation.
     */
    public function test_voucher_model_can_be_fulfilled_from_allocation(): void
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

        // Should return true for matching allocation ID
        $this->assertTrue($voucher->canBeFulfilledFromAllocation($this->allocation->id));

        // Should return false for different allocation ID
        $this->assertFalse($voucher->canBeFulfilledFromAllocation($this->otherAllocation->id));
    }

    /**
     * Test getLineageConstraintMessage returns proper message.
     */
    public function test_get_lineage_constraint_message(): void
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
            'sale_reference' => 'TEST-006',
        ]);

        $message = $voucher->getLineageConstraintMessage();

        // Message should contain the allocation ID
        $this->assertStringContainsString($this->allocation->id, $message);

        // Message should explain the constraint
        $this->assertStringContainsString('must be fulfilled with physical bottles', $message);
        $this->assertStringContainsString('not permitted', $message);
    }

    /**
     * Test checkFulfillmentEligibility for various voucher states.
     */
    public function test_check_fulfillment_eligibility(): void
    {
        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Test with issued voucher (should not be fulfillable - needs to be locked first)
        $issuedVoucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'sale_reference' => 'TEST-007',
        ]);

        $result = $voucherService->checkFulfillmentEligibility($issuedVoucher);
        $this->assertFalse($result['fulfillable']);
        $this->assertStringContainsString('must be locked', $result['reason']);

        // Test with locked voucher (should be fulfillable)
        $lockedVoucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Locked,
            'tradable' => true,
            'giftable' => true,
            'suspended' => false,
            'sale_reference' => 'TEST-008',
        ]);

        $result = $voucherService->checkFulfillmentEligibility($lockedVoucher);
        $this->assertTrue($result['fulfillable']);
        $this->assertNull($result['reason']);

        // Test with suspended voucher (should not be fulfillable)
        $suspendedVoucher = Voucher::create([
            'customer_id' => $this->customer->id,
            'allocation_id' => $this->allocation->id,
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Locked,
            'tradable' => true,
            'giftable' => true,
            'suspended' => true,
            'sale_reference' => 'TEST-009',
        ]);

        $result = $voucherService->checkFulfillmentEligibility($suspendedVoucher);
        $this->assertFalse($result['fulfillable']);
        $this->assertStringContainsString('suspended', $result['reason']);
    }

    /**
     * Test that allocation_id is set correctly during creation and preserved.
     */
    public function test_allocation_id_preserved_after_other_updates(): void
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
            'sale_reference' => 'TEST-010',
        ]);

        $originalAllocationId = $voucher->allocation_id;

        // Update other fields - this should work
        $voucher->suspended = true;
        $voucher->save();

        // Reload from database
        $voucher->refresh();

        // allocation_id should remain unchanged
        $this->assertEquals($originalAllocationId, $voucher->allocation_id);

        // Update lifecycle state - this should work
        $voucher->lifecycle_state = VoucherLifecycleState::Locked;
        $voucher->save();

        // Reload from database
        $voucher->refresh();

        // allocation_id should still remain unchanged
        $this->assertEquals($originalAllocationId, $voucher->allocation_id);
    }
}
