<?php

namespace Tests\Feature\Services\Fulfillment;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Fulfillment\ShipmentStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Jobs\Fulfillment\UpdateProvenanceOnShipmentJob;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use App\Services\Fulfillment\LateBindingService;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class ShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShipmentService $service;

    private LateBindingService $lateBindingService;

    private Allocation $allocation;

    private Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShipmentService::class);
        $this->lateBindingService = app(LateBindingService::class);
        $this->allocation = Allocation::factory()->active()->create();
        $this->warehouse = Location::factory()->warehouse()->create();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Create a fully-bound shipping order ready for shipment creation.
     *
     * @return array{so: ShippingOrder, bottles: array<SerializedBottle>, vouchers: array<Voucher>}
     */
    private function createBoundSO(int $lineCount = 1): array
    {
        $so = ShippingOrder::factory()->create([
            'source_warehouse_id' => $this->warehouse->id,
            'status' => ShippingOrderStatus::Picking,
        ]);

        $bottles = [];
        $vouchers = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $voucher = Voucher::factory()->locked()->create([
                'allocation_id' => $this->allocation->id,
                'suspended' => false,
            ]);
            $serial = 'SB-SHIP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $bottle = SerializedBottle::factory()->create([
                'allocation_id' => $this->allocation->id,
                'current_location_id' => $this->warehouse->id,
                'state' => BottleState::Stored,
                'serial_number' => $serial,
            ]);
            $line = ShippingOrderLine::factory()->validated()->create([
                'shipping_order_id' => $so->id,
                'voucher_id' => $voucher->id,
                'allocation_id' => $this->allocation->id,
            ]);

            // Bind the line
            $this->lateBindingService->bindVoucherToBottle($line, $serial);

            $bottles[] = $bottle;
            $vouchers[] = $voucher;
        }

        /** @var ShippingOrder $freshSo */
        $freshSo = $so->fresh();

        return ['so' => $freshSo, 'bottles' => $bottles, 'vouchers' => $vouchers];
    }

    // --- createFromOrder ---

    public function test_create_from_order_happy_path(): void
    {
        $data = $this->createBoundSO(2);

        $shipment = $this->service->createFromOrder($data['so']);

        $this->assertEquals(ShipmentStatus::Preparing, $shipment->status);
        $this->assertCount(2, $shipment->shipped_bottle_serials);
        $this->assertEquals($data['so']->id, $shipment->shipping_order_id);
    }

    public function test_create_rejects_non_picking_so(): void
    {
        $so = ShippingOrder::factory()->create([
            'status' => ShippingOrderStatus::Draft,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Picking status');

        $this->service->createFromOrder($so);
    }

    public function test_create_rejects_unbound_lines(): void
    {
        $so = ShippingOrder::factory()->create([
            'source_warehouse_id' => $this->warehouse->id,
            'status' => ShippingOrderStatus::Picking,
        ]);
        ShippingOrderLine::factory()->validated()->create([
            'shipping_order_id' => $so->id,
            'allocation_id' => $this->allocation->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not all lines are bound');

        /** @var ShippingOrder $freshSo */
        $freshSo = $so->fresh();
        $this->service->createFromOrder($freshSo);
    }

    // --- confirmShipment ---

    public function test_confirm_sets_shipped_status(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);

        $confirmed = $this->service->confirmShipment($shipment, 'TRK-12345');

        $this->assertEquals(ShipmentStatus::Shipped, $confirmed->status);
        $this->assertEquals('TRK-12345', $confirmed->tracking_number);
        $this->assertNotNull($confirmed->shipped_at);
    }

    public function test_confirm_rejects_non_preparing(): void
    {
        $shipment = Shipment::factory()->shipped()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not in Preparing status');

        $this->service->confirmShipment($shipment, 'TRK-99999');
    }

    public function test_confirm_rejects_empty_tracking(): void
    {
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tracking number is required');

        $this->service->confirmShipment($shipment, '');
    }

    public function test_confirm_triggers_redemption(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);

        $this->service->confirmShipment($shipment, 'TRK-REDEEM');

        // Voucher should be redeemed
        $data['vouchers'][0]->refresh();
        $this->assertEquals(VoucherLifecycleState::Redeemed, $data['vouchers'][0]->lifecycle_state);
    }

    public function test_confirm_triggers_ownership_transfer(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);

        $this->service->confirmShipment($shipment, 'TRK-TRANSFER');

        // Bottle should be shipped
        $data['bottles'][0]->refresh();
        $this->assertEquals(BottleState::Shipped, $data['bottles'][0]->state);
    }

    public function test_confirm_dispatches_provenance_job(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);

        $this->service->confirmShipment($shipment, 'TRK-PROVENANCE');

        Queue::assertPushed(UpdateProvenanceOnShipmentJob::class);
    }

    public function test_confirm_updates_so_status(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);

        $this->service->confirmShipment($shipment, 'TRK-SO-STATUS');

        $data['so']->refresh();
        $this->assertEquals(ShippingOrderStatus::Shipped, $data['so']->status);
    }

    // --- markDelivered ---

    public function test_mark_delivered(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);
        $confirmed = $this->service->confirmShipment($shipment, 'TRK-DELIVER');

        $delivered = $this->service->markDelivered($confirmed);

        $this->assertEquals(ShipmentStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
    }

    public function test_mark_delivered_updates_so(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);
        $confirmed = $this->service->confirmShipment($shipment, 'TRK-DELIVER-SO');

        $this->service->markDelivered($confirmed);

        $data['so']->refresh();
        $this->assertEquals(ShippingOrderStatus::Completed, $data['so']->status);
    }

    public function test_mark_delivered_rejects_non_shipped(): void
    {
        $shipment = Shipment::factory()->create([
            'status' => ShipmentStatus::Preparing,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');

        $this->service->markDelivered($shipment);
    }

    // --- markFailed ---

    public function test_mark_failed(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);
        $confirmed = $this->service->confirmShipment($shipment, 'TRK-FAIL');

        $failed = $this->service->markFailed($confirmed, 'Package damaged in transit');

        $this->assertEquals(ShipmentStatus::Failed, $failed->status);
        $this->assertStringContainsString('Package damaged', (string) $failed->notes);
    }

    public function test_mark_failed_rejects_terminal(): void
    {
        Queue::fake([UpdateProvenanceOnShipmentJob::class]);
        $data = $this->createBoundSO(1);
        $shipment = $this->service->createFromOrder($data['so']);
        $confirmed = $this->service->confirmShipment($shipment, 'TRK-FAIL-TERM');
        $this->service->markDelivered($confirmed);

        $this->expectException(InvalidArgumentException::class);

        $freshConfirmed = $confirmed->fresh();
        $this->assertNotNull($freshConfirmed);
        $this->service->markFailed($freshConfirmed, 'Too late');
    }

    // --- validateForShipment ---

    public function test_validate_for_shipment(): void
    {
        $data = $this->createBoundSO(1);

        $result = $this->service->validateForShipment($data['so']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_for_shipment_wrong_status(): void
    {
        $so = ShippingOrder::factory()->create([
            'status' => ShippingOrderStatus::Draft,
        ]);

        $result = $this->service->validateForShipment($so);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}
