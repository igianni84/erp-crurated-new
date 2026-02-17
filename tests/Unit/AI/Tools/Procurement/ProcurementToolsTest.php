<?php

namespace Tests\Unit\AI\Tools\Procurement;

use App\AI\Tools\Procurement\InboundScheduleTool;
use App\AI\Tools\Procurement\PendingPurchaseOrdersTool;
use App\AI\Tools\Procurement\ProcurementIntentsStatusTool;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\SourcingModel;
use App\Enums\UserRole;
use App\Models\Customer\Party;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ProcurementToolsTest extends TestCase
{
    use RefreshDatabase;

    protected WineMaster $wineMaster;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected CaseConfiguration $caseConfig;

    protected SellableSku $sellableSku;

    protected Party $supplier;

    protected ProcurementIntent $procurementIntent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wineMaster = WineMaster::create([
            'name' => 'Sassicaia',
            'producer' => 'Tenuta San Guido',
            'country' => 'Italy',
        ]);

        $this->wineVariant = WineVariant::create([
            'wine_master_id' => $this->wineMaster->id,
            'vintage_year' => 2020,
        ]);

        $this->format = Format::create([
            'name' => 'Bottle 750ml',
            'volume_ml' => 750,
        ]);

        $this->caseConfig = CaseConfiguration::create([
            'name' => '6 bottles OWC',
            'format_id' => $this->format->id,
            'bottles_per_case' => 6,
            'case_type' => 'owc',
        ]);

        $this->sellableSku = SellableSku::create([
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'case_configuration_id' => $this->caseConfig->id,
            'lifecycle_status' => 'active',
            'source' => 'manual',
        ]);

        $this->supplier = Party::create([
            'legal_name' => 'Supplier Vini Srl',
            'party_type' => PartyType::LegalEntity,
            'status' => PartyStatus::Active,
        ]);

        $this->procurementIntent = ProcurementIntent::create([
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 100,
            'trigger_type' => ProcurementTriggerType::Strategic,
            'sourcing_model' => SourcingModel::Purchase,
            'status' => ProcurementIntentStatus::Approved,
        ]);
    }

    // =========================================================================
    // PendingPurchaseOrdersTool
    // =========================================================================

    public function test_pending_purchase_orders_happy_path(): void
    {
        // Create two non-closed POs and one closed PO
        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 50,
            'unit_cost' => 25.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Draft,
        ]);

        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 30,
            'unit_cost' => 28.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Sent,
        ]);

        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 20,
            'unit_cost' => 22.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Closed,
        ]);

        $tool = new PendingPurchaseOrdersTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_pending', $data);
        $this->assertArrayHasKey('orders', $data);
        // Only 2 non-closed POs should appear
        $this->assertEquals(2, $data['total_pending']);
        $this->assertCount(2, $data['orders']);

        // Verify each order has the expected structure
        foreach ($data['orders'] as $order) {
            $this->assertArrayHasKey('id', $order);
            $this->assertArrayHasKey('status', $order);
            $this->assertArrayHasKey('supplier_name', $order);
            $this->assertArrayHasKey('quantity', $order);
            $this->assertArrayHasKey('unit_cost', $order);
            $this->assertArrayHasKey('currency', $order);
        }
    }

    public function test_pending_purchase_orders_filters_by_status(): void
    {
        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 50,
            'unit_cost' => 25.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Draft,
        ]);

        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 30,
            'unit_cost' => 28.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Sent,
        ]);

        $tool = new PendingPurchaseOrdersTool;
        $result = $tool->handle(new Request(['status' => 'draft']));
        $data = json_decode((string) $result, true);

        $this->assertEquals(1, $data['total_pending']);
        $this->assertCount(1, $data['orders']);
        $this->assertEquals('Draft', $data['orders'][0]['status']);
    }

    public function test_pending_purchase_orders_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new PendingPurchaseOrdersTool;

        // Viewer maps to Overview (10), tool requires Standard (40) => denied
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_pending_purchase_orders_authorization_manager_granted(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $tool = new PendingPurchaseOrdersTool;

        // Manager maps to Standard (40), tool requires Standard (40) => granted
        $this->assertTrue($tool->authorizeForUser($manager));
    }

    // =========================================================================
    // InboundScheduleTool
    // =========================================================================

    public function test_inbound_schedule_happy_path(): void
    {
        $now = Carbon::now();

        // PO with expected delivery within next 30 days (no inbounds = pending arrival)
        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 60,
            'unit_cost' => 30.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'expected_delivery_start' => $now->copy()->addDays(5)->toDateString(),
            'expected_delivery_end' => $now->copy()->addDays(10)->toDateString(),
            'destination_warehouse' => 'Milano Central Warehouse',
            'status' => PurchaseOrderStatus::Confirmed,
        ]);

        // PO with expected delivery outside range (60 days)
        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 40,
            'unit_cost' => 35.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'expected_delivery_start' => $now->copy()->addDays(60)->toDateString(),
            'status' => PurchaseOrderStatus::Sent,
        ]);

        $tool = new InboundScheduleTool;
        $result = $tool->handle(new Request(['days_ahead' => 30]));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_pending_arrival', $data);
        $this->assertArrayHasKey('total_bottles', $data);
        $this->assertArrayHasKey('by_warehouse', $data);
        $this->assertArrayHasKey('purchase_orders', $data);
        // Only the first PO is within 30 days
        $this->assertEquals(1, $data['total_pending_arrival']);
        $this->assertEquals(60, $data['total_bottles']);
        $this->assertCount(1, $data['purchase_orders']);

        $order = $data['purchase_orders'][0];
        $this->assertArrayHasKey('expected_delivery_start', $order);
        $this->assertArrayHasKey('supplier_name', $order);
        $this->assertArrayHasKey('quantity', $order);
        $this->assertArrayHasKey('status', $order);
        $this->assertArrayHasKey('destination_warehouse', $order);
        $this->assertArrayHasKey('has_inbound', $order);
        $this->assertEquals('Supplier Vini Srl', $order['supplier_name']);
        $this->assertEquals('Milano Central Warehouse', $order['destination_warehouse']);
        $this->assertFalse($order['has_inbound']);

        // Verify warehouse grouping
        $this->assertArrayHasKey('Milano Central Warehouse', $data['by_warehouse']);
        $this->assertEquals(1, $data['by_warehouse']['Milano Central Warehouse']['count']);
        $this->assertEquals(60, $data['by_warehouse']['Milano Central Warehouse']['total_bottles']);
    }

    public function test_inbound_schedule_excludes_pos_with_inbounds(): void
    {
        $now = Carbon::now();

        // PO that already has an inbound record (goods already received)
        $poWithInbound = PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 60,
            'unit_cost' => 30.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'expected_delivery_start' => $now->copy()->addDays(5)->toDateString(),
            'status' => PurchaseOrderStatus::Confirmed,
        ]);

        // Create an inbound record for this PO (goods already received)
        \App\Models\Procurement\Inbound::create([
            'purchase_order_id' => $poWithInbound->id,
            'warehouse' => 'Milano Central Warehouse',
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'received_date' => $now->copy()->addDays(3)->toDateString(),
            'quantity' => 60,
            'packaging' => 'cases',
            'status' => 'recorded',
        ]);

        // PO without inbound (still pending)
        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 40,
            'unit_cost' => 35.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'expected_delivery_start' => $now->copy()->addDays(10)->toDateString(),
            'status' => PurchaseOrderStatus::Sent,
        ]);

        $tool = new InboundScheduleTool;

        // Default: excludes POs with inbounds
        $result = $tool->handle(new Request(['days_ahead' => 30]));
        $data = json_decode((string) $result, true);
        $this->assertEquals(1, $data['total_pending_arrival']);

        // With include flag: shows all
        $resultAll = $tool->handle(new Request(['days_ahead' => 30, 'include_confirmed_with_inbounds' => 'true']));
        $dataAll = json_decode((string) $resultAll, true);
        $this->assertEquals(2, $dataAll['total_pending_arrival']);
    }

    public function test_inbound_schedule_authorization_editor_denied(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $tool = new InboundScheduleTool;

        // Editor maps to Basic (20), tool requires Standard (40) => denied
        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_inbound_schedule_authorization_manager_granted(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $tool = new InboundScheduleTool;

        // Manager maps to Standard (40), tool requires Standard (40) => granted
        $this->assertTrue($tool->authorizeForUser($manager));
    }

    // =========================================================================
    // ProcurementIntentsStatusTool
    // =========================================================================

    public function test_procurement_intents_status_happy_path(): void
    {
        // Create intents with different statuses
        ProcurementIntent::create([
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 10,
            'trigger_type' => ProcurementTriggerType::Strategic,
            'sourcing_model' => SourcingModel::Purchase,
            'status' => ProcurementIntentStatus::Draft,
        ]);

        ProcurementIntent::create([
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 20,
            'trigger_type' => ProcurementTriggerType::Strategic,
            'sourcing_model' => SourcingModel::Purchase,
            'status' => ProcurementIntentStatus::Executed,
        ]);

        $tool = new ProcurementIntentsStatusTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_intents', $data);
        $this->assertArrayHasKey('by_status', $data);
        $this->assertArrayHasKey('without_purchase_order', $data);

        // setUp created 1 approved + we created 1 draft + 1 executed = 3 total
        $this->assertEquals(3, $data['total_intents']);

        // Verify by_status has all ProcurementIntentStatus labels
        $this->assertArrayHasKey('Draft', $data['by_status']);
        $this->assertArrayHasKey('Approved', $data['by_status']);
        $this->assertArrayHasKey('Executed', $data['by_status']);
        $this->assertArrayHasKey('Closed', $data['by_status']);

        $this->assertEquals(1, $data['by_status']['Draft']);
        $this->assertEquals(1, $data['by_status']['Approved']);
        $this->assertEquals(1, $data['by_status']['Executed']);
        $this->assertEquals(0, $data['by_status']['Closed']);
    }

    public function test_procurement_intents_status_without_purchase_order_count(): void
    {
        // The setUp intent has no PO linked to it via the purchaseOrders relationship.
        // Create a PO linked to the setUp intent
        PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 50,
            'unit_cost' => 25.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Draft,
        ]);

        // Create another intent without any PO
        ProcurementIntent::create([
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 5,
            'trigger_type' => ProcurementTriggerType::Contractual,
            'sourcing_model' => SourcingModel::Purchase,
            'status' => ProcurementIntentStatus::Draft,
        ]);

        $tool = new ProcurementIntentsStatusTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        // 2 total intents, 1 without PO (the new draft one)
        $this->assertEquals(2, $data['total_intents']);
        $this->assertEquals(1, $data['without_purchase_order']);
    }

    public function test_procurement_intents_status_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new ProcurementIntentsStatusTool;

        // Viewer maps to Overview (10), tool requires Standard (40) => denied
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_procurement_intents_status_authorization_manager_granted(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $tool = new ProcurementIntentsStatusTool;

        // Manager maps to Standard (40), tool requires Standard (40) => granted
        $this->assertTrue($tool->authorizeForUser($manager));
    }
}
