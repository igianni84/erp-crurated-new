<?php

namespace Tests\Unit\AI\Tools\Fulfillment;

use App\AI\Tools\Fulfillment\PendingShippingOrdersTool;
use App\AI\Tools\Fulfillment\ShipmentsInTransitTool;
use App\AI\Tools\Fulfillment\ShipmentStatusTool;
use App\Enums\Fulfillment\ShipmentStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Enums\UserRole;
use App\Models\Customer\Customer;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Inventory\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Unit tests for Fulfillment AI tools:
 * - PendingShippingOrdersTool
 * - ShipmentStatusTool
 * - ShipmentsInTransitTool
 */
class FulfillmentToolsTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $this->warehouse = Location::create([
            'name' => 'Main Warehouse',
            'location_type' => LocationType::MainWarehouse,
            'country' => 'UK',
            'status' => LocationStatus::Active,
            'serialization_authorized' => true,
        ]);
    }

    /**
     * Helper: create a ShippingOrder with sensible defaults.
     */
    private function createShippingOrder(array $overrides = []): ShippingOrder
    {
        return ShippingOrder::create(array_merge([
            'customer_id' => $this->customer->id,
            'source_warehouse_id' => $this->warehouse->id,
            'status' => ShippingOrderStatus::Draft,
        ], $overrides));
    }

    /**
     * Helper: create a Shipment with sensible defaults.
     */
    private function createShipment(ShippingOrder $order, array $overrides = []): Shipment
    {
        return Shipment::create(array_merge([
            'shipping_order_id' => $order->id,
            'carrier' => 'DHL',
            'tracking_number' => 'TRACK-'.fake()->unique()->numerify('######'),
            'status' => ShipmentStatus::Preparing,
            'shipped_bottle_serials' => ['BTL-000001', 'BTL-000002'],
            'origin_warehouse_id' => $this->warehouse->id,
            'destination_address' => '123 Test Street, London, UK',
        ], $overrides));
    }

    // =========================================================================
    // PendingShippingOrdersTool
    // =========================================================================

    public function test_pending_shipping_orders_happy_path(): void
    {
        // Create orders in various non-terminal statuses
        $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);
        $this->createShippingOrder(['status' => ShippingOrderStatus::Planned]);
        $this->createShippingOrder(['status' => ShippingOrderStatus::Picking]);

        // Create a completed and cancelled order (terminal -- should be excluded)
        $completedOrder = $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);
        $completedOrder->status = ShippingOrderStatus::Planned;
        $completedOrder->save();
        $completedOrder->status = ShippingOrderStatus::Picking;
        $completedOrder->save();
        $completedOrder->status = ShippingOrderStatus::Shipped;
        $completedOrder->save();
        $completedOrder->status = ShippingOrderStatus::Completed;
        $completedOrder->save();

        $cancelledOrder = $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);
        $cancelledOrder->status = ShippingOrderStatus::Cancelled;
        $cancelledOrder->save();

        $tool = new PendingShippingOrdersTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_pending', $data);
        $this->assertArrayHasKey('by_status', $data);
        $this->assertArrayHasKey('orders', $data);

        // Only 3 non-terminal orders
        $this->assertEquals(3, $data['total_pending']);

        // Verify by_status breakdown (only non-terminal statuses in keys)
        $this->assertArrayHasKey('Draft', $data['by_status']);
        $this->assertArrayHasKey('Planned', $data['by_status']);
        $this->assertArrayHasKey('Picking', $data['by_status']);

        // Each order should have the expected structure
        $this->assertCount(3, $data['orders']);
        foreach ($data['orders'] as $order) {
            $this->assertArrayHasKey('id', $order);
            $this->assertArrayHasKey('customer_name', $order);
            $this->assertArrayHasKey('status', $order);
            $this->assertArrayHasKey('line_count', $order);
            $this->assertArrayHasKey('created_at', $order);
        }
    }

    public function test_pending_shipping_orders_filters_by_status(): void
    {
        $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);
        $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);
        $this->createShippingOrder(['status' => ShippingOrderStatus::Planned]);

        $tool = new PendingShippingOrdersTool;

        // Filter by 'draft' status
        $result = $tool->handle(new Request(['status' => 'draft']));
        $data = json_decode($result, true);

        $this->assertEquals(2, $data['total_pending']);
        foreach ($data['orders'] as $order) {
            $this->assertEquals('Draft', $order['status']);
        }

        // Filter by 'planned' status
        $result = $tool->handle(new Request(['status' => 'planned']));
        $data = json_decode($result, true);

        $this->assertEquals(1, $data['total_pending']);
        $this->assertEquals('Planned', $data['orders'][0]['status']);
    }

    public function test_pending_shipping_orders_respects_limit(): void
    {
        // Create 5 draft orders
        for ($i = 0; $i < 5; $i++) {
            $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);
        }

        $tool = new PendingShippingOrdersTool;
        $result = $tool->handle(new Request(['limit' => 2]));
        $data = json_decode($result, true);

        // total_pending reflects the real count, but orders list is limited
        $this->assertEquals(5, $data['total_pending']);
        $this->assertCount(2, $data['orders']);
    }

    public function test_pending_shipping_orders_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new PendingShippingOrdersTool;

        // PendingShippingOrdersTool requires Basic access level.
        // Viewer maps to Overview (10) which is less than Basic (20).
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_pending_shipping_orders_authorization_editor_allowed(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new PendingShippingOrdersTool;

        // Editor maps to Basic (20) which equals Basic (20).
        $this->assertTrue($tool->authorizeForUser($editor));
    }

    // =========================================================================
    // ShipmentStatusTool
    // =========================================================================

    public function test_shipment_status_by_tracking_number(): void
    {
        $order = $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);
        $shipment = $this->createShipment($order, [
            'tracking_number' => 'TRK-UNIQUE-123',
            'status' => ShipmentStatus::Shipped,
            'carrier' => 'FedEx',
            'shipped_at' => now()->subDay(),
        ]);

        $tool = new ShipmentStatusTool;
        $result = $tool->handle(new Request(['tracking_number' => 'TRK-UNIQUE-123']));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('shipment', $data);

        $shipmentData = $data['shipment'];
        $this->assertEquals('TRK-UNIQUE-123', $shipmentData['tracking_number']);
        $this->assertEquals('Shipped', $shipmentData['status']);
        $this->assertEquals('FedEx', $shipmentData['carrier']);
        $this->assertArrayHasKey('customer_name', $shipmentData);
        $this->assertArrayHasKey('shipped_at', $shipmentData);
        $this->assertArrayHasKey('delivered_at', $shipmentData);
        $this->assertArrayHasKey('shipping_order_id', $shipmentData);
    }

    public function test_shipment_status_tracking_not_found(): void
    {
        $tool = new ShipmentStatusTool;
        $result = $tool->handle(new Request(['tracking_number' => 'NONEXISTENT']));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('NONEXISTENT', $data['message']);
    }

    public function test_shipment_status_filters_by_status_and_period(): void
    {
        $order = $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);

        // Create a shipment within last 7 days with status "shipped"
        $recentShipped = $this->createShipment($order, [
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now()->subDays(2),
        ]);

        // Create a shipment within last 7 days with status "preparing"
        $this->createShipment($order, [
            'status' => ShipmentStatus::Preparing,
        ]);

        // Create a shipment outside last 7 days -- must backdate created_at via DB
        $oldShipped = $this->createShipment($order, [
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now()->subDays(15),
        ]);
        Shipment::withoutTimestamps(function () use ($oldShipped) {
            $oldShipped->forceFill(['created_at' => now()->subDays(15)])->save();
        });

        $tool = new ShipmentStatusTool;

        // Filter by status=shipped, period=last_7_days (default)
        $result = $tool->handle(new Request(['status' => 'shipped', 'period' => 'last_7_days']));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('shipments', $data);

        // Only 1 shipped shipment within last 7 days
        $this->assertEquals(1, $data['total']);
        $this->assertEquals('Shipped', $data['shipments'][0]['status']);
    }

    public function test_shipment_status_overview_without_tracking(): void
    {
        $order = $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);

        // Create multiple shipments within this month
        $this->createShipment($order, [
            'status' => ShipmentStatus::Preparing,
            'created_at' => now(),
        ]);
        $this->createShipment($order, [
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now(),
            'created_at' => now(),
        ]);

        $tool = new ShipmentStatusTool;
        $result = $tool->handle(new Request(['period' => 'this_month']));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('shipments', $data);
        $this->assertEquals(2, $data['total']);
    }

    public function test_shipment_status_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new ShipmentStatusTool;

        // ShipmentStatusTool requires Basic access level.
        // Viewer maps to Overview (10) which is less than Basic (20).
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_shipment_status_authorization_manager_allowed(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $tool = new ShipmentStatusTool;

        // Manager maps to Standard (40) which is greater than Basic (20).
        $this->assertTrue($tool->authorizeForUser($manager));
    }

    // =========================================================================
    // ShipmentsInTransitTool
    // =========================================================================

    public function test_shipments_in_transit_happy_path(): void
    {
        $order = $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);

        // Create non-terminal shipments (preparing, shipped, in_transit)
        $this->createShipment($order, [
            'status' => ShipmentStatus::Preparing,
            'carrier' => 'DHL',
        ]);
        $this->createShipment($order, [
            'status' => ShipmentStatus::Shipped,
            'carrier' => 'FedEx',
            'shipped_at' => now()->subDays(3),
        ]);
        $this->createShipment($order, [
            'status' => ShipmentStatus::InTransit,
            'carrier' => 'UPS',
            'shipped_at' => now()->subDays(5),
        ]);

        // Create terminal shipments (delivered, failed) -- should be excluded
        $deliveredShipment = $this->createShipment($order, [
            'status' => ShipmentStatus::Preparing,
            'carrier' => 'Royal Mail',
            'shipped_at' => now()->subDays(10),
        ]);
        // Transition: preparing -> shipped -> delivered
        $deliveredShipment->status = ShipmentStatus::Shipped;
        $deliveredShipment->save();
        $deliveredShipment->status = ShipmentStatus::Delivered;
        $deliveredShipment->delivered_at = now();
        $deliveredShipment->save();

        $tool = new ShipmentsInTransitTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('count_in_transit', $data);
        $this->assertArrayHasKey('shipments', $data);

        // Only 3 non-terminal shipments
        $this->assertEquals(3, $data['count_in_transit']);
        $this->assertCount(3, $data['shipments']);

        // Verify each shipment has the expected structure
        foreach ($data['shipments'] as $shipment) {
            $this->assertArrayHasKey('tracking_number', $shipment);
            $this->assertArrayHasKey('status', $shipment);
            $this->assertArrayHasKey('customer_name', $shipment);
            $this->assertArrayHasKey('carrier', $shipment);
            $this->assertArrayHasKey('shipped_at', $shipment);
            $this->assertArrayHasKey('days_since_dispatch', $shipment);
        }
    }

    public function test_shipments_in_transit_days_since_dispatch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-15 12:00:00'));

        $order = $this->createShippingOrder(['status' => ShippingOrderStatus::Draft]);

        // Create a shipment shipped 5 days ago
        $this->createShipment($order, [
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now()->subDays(5),
        ]);

        // Create a shipment with null shipped_at (preparing)
        $this->createShipment($order, [
            'status' => ShipmentStatus::Preparing,
            'shipped_at' => null,
        ]);

        $tool = new ShipmentsInTransitTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertEquals(2, $data['count_in_transit']);

        // Find the shipped one -- should have days_since_dispatch = 5
        $shippedEntry = collect($data['shipments'])->firstWhere('status', 'Shipped');
        $this->assertNotNull($shippedEntry);
        $this->assertEquals(5, $shippedEntry['days_since_dispatch']);

        // Find the preparing one -- should have null days_since_dispatch
        $preparingEntry = collect($data['shipments'])->firstWhere('status', 'Preparing');
        $this->assertNotNull($preparingEntry);
        $this->assertNull($preparingEntry['days_since_dispatch']);

        Carbon::setTestNow(); // Reset
    }

    public function test_shipments_in_transit_empty_result(): void
    {
        $tool = new ShipmentsInTransitTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertEquals(0, $data['count_in_transit']);
        $this->assertCount(0, $data['shipments']);
    }

    public function test_shipments_in_transit_authorization_viewer_allowed(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new ShipmentsInTransitTool;

        // ShipmentsInTransitTool requires Overview access level.
        // Viewer maps to Overview (10) which equals Overview (10).
        $this->assertTrue($tool->authorizeForUser($viewer));
    }

    public function test_shipments_in_transit_authorization_null_role_denied(): void
    {
        // The DB schema requires a non-null role, so we create a user
        // then force-set the role property to null to test the guard.
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $user->role = null;

        $tool = new ShipmentsInTransitTool;

        // User with null role should always be denied.
        $this->assertFalse($tool->authorizeForUser($user));
    }
}
