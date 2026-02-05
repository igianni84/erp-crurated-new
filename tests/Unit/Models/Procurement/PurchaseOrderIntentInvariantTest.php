<?php

namespace Tests\Unit\Models\Procurement;

use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\SourcingModel;
use App\Models\Customer\Party;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for PurchaseOrder Intent-before-PO invariant enforcement.
 *
 * These tests verify that:
 * 1. A PurchaseOrder CANNOT be created without a ProcurementIntent (model validation)
 * 2. The error message is user-friendly and descriptive
 * 3. A PurchaseOrder CAN be created when a valid ProcurementIntent is provided
 *
 * @see US-057 in PRD
 */
class PurchaseOrderIntentInvariantTest extends TestCase
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

        // Create required base models for testing
        $this->wineMaster = WineMaster::create([
            'name' => 'Test Wine PO',
            'producer' => 'Test Producer',
            'country' => 'France',
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
            'name' => '6 bottles',
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
            'legal_name' => 'Test Supplier Co.',
            'party_type' => PartyType::LegalEntity,
            'status' => PartyStatus::Active,
        ]);

        // Create a valid ProcurementIntent for use in tests
        $this->procurementIntent = ProcurementIntent::create([
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 100,
            'trigger_type' => ProcurementTriggerType::Strategic,
            'sourcing_model' => SourcingModel::Purchase,
            'status' => ProcurementIntentStatus::Approved,
        ]);
    }

    /**
     * Test that PO creation without procurement_intent_id throws exception.
     * This is the primary acceptance criterion for US-057.
     */
    public function test_po_creation_without_intent_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A Purchase Order cannot exist without a Procurement Intent');

        // Attempt to create PO without procurement_intent_id
        PurchaseOrder::create([
            'procurement_intent_id' => null, // Explicitly null
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 50,
            'unit_cost' => 25.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Draft,
        ]);
    }

    /**
     * Test that PO creation with empty procurement_intent_id throws exception.
     */
    public function test_po_creation_with_empty_intent_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A Purchase Order cannot exist without a Procurement Intent');

        // Attempt to create PO with empty string
        PurchaseOrder::create([
            'procurement_intent_id' => '',
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 50,
            'unit_cost' => 25.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Draft,
        ]);
    }

    /**
     * Test that PO creation with valid procurement_intent_id succeeds.
     */
    public function test_po_creation_with_intent_succeeds(): void
    {
        $purchaseOrder = PurchaseOrder::create([
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

        $this->assertNotEmpty($purchaseOrder->id);
        $this->assertEquals($this->procurementIntent->id, $purchaseOrder->procurement_intent_id);
        $this->assertEquals($this->supplier->id, $purchaseOrder->supplier_party_id);
        $this->assertEquals(50, $purchaseOrder->quantity);
        $this->assertTrue($purchaseOrder->isDraft());
    }

    /**
     * Test that the PO-Intent relationship is correctly established.
     */
    public function test_po_belongs_to_procurement_intent(): void
    {
        $purchaseOrder = PurchaseOrder::create([
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

        // Verify the relationship works
        $this->assertNotNull($purchaseOrder->procurementIntent);
        $this->assertEquals($this->procurementIntent->id, $purchaseOrder->procurementIntent->id);
        $this->assertEquals(100, $purchaseOrder->procurementIntent->quantity);
    }

    /**
     * Test that Intent can have multiple POs linked.
     */
    public function test_intent_can_have_multiple_pos(): void
    {
        // Create two POs linked to the same intent
        $po1 = PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 30,
            'unit_cost' => 25.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Draft,
        ]);

        $po2 = PurchaseOrder::create([
            'procurement_intent_id' => $this->procurementIntent->id,
            'supplier_party_id' => $this->supplier->id,
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $this->sellableSku->id,
            'quantity' => 20,
            'unit_cost' => 24.00,
            'currency' => 'EUR',
            'ownership_transfer' => true,
            'status' => PurchaseOrderStatus::Draft,
        ]);

        // Refresh the intent to get the relationship
        $this->procurementIntent->refresh();

        $this->assertEquals(2, $this->procurementIntent->purchaseOrders()->count());
        $poIds = $this->procurementIntent->purchaseOrders()->pluck('id')->toArray();
        $this->assertContains($po1->id, $poIds);
        $this->assertContains($po2->id, $poIds);
    }

    /**
     * Test that updating procurement_intent_id to empty throws exception.
     */
    public function test_updating_intent_to_empty_throws_exception(): void
    {
        $purchaseOrder = PurchaseOrder::create([
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A Purchase Order cannot exist without a Procurement Intent');

        // Attempt to update procurement_intent_id to empty string
        // (null would fail PHPStan since the property is typed as string)
        $purchaseOrder->procurement_intent_id = '';
        $purchaseOrder->save();
    }
}
