<?php

namespace Tests\Feature\Services\Allocation;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Events\VoucherIssued;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use App\Models\User;
use App\Services\Allocation\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class VoucherServiceTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $service;

    private Allocation $allocation;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VoucherService::class);
        $this->allocation = Allocation::factory()->active()->create([
            'total_quantity' => 50,
            'sold_quantity' => 0,
        ]);
        $this->customer = Customer::factory()->create();

        $this->actingAs(User::factory()->create());
    }

    // --- issueVouchers ---

    public function test_issue_vouchers_creates_single_voucher(): void
    {
        Event::fake([VoucherIssued::class]);

        $vouchers = $this->service->issueVouchers(
            $this->allocation,
            $this->customer,
            null,
            'SALE-001',
            1
        );

        $this->assertCount(1, $vouchers);
        $voucher = $vouchers->first();
        $this->assertNotNull($voucher);
        $this->assertEquals(1, $voucher->quantity);
    }

    public function test_issue_vouchers_correct_attributes(): void
    {
        Event::fake([VoucherIssued::class]);

        $sku = SellableSku::factory()->create();
        $vouchers = $this->service->issueVouchers(
            $this->allocation,
            $this->customer,
            $sku,
            'SALE-002',
            1
        );

        $voucher = $vouchers->first();
        $this->assertNotNull($voucher);
        $this->assertEquals($this->allocation->id, $voucher->allocation_id);
        $this->assertEquals($this->customer->id, $voucher->customer_id);
        $this->assertEquals($sku->id, $voucher->sellable_sku_id);
        $this->assertEquals(VoucherLifecycleState::Issued, $voucher->lifecycle_state);
        $this->assertEquals('SALE-002', $voucher->sale_reference);
        $this->assertFalse($voucher->suspended);
    }

    public function test_issue_vouchers_dispatches_event(): void
    {
        Event::fake([VoucherIssued::class]);

        $this->service->issueVouchers(
            $this->allocation,
            $this->customer,
            null,
            'SALE-003',
            1
        );

        Event::assertDispatched(VoucherIssued::class);
    }

    public function test_issue_vouchers_idempotent(): void
    {
        Event::fake([VoucherIssued::class]);

        $first = $this->service->issueVouchers(
            $this->allocation,
            $this->customer,
            null,
            'SALE-IDEMPOTENT',
            1
        );

        $second = $this->service->issueVouchers(
            $this->allocation,
            $this->customer,
            null,
            'SALE-IDEMPOTENT',
            1
        );

        $firstVoucher = $first->first();
        $secondVoucher = $second->first();
        $this->assertNotNull($firstVoucher);
        $this->assertNotNull($secondVoucher);
        $this->assertEquals($firstVoucher->id, $secondVoucher->id);
        $this->assertDatabaseCount('vouchers', 1);
    }

    public function test_issue_vouchers_rejects_zero_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        $this->service->issueVouchers(
            $this->allocation,
            $this->customer,
            null,
            'SALE-ZERO',
            0
        );
    }

    public function test_issue_vouchers_rejects_exhausted_allocation(): void
    {
        $exhausted = Allocation::factory()->exhausted()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot issue vouchers');

        $this->service->issueVouchers(
            $exhausted,
            $this->customer,
            null,
            'SALE-EXHAUSTED',
            1
        );
    }

    // --- lockForFulfillment ---

    public function test_lock_for_fulfillment_issued_to_locked(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
        ]);

        $result = $this->service->lockForFulfillment($voucher);

        $this->assertEquals(VoucherLifecycleState::Locked, $result->lifecycle_state);
        $freshVoucher = $voucher->fresh();
        $this->assertNotNull($freshVoucher);
        $this->assertEquals(VoucherLifecycleState::Locked, $freshVoucher->lifecycle_state);
    }

    public function test_lock_rejects_non_issued(): void
    {
        $locked = Voucher::factory()->locked()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot lock voucher');

        $this->service->lockForFulfillment($locked);
    }

    public function test_lock_rejects_suspended(): void
    {
        $suspended = Voucher::factory()->suspended()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('suspended');

        $this->service->lockForFulfillment($suspended);
    }

    // --- unlock ---

    public function test_unlock_locked_to_issued(): void
    {
        $voucher = Voucher::factory()->locked()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
        ]);

        $result = $this->service->unlock($voucher);

        $this->assertEquals(VoucherLifecycleState::Issued, $result->lifecycle_state);
    }

    public function test_unlock_rejects_non_locked(): void
    {
        $issued = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot unlock voucher');

        $this->service->unlock($issued);
    }

    // --- redeem ---

    public function test_redeem_locked_to_redeemed(): void
    {
        $voucher = Voucher::factory()->locked()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
        ]);

        $result = $this->service->redeem($voucher);

        $this->assertEquals(VoucherLifecycleState::Redeemed, $result->lifecycle_state);
        $this->assertTrue($result->lifecycle_state->isTerminal());
    }

    public function test_redeem_rejects_non_locked(): void
    {
        $issued = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot redeem voucher');

        $this->service->redeem($issued);
    }

    // --- cancel ---

    public function test_cancel_issued_to_cancelled(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
        ]);

        $result = $this->service->cancel($voucher);

        $this->assertEquals(VoucherLifecycleState::Cancelled, $result->lifecycle_state);
        $this->assertTrue($result->lifecycle_state->isTerminal());
    }

    public function test_cancel_rejects_non_issued(): void
    {
        $locked = Voucher::factory()->locked()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel voucher');

        $this->service->cancel($locked);
    }

    // --- suspend ---

    public function test_suspend_sets_flag(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
        ]);

        $result = $this->service->suspend($voucher, 'manual hold');

        $this->assertTrue($result->suspended);
        $freshVoucher = $voucher->fresh();
        $this->assertNotNull($freshVoucher);
        $this->assertTrue($freshVoucher->suspended);
    }

    public function test_suspend_rejects_already_suspended(): void
    {
        $voucher = Voucher::factory()->suspended()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already suspended');

        $this->service->suspend($voucher);
    }

    public function test_suspend_rejects_terminal(): void
    {
        $redeemed = Voucher::factory()->redeemed()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('terminal state');

        $this->service->suspend($redeemed);
    }

    // --- reactivate ---

    public function test_reactivate_clears_flag(): void
    {
        $voucher = Voucher::factory()->suspended()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'external_trading_reference' => 'TRADE-123',
        ]);

        $result = $this->service->reactivate($voucher);

        $this->assertFalse($result->suspended);
        $this->assertNull($result->external_trading_reference);
    }

    public function test_reactivate_rejects_non_suspended(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not suspended');

        $this->service->reactivate($voucher);
    }

    // --- suspendForTrading ---

    public function test_suspend_for_trading_happy_path(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
            'tradable' => true,
        ]);

        $result = $this->service->suspendForTrading($voucher, 'LIVEX-REF-001');

        $this->assertTrue($result->suspended);
        $this->assertEquals('LIVEX-REF-001', $result->external_trading_reference);
    }

    public function test_suspend_for_trading_rejects_non_tradable(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
            'tradable' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not tradable');

        $this->service->suspendForTrading($voucher, 'LIVEX-REF-002');
    }

    public function test_suspend_for_trading_rejects_locked(): void
    {
        $voucher = Voucher::factory()->locked()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
            'tradable' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be in Issued state');

        $this->service->suspendForTrading($voucher, 'LIVEX-REF-003');
    }

    // --- completeTrading ---

    public function test_complete_trading_transfers_customer(): void
    {
        $newCustomer = Customer::factory()->create();
        $voucher = Voucher::factory()->suspended()->create([
            'allocation_id' => $this->allocation->id,
            'customer_id' => $this->customer->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'external_trading_reference' => 'TRADE-REF-001',
        ]);

        $result = $this->service->completeTrading($voucher, 'TRADE-REF-001', $newCustomer);

        $this->assertEquals($newCustomer->id, $result->customer_id);
        $this->assertFalse($result->suspended);
        $this->assertNull($result->external_trading_reference);
        // Lineage must be preserved
        $this->assertEquals($this->allocation->id, $result->allocation_id);
    }

    public function test_complete_trading_rejects_wrong_reference(): void
    {
        $voucher = Voucher::factory()->suspended()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'external_trading_reference' => 'TRADE-REF-001',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trading reference does not match');

        $this->service->completeTrading($voucher, 'WRONG-REF', Customer::factory()->create());
    }

    // --- setTradable / setGiftable ---

    public function test_set_tradable_on_issued(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
            'tradable' => true,
        ]);

        $result = $this->service->setTradable($voucher, false);

        $this->assertFalse($result->tradable);
    }

    public function test_set_tradable_rejects_non_issued(): void
    {
        $voucher = Voucher::factory()->locked()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only issued vouchers');

        $this->service->setTradable($voucher, false);
    }

    public function test_set_giftable_on_issued(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
            'giftable' => false,
        ]);

        $result = $this->service->setGiftable($voucher, true);

        $this->assertTrue($result->giftable);
    }

    // --- validateFulfillmentLineage ---

    public function test_validate_fulfillment_lineage_passes(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        // Should not throw
        $this->service->validateFulfillmentLineage($voucher, $this->allocation);
        $this->addToAssertionCount(1);
    }

    public function test_validate_fulfillment_lineage_throws(): void
    {
        $otherAllocation = Allocation::factory()->active()->create();
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different allocation lineage');

        $this->service->validateFulfillmentLineage($voucher, $otherAllocation);
    }

    // --- checkFulfillmentEligibility ---

    public function test_check_eligibility_locked_not_suspended(): void
    {
        $voucher = Voucher::factory()->locked()->create([
            'allocation_id' => $this->allocation->id,
            'suspended' => false,
        ]);

        $result = $this->service->checkFulfillmentEligibility($voucher);

        $this->assertTrue($result['fulfillable']);
        $this->assertNull($result['reason']);
    }

    public function test_check_eligibility_fails_non_locked(): void
    {
        $voucher = Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $result = $this->service->checkFulfillmentEligibility($voucher);

        $this->assertFalse($result['fulfillable']);
        $this->assertStringContainsString('locked', (string) $result['reason']);
    }

    public function test_check_eligibility_fails_suspended(): void
    {
        $voucher = Voucher::factory()->locked()->suspended()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $result = $this->service->checkFulfillmentEligibility($voucher);

        $this->assertFalse($result['fulfillable']);
        $this->assertStringContainsString('suspended', (string) $result['reason']);
    }
}
