<?php

namespace Tests\Feature\Services\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use App\Services\Fulfillment\LateBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class LateBindingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LateBindingService $service;

    private Allocation $allocation;

    private Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LateBindingService::class);
        $this->allocation = Allocation::factory()->active()->create();
        $this->warehouse = Location::factory()->warehouse()->create();

        $this->actingAs(User::factory()->create());
        Cache::flush();
    }

    /**
     * Create a shipping order in Picking status with lines ready for binding.
     */
    private function createPickingSO(int $lineCount = 1): ShippingOrder
    {
        $so = ShippingOrder::factory()->create([
            'source_warehouse_id' => $this->warehouse->id,
            'status' => ShippingOrderStatus::Picking,
        ]);

        for ($i = 0; $i < $lineCount; $i++) {
            $voucher = Voucher::factory()->locked()->create([
                'allocation_id' => $this->allocation->id,
            ]);
            ShippingOrderLine::factory()->validated()->create([
                'shipping_order_id' => $so->id,
                'voucher_id' => $voucher->id,
                'allocation_id' => $this->allocation->id,
            ]);
        }

        /** @var ShippingOrder $freshSo */
        $freshSo = $so->fresh();

        return $freshSo;
    }

    /**
     * Create a stored bottle matching our allocation.
     */
    private function createStoredBottle(?string $serial = null): SerializedBottle
    {
        return SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
            'current_location_id' => $this->warehouse->id,
            'state' => BottleState::Stored,
            'serial_number' => $serial ?? 'SB-'.fake()->unique()->numerify('############'),
        ]);
    }

    // --- requestEligibleInventory ---

    public function test_eligible_inventory_returns_available_bottles(): void
    {
        $so = $this->createPickingSO(2);
        $bottle1 = $this->createStoredBottle();
        $bottle2 = $this->createStoredBottle();

        $result = $this->service->requestEligibleInventory($so);

        $this->assertTrue($result['all_available']);
        $this->assertArrayHasKey($this->allocation->id, $result['allocations']);

        $allocationResult = $result['allocations'][$this->allocation->id];
        $this->assertEquals(2, $allocationResult['required_quantity']);
        $this->assertGreaterThanOrEqual(2, $allocationResult['available_quantity']);
        $this->assertEquals('sufficient', $allocationResult['status']);
    }

    public function test_eligible_inventory_reports_insufficient(): void
    {
        $so = $this->createPickingSO(3);
        // Only 1 bottle available, need 3
        $this->createStoredBottle();

        $result = $this->service->requestEligibleInventory($so);

        $this->assertFalse($result['all_available']);
        $allocationResult = $result['allocations'][$this->allocation->id];
        $this->assertEquals('insufficient', $allocationResult['status']);
    }

    public function test_eligible_inventory_caches_results(): void
    {
        $so = $this->createPickingSO(1);
        $this->createStoredBottle();

        // First call
        $this->service->requestEligibleInventory($so);

        // Create more bottles (should NOT appear due to cache)
        $this->createStoredBottle();

        $result = $this->service->requestEligibleInventory($so);
        $allocationResult = $result['allocations'][$this->allocation->id];
        // Should still show 1 (cached), not 2
        $this->assertEquals(1, $allocationResult['available_quantity']);
    }

    // --- bindVoucherToBottle ---

    public function test_bind_happy_path(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $bottle = $this->createStoredBottle('SB-BIND-001');

        $result = $this->service->bindVoucherToBottle($line, 'SB-BIND-001');

        $this->assertEquals('SB-BIND-001', $result->bound_bottle_serial);
        $this->assertNotNull($result->binding_confirmed_at);

        // Bottle should be reserved
        $bottle->refresh();
        $this->assertEquals(BottleState::ReservedForPicking, $bottle->state);
    }

    public function test_bind_rejects_already_bound(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $bottle1 = $this->createStoredBottle('SB-BIND-002');
        $bottle2 = $this->createStoredBottle('SB-BIND-003');

        $this->service->bindVoucherToBottle($line, 'SB-BIND-002');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already bound');

        $line->refresh();
        $this->service->bindVoucherToBottle($line, 'SB-BIND-003');
    }

    public function test_bind_rejects_nonexistent_serial(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        $this->service->bindVoucherToBottle($line, 'SB-NONEXISTENT');
    }

    public function test_bind_enforces_allocation_lineage(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);

        // Bottle from different allocation
        $otherAllocation = Allocation::factory()->active()->create();
        SerializedBottle::factory()->create([
            'allocation_id' => $otherAllocation->id,
            'serial_number' => 'SB-WRONG-ALLOC',
            'state' => BottleState::Stored,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allocation lineage mismatch');

        $this->service->bindVoucherToBottle($line, 'SB-WRONG-ALLOC');
    }

    public function test_bind_rejects_non_stored_bottle(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);

        SerializedBottle::factory()->shipped()->create([
            'allocation_id' => $this->allocation->id,
            'serial_number' => 'SB-SHIPPED',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stored state');

        $this->service->bindVoucherToBottle($line, 'SB-SHIPPED');
    }

    public function test_bind_sets_confirmed_fields(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $this->createStoredBottle('SB-CONFIRM-001');

        $result = $this->service->bindVoucherToBottle($line, 'SB-CONFIRM-001');

        $this->assertNotNull($result->binding_confirmed_at);
        $this->assertNotNull($result->binding_confirmed_by);
    }

    public function test_bind_rejects_pending_line_status(): void
    {
        $so = ShippingOrder::factory()->create([
            'status' => ShippingOrderStatus::Picking,
        ]);
        $line = ShippingOrderLine::factory()->create([
            'shipping_order_id' => $so->id,
            'allocation_id' => $this->allocation->id,
            'status' => ShippingOrderLineStatus::Pending,
        ]);
        $this->createStoredBottle('SB-PENDING-001');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not allow binding');

        $this->service->bindVoucherToBottle($line, 'SB-PENDING-001');
    }

    // --- validateBinding ---

    public function test_validate_binding_passes_valid(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $this->createStoredBottle('SB-VALID-001');
        $this->service->bindVoucherToBottle($line, 'SB-VALID-001');

        $freshLine = $line->fresh();
        $this->assertNotNull($freshLine);
        $result = $this->service->validateBinding($freshLine);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_binding_fails_unbound(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);

        $result = $this->service->validateBinding($line);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('no bound bottle', $result['errors'][0]);
    }

    public function test_validate_binding_fails_missing_bottle(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        // Manually set a serial that doesn't exist
        $line->bound_bottle_serial = 'SB-GHOST-001';
        $line->save();

        $result = $this->service->validateBinding($line);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not found', $result['errors'][0]);
    }

    public function test_validate_binding_fails_terminal_state(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $bottle = $this->createStoredBottle('SB-TERMINAL-001');
        $this->service->bindVoucherToBottle($line, 'SB-TERMINAL-001');

        // Manually mark bottle as shipped (terminal)
        $bottle->state = BottleState::Shipped;
        $bottle->save();

        $freshLine = $line->fresh();
        $this->assertNotNull($freshLine);
        $result = $this->service->validateBinding($freshLine);

        $this->assertFalse($result['valid']);
    }

    // --- unbindLine ---

    public function test_unbind_reverts_to_stored(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $bottle = $this->createStoredBottle('SB-UNBIND-001');
        $this->service->bindVoucherToBottle($line, 'SB-UNBIND-001');

        $freshBottle = $bottle->fresh();
        $this->assertNotNull($freshBottle);
        $this->assertEquals(BottleState::ReservedForPicking, $freshBottle->state);

        $freshLine = $line->fresh();
        $this->assertNotNull($freshLine);
        $result = $this->service->unbindLine($freshLine);

        $this->assertNull($result->bound_bottle_serial);
        $freshBottle2 = $bottle->fresh();
        $this->assertNotNull($freshBottle2);
        $this->assertEquals(BottleState::Stored, $freshBottle2->state);
    }

    public function test_unbind_clears_fields(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $this->createStoredBottle('SB-UNBIND-002');
        $this->service->bindVoucherToBottle($line, 'SB-UNBIND-002');

        $freshLine = $line->fresh();
        $this->assertNotNull($freshLine);
        $result = $this->service->unbindLine($freshLine);

        $this->assertNull($result->bound_bottle_serial);
        $this->assertNull($result->bound_case_id);
        $this->assertNull($result->binding_confirmed_at);
        $this->assertNull($result->binding_confirmed_by);
    }

    public function test_unbind_rejects_shipped(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);
        $this->createStoredBottle('SB-UNBIND-SHIP');
        $this->service->bindVoucherToBottle($line, 'SB-UNBIND-SHIP');

        // Mark as shipped
        $line = $line->fresh();
        $this->assertNotNull($line);
        $line->status = ShippingOrderLineStatus::Shipped;
        $line->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already been shipped');

        $this->service->unbindLine($line);
    }

    public function test_unbind_rejects_unbound(): void
    {
        $so = $this->createPickingSO(1);
        $line = $so->lines->first();
        $this->assertNotNull($line);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no bound bottle');

        $this->service->unbindLine($line);
    }

    // --- checkAllLinesBinding ---

    public function test_check_all_lines_mixed_status(): void
    {
        $so = $this->createPickingSO(3);
        $lines = $so->lines;

        // Bind only the first line
        $bottle = $this->createStoredBottle('SB-MIX-001');
        $firstLine = $lines[0];
        $this->assertNotNull($firstLine);
        $this->service->bindVoucherToBottle($firstLine, 'SB-MIX-001');

        $freshSo = $so->fresh();
        $this->assertNotNull($freshSo);
        $result = $this->service->checkAllLinesBinding($freshSo);

        $this->assertFalse($result['all_bound']);
        $this->assertEquals(1, $result['bound_count']);
        $this->assertEquals(2, $result['unbound_count']);
    }

    // --- validateAllBindings ---

    public function test_validate_all_passes(): void
    {
        $so = $this->createPickingSO(2);
        $lines = $so->lines;

        $bottle1 = $this->createStoredBottle('SB-ALL-001');
        $bottle2 = $this->createStoredBottle('SB-ALL-002');
        $firstLine = $lines[0];
        $this->assertNotNull($firstLine);
        $this->service->bindVoucherToBottle($firstLine, 'SB-ALL-001');
        $secondLine = $lines[1];
        $this->assertNotNull($secondLine);
        $freshLine1 = $secondLine->fresh();
        $this->assertNotNull($freshLine1);
        $this->service->bindVoucherToBottle($freshLine1, 'SB-ALL-002');

        $freshSo = $so->fresh();
        $this->assertNotNull($freshSo);
        $result = $this->service->validateAllBindings($freshSo);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_all_aggregates_errors(): void
    {
        $so = $this->createPickingSO(2);
        // Neither line is bound → both fail

        $freshSo = $so->fresh();
        $this->assertNotNull($freshSo);
        $result = $this->service->validateAllBindings($freshSo);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['errors']);
    }

    // --- clearInventoryCache ---

    public function test_clear_inventory_cache(): void
    {
        $so = $this->createPickingSO(1);
        $this->createStoredBottle();

        // Populate cache
        $this->service->requestEligibleInventory($so);

        // Add another bottle
        $this->createStoredBottle();

        // Still cached (1 bottle)
        $cached = $this->service->requestEligibleInventory($so);
        $this->assertEquals(1, $cached['allocations'][$this->allocation->id]['available_quantity']);

        // Clear cache (with warehouse ID to match the cached key)
        $this->service->clearInventoryCache($this->allocation->id, $this->warehouse->id);

        // Now should see 2
        $freshSo = $so->fresh();
        $this->assertNotNull($freshSo);
        $fresh = $this->service->requestEligibleInventory($freshSo);
        $this->assertEquals(2, $fresh['allocations'][$this->allocation->id]['available_quantity']);
    }
}
