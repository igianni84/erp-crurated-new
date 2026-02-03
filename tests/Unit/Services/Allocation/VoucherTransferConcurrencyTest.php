<?php

namespace Tests\Unit\Services\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Allocation\VoucherTransferStatus;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\Customer\Customer;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Services\Allocation\VoucherService;
use App\Services\Allocation\VoucherTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tests for concurrent transfer and lock handling.
 *
 * These tests verify the race condition scenario where:
 * 1. A transfer is initiated (pending)
 * 2. The voucher is locked for fulfillment
 * 3. The transfer acceptance should be blocked
 * 4. The transfer can still be cancelled
 */
class VoucherTransferConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected WineMaster $wineMaster;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected Customer $customer;

    protected Customer $recipientCustomer;

    protected Allocation $allocation;

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
            'name' => 'Original Owner',
            'email' => 'owner@example.com',
            'status' => 'active',
        ]);

        $this->recipientCustomer = Customer::create([
            'name' => 'Transfer Recipient',
            'email' => 'recipient@example.com',
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
     * Test that a pending transfer cannot be accepted when voucher is locked.
     */
    public function test_transfer_acceptance_blocked_when_voucher_locked(): void
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
            'sale_reference' => 'TEST-CONCURRENT-001',
        ]);

        /** @var VoucherTransferService $transferService */
        $transferService = app(VoucherTransferService::class);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Step 1: Initiate a transfer (creates pending transfer)
        $expiresAt = Carbon::now()->addWeeks(2);
        $transfer = $transferService->initiateTransfer($voucher, $this->recipientCustomer, $expiresAt);

        $this->assertEquals(VoucherTransferStatus::Pending, $transfer->status);
        $this->assertTrue($voucher->hasPendingTransfer());

        // Step 2: Lock the voucher for fulfillment (simulating race condition)
        $voucherService->lockForFulfillment($voucher);
        $voucher->refresh();

        $this->assertTrue($voucher->isLocked());
        $this->assertTrue($voucher->hasPendingTransfer());

        // Step 3: Attempting to accept the transfer should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locked during transfer - acceptance blocked');

        $transferService->acceptTransfer($transfer);
    }

    /**
     * Test that a blocked transfer can still be cancelled.
     */
    public function test_blocked_transfer_can_be_cancelled(): void
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
            'sale_reference' => 'TEST-CONCURRENT-002',
        ]);

        /** @var VoucherTransferService $transferService */
        $transferService = app(VoucherTransferService::class);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Initiate a transfer
        $expiresAt = Carbon::now()->addWeeks(2);
        $transfer = $transferService->initiateTransfer($voucher, $this->recipientCustomer, $expiresAt);

        // Lock the voucher (creates blocked state)
        $voucherService->lockForFulfillment($voucher);
        $voucher->refresh();

        // Verify the transfer is blocked
        $this->assertTrue($transfer->isAcceptanceBlockedByLock());

        // The transfer should still be cancellable
        $this->assertTrue($transfer->canBeCancelled());

        // Cancel the transfer
        $transferService->cancelTransfer($transfer);
        $transfer->refresh();

        // Verify cancellation succeeded
        $this->assertEquals(VoucherTransferStatus::Cancelled, $transfer->status);
        $this->assertNotNull($transfer->cancelled_at);

        // Voucher should still be locked and owned by original customer
        $voucher->refresh();
        $this->assertTrue($voucher->isLocked());
        $this->assertEquals($this->customer->id, $voucher->customer_id);
    }

    /**
     * Test that transfer can be accepted after voucher is unlocked.
     */
    public function test_transfer_can_be_accepted_after_unlock(): void
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
            'sale_reference' => 'TEST-CONCURRENT-003',
        ]);

        /** @var VoucherTransferService $transferService */
        $transferService = app(VoucherTransferService::class);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Initiate a transfer
        $expiresAt = Carbon::now()->addWeeks(2);
        $transfer = $transferService->initiateTransfer($voucher, $this->recipientCustomer, $expiresAt);

        // Lock the voucher
        $voucherService->lockForFulfillment($voucher);
        $voucher->refresh();

        // Verify blocked
        $this->assertTrue($transfer->isAcceptanceBlockedByLock());

        // Unlock the voucher
        $voucherService->unlock($voucher);
        $voucher->refresh();
        $transfer->refresh();

        // Now the transfer should no longer be blocked
        $this->assertFalse($transfer->isAcceptanceBlockedByLock());
        $this->assertTrue($transfer->canCurrentlyBeAccepted());

        // Accept the transfer
        $transferService->acceptTransfer($transfer);
        $transfer->refresh();
        $voucher->refresh();

        // Verify acceptance succeeded
        $this->assertEquals(VoucherTransferStatus::Accepted, $transfer->status);
        $this->assertEquals($this->recipientCustomer->id, $voucher->customer_id);
    }

    /**
     * Test VoucherTransfer model helper methods for blocked state.
     */
    public function test_voucher_transfer_model_blocked_state_helpers(): void
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
            'sale_reference' => 'TEST-CONCURRENT-004',
        ]);

        /** @var VoucherTransferService $transferService */
        $transferService = app(VoucherTransferService::class);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Create a pending transfer
        $expiresAt = Carbon::now()->addWeeks(2);
        $transfer = $transferService->initiateTransfer($voucher, $this->recipientCustomer, $expiresAt);
        $transfer->load('voucher');

        // Before locking - not blocked
        $this->assertFalse($transfer->isAcceptanceBlockedByLock());
        $this->assertTrue($transfer->canCurrentlyBeAccepted());
        $this->assertNull($transfer->getAcceptanceBlockedReason());

        // Lock the voucher
        $voucherService->lockForFulfillment($voucher);
        $voucher->refresh();
        $transfer->refresh();
        $transfer->load('voucher');

        // After locking - blocked
        $this->assertTrue($transfer->isAcceptanceBlockedByLock());
        $this->assertFalse($transfer->canCurrentlyBeAccepted());

        $reason = $transfer->getAcceptanceBlockedReason();
        $this->assertNotNull($reason);
        $this->assertStringContainsString('Locked during transfer', $reason);
        $this->assertStringContainsString('acceptance blocked', $reason);
    }

    /**
     * Test Voucher model helper for detecting blocked pending transfer.
     */
    public function test_voucher_model_has_pending_transfer_blocked_by_lock(): void
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
            'sale_reference' => 'TEST-CONCURRENT-005',
        ]);

        /** @var VoucherTransferService $transferService */
        $transferService = app(VoucherTransferService::class);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Before any transfer - false
        $this->assertFalse($voucher->hasPendingTransferBlockedByLock());

        // Create pending transfer but voucher is still issued - false
        $expiresAt = Carbon::now()->addWeeks(2);
        $transfer = $transferService->initiateTransfer($voucher, $this->recipientCustomer, $expiresAt);
        $this->assertFalse($voucher->hasPendingTransferBlockedByLock());

        // Lock the voucher - now should be true
        $voucherService->lockForFulfillment($voucher);
        $voucher->refresh();
        $this->assertTrue($voucher->hasPendingTransferBlockedByLock());

        // Unlock - should be false again
        $voucherService->unlock($voucher);
        $voucher->refresh();
        $this->assertFalse($voucher->hasPendingTransferBlockedByLock());
    }

    /**
     * Test that lifecycle transition logs include explicit timestamps.
     */
    public function test_lifecycle_transition_logs_include_timestamps(): void
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
            'sale_reference' => 'TEST-CONCURRENT-006',
        ]);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Lock the voucher
        $voucherService->lockForFulfillment($voucher);
        $voucher->refresh();

        // Get the lock audit log
        $lockLog = $voucher->auditLogs()
            ->where('event', 'lifecycle_change')
            ->whereJsonContains('new_values->lifecycle_state', VoucherLifecycleState::Locked->value)
            ->first();

        $this->assertNotNull($lockLog);
        $this->assertArrayHasKey('transitioned_at', $lockLog->new_values);
        $this->assertNotEmpty($lockLog->new_values['transitioned_at']);

        // Verify the timestamp is valid ISO8601
        $timestamp = Carbon::parse($lockLog->new_values['transitioned_at']);
        $this->assertInstanceOf(Carbon::class, $timestamp);
    }

    /**
     * Test getLockedAtTimestamp returns correct value.
     */
    public function test_get_locked_at_timestamp(): void
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
            'sale_reference' => 'TEST-CONCURRENT-007',
        ]);

        // Before locking - should return null
        $this->assertNull($voucher->getLockedAtTimestamp());

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Lock the voucher
        $beforeLock = now();
        $voucherService->lockForFulfillment($voucher);
        $afterLock = now();
        $voucher->refresh();

        // Should return a timestamp within the lock time window
        $lockedAt = $voucher->getLockedAtTimestamp();
        $this->assertNotNull($lockedAt);
        $this->assertTrue($lockedAt->gte($beforeLock->subSecond()));
        $this->assertTrue($lockedAt->lte($afterLock->addSecond()));
    }

    /**
     * Test error message for blocked transfer includes timestamp information.
     */
    public function test_blocked_transfer_error_includes_timestamp(): void
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
            'sale_reference' => 'TEST-CONCURRENT-008',
        ]);

        /** @var VoucherTransferService $transferService */
        $transferService = app(VoucherTransferService::class);

        /** @var VoucherService $voucherService */
        $voucherService = app(VoucherService::class);

        // Initiate a transfer
        $expiresAt = Carbon::now()->addWeeks(2);
        $transfer = $transferService->initiateTransfer($voucher, $this->recipientCustomer, $expiresAt);

        // Lock the voucher
        $voucherService->lockForFulfillment($voucher);
        $voucher->refresh();

        // Try to accept and capture the exception message
        try {
            $transferService->acceptTransfer($transfer);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            // Error message should include the phrase about being able to cancel
            $this->assertStringContainsString('Locked during transfer', $e->getMessage());
            $this->assertStringContainsString('cancel', strtolower($e->getMessage()));
            // Should mention the timestamp (contains "(locked at" text)
            $this->assertStringContainsString('locked at', $e->getMessage());
        }
    }
}
