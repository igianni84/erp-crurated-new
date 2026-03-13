<?php

namespace Tests\Feature\Services\Fulfillment;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\User;
use App\Services\Fulfillment\ShippingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ShippingOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShippingOrderService $service;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShippingOrderService::class);
        $this->customer = Customer::factory()->active()->create();

        // Authenticate for audit logging
        $this->actingAs(User::factory()->create());
    }

    private function createIssuedVoucher(?Customer $customer = null): Voucher
    {
        return Voucher::factory()->create([
            'customer_id' => ($customer ?? $this->customer)->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'suspended' => false,
        ]);
    }

    // --- create ---

    public function test_create_shipping_order_with_vouchers(): void
    {
        $voucher1 = $this->createIssuedVoucher();
        $voucher2 = $this->createIssuedVoucher();

        $so = $this->service->create($this->customer, [$voucher1, $voucher2]);

        $this->assertEquals(ShippingOrderStatus::Draft, $so->status);
        $this->assertEquals($this->customer->id, $so->customer_id);
        $this->assertCount(2, $so->lines);
    }

    public function test_create_sets_allocation_id_on_lines(): void
    {
        $voucher = $this->createIssuedVoucher();

        $so = $this->service->create($this->customer, [$voucher]);

        $line = $so->lines->first();
        $this->assertNotNull($line);
        $this->assertEquals($voucher->allocation_id, $line->allocation_id);
        $this->assertEquals($voucher->id, $line->voucher_id);
    }

    // --- transitionTo ---

    public function test_transition_draft_to_planned(): void
    {
        $voucher = $this->createIssuedVoucher();
        $so = $this->service->create($this->customer, [$voucher]);

        $updated = $this->service->transitionTo($so, ShippingOrderStatus::Planned);

        $this->assertEquals(ShippingOrderStatus::Planned, $updated->status);

        // Voucher should be locked for fulfillment
        $voucher->refresh();
        $this->assertEquals(VoucherLifecycleState::Locked, $voucher->lifecycle_state);
    }

    public function test_transition_planned_to_picking(): void
    {
        $voucher = $this->createIssuedVoucher();
        $so = $this->service->create($this->customer, [$voucher]);
        $this->service->transitionTo($so, ShippingOrderStatus::Planned);

        $updated = $this->service->transitionTo($so, ShippingOrderStatus::Picking);

        $this->assertEquals(ShippingOrderStatus::Picking, $updated->status);
    }

    public function test_transition_rejects_invalid_transition(): void
    {
        $voucher = $this->createIssuedVoucher();
        $so = $this->service->create($this->customer, [$voucher]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->transitionTo($so, ShippingOrderStatus::Completed);
    }

    // --- cancel ---

    public function test_cancel_draft_order(): void
    {
        $voucher = $this->createIssuedVoucher();
        $so = $this->service->create($this->customer, [$voucher]);

        $cancelled = $this->service->cancel($so, 'Customer request');

        $this->assertEquals(ShippingOrderStatus::Cancelled, $cancelled->status);

        // Voucher should remain issued (was never locked for draft SO)
        $voucher->refresh();
        $this->assertEquals(VoucherLifecycleState::Issued, $voucher->lifecycle_state);
    }

    public function test_cancel_planned_order_unlocks_vouchers(): void
    {
        $voucher = $this->createIssuedVoucher();
        $so = $this->service->create($this->customer, [$voucher]);
        $this->service->transitionTo($so, ShippingOrderStatus::Planned);

        // Verify voucher is locked
        $voucher->refresh();
        $this->assertEquals(VoucherLifecycleState::Locked, $voucher->lifecycle_state);

        $cancelled = $this->service->cancel($so, 'Changed mind');
        $this->assertEquals(ShippingOrderStatus::Cancelled, $cancelled->status);

        // Voucher should be unlocked back to issued
        $voucher->refresh();
        $this->assertEquals(VoucherLifecycleState::Issued, $voucher->lifecycle_state);
    }

    // --- voucher eligibility ---

    public function test_suspended_voucher_not_eligible(): void
    {
        $voucher = Voucher::factory()->suspended()->create([
            'customer_id' => $this->customer->id,
        ]);

        $result = $this->service->checkVoucherEligibility($voucher);

        $this->assertFalse($result['eligible']);
    }

    public function test_cancelled_voucher_not_eligible(): void
    {
        $voucher = Voucher::factory()->cancelled()->create([
            'customer_id' => $this->customer->id,
        ]);

        $result = $this->service->checkVoucherEligibility($voucher);

        $this->assertFalse($result['eligible']);
    }

    public function test_issued_voucher_is_eligible(): void
    {
        $voucher = $this->createIssuedVoucher();

        $result = $this->service->checkVoucherEligibility($voucher);

        $this->assertTrue($result['eligible']);
    }

    // --- customer validation ---

    public function test_create_rejects_other_customer_vouchers(): void
    {
        $otherCustomer = Customer::factory()->active()->create();
        $voucher = $this->createIssuedVoucher($otherCustomer);

        $this->expectException(InvalidArgumentException::class);

        $this->service->create($this->customer, [$voucher]);
    }
}
