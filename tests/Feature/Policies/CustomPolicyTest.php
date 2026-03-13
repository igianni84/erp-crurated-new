<?php

namespace Tests\Feature\Policies;

use App\Enums\Customer\BlockStatus;
use App\Enums\Customer\BlockType;
use App\Enums\Fulfillment\ShipmentStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Customer\OperationalBlock;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\User;
use App\Policies\Customer\OperationalBlockPolicy;
use App\Policies\Fulfillment\ShipmentPolicy;
use App\Policies\Fulfillment\ShippingOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPolicyTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ShipmentPolicy Tests
    // =========================================================================

    public function test_shipment_any_user_can_view(): void
    {
        $viewer = User::factory()->viewer()->create();
        $shipment = Shipment::factory()->create();
        $policy = new ShipmentPolicy;

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $shipment));
    }

    public function test_shipment_editor_can_update_when_not_delivered(): void
    {
        $editor = User::factory()->editor()->create();
        $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Preparing]);
        $policy = new ShipmentPolicy;

        $this->assertTrue($policy->update($editor, $shipment));
    }

    public function test_shipment_editor_cannot_update_when_delivered(): void
    {
        $editor = User::factory()->editor()->create();
        $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Delivered]);
        $policy = new ShipmentPolicy;

        $this->assertFalse($policy->update($editor, $shipment));
    }

    public function test_shipment_viewer_cannot_update(): void
    {
        $viewer = User::factory()->viewer()->create();
        $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Preparing]);
        $policy = new ShipmentPolicy;

        $this->assertFalse($policy->update($viewer, $shipment));
    }

    public function test_shipment_admin_can_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->create();
        $policy = new ShipmentPolicy;

        $this->assertTrue($policy->delete($admin, $shipment));
    }

    public function test_shipment_no_one_can_force_delete(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $shipment = Shipment::factory()->create();
        $policy = new ShipmentPolicy;

        $this->assertFalse($policy->forceDelete($superAdmin, $shipment));
    }

    // =========================================================================
    // ShippingOrderPolicy Tests
    // =========================================================================

    public function test_shipping_order_any_user_can_view(): void
    {
        $viewer = User::factory()->viewer()->create();
        $order = ShippingOrder::factory()->create();
        $policy = new ShippingOrderPolicy;

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $order));
    }

    public function test_shipping_order_editor_can_update_when_draft(): void
    {
        $editor = User::factory()->editor()->create();
        $order = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Draft]);
        $policy = new ShippingOrderPolicy;

        $this->assertTrue($policy->update($editor, $order));
    }

    public function test_shipping_order_editor_cannot_update_when_completed(): void
    {
        $editor = User::factory()->editor()->create();
        $order = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Completed]);
        $policy = new ShippingOrderPolicy;

        $this->assertFalse($policy->update($editor, $order));
    }

    public function test_shipping_order_editor_cannot_update_when_cancelled(): void
    {
        $editor = User::factory()->editor()->create();
        $order = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Cancelled]);
        $policy = new ShippingOrderPolicy;

        $this->assertFalse($policy->update($editor, $order));
    }

    public function test_shipping_order_viewer_cannot_update(): void
    {
        $viewer = User::factory()->viewer()->create();
        $order = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Draft]);
        $policy = new ShippingOrderPolicy;

        $this->assertFalse($policy->update($viewer, $order));
    }

    public function test_shipping_order_admin_can_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShippingOrder::factory()->create();
        $policy = new ShippingOrderPolicy;

        $this->assertTrue($policy->delete($admin, $order));
    }

    // =========================================================================
    // OperationalBlockPolicy Tests
    // =========================================================================

    public function test_operational_block_any_user_can_view(): void
    {
        $viewer = User::factory()->viewer()->create();
        $customer = \App\Models\Customer\Customer::factory()->create();
        $block = OperationalBlock::create([
            'blockable_type' => 'App\\Models\\Customer\\Customer',
            'blockable_id' => $customer->id,
            'block_type' => BlockType::Payment,
            'reason' => 'Test block',
            'status' => BlockStatus::Active,
        ]);
        $policy = new OperationalBlockPolicy;

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $block));
    }

    public function test_operational_block_admin_can_create(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = new OperationalBlockPolicy;

        $this->assertTrue($policy->create($admin));
    }

    public function test_operational_block_editor_cannot_create(): void
    {
        $editor = User::factory()->editor()->create();
        $policy = new OperationalBlockPolicy;

        $this->assertFalse($policy->create($editor));
    }

    public function test_operational_block_admin_can_update(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = \App\Models\Customer\Customer::factory()->create();
        $block = OperationalBlock::create([
            'blockable_type' => 'App\\Models\\Customer\\Customer',
            'blockable_id' => $customer->id,
            'block_type' => BlockType::Payment,
            'reason' => 'Test block',
            'status' => BlockStatus::Active,
        ]);
        $policy = new OperationalBlockPolicy;

        $this->assertTrue($policy->update($admin, $block));
    }

    public function test_operational_block_editor_cannot_update(): void
    {
        $editor = User::factory()->editor()->create();
        $customer = \App\Models\Customer\Customer::factory()->create();
        $block = OperationalBlock::create([
            'blockable_type' => 'App\\Models\\Customer\\Customer',
            'blockable_id' => $customer->id,
            'block_type' => BlockType::Payment,
            'reason' => 'Test block',
            'status' => BlockStatus::Active,
        ]);
        $policy = new OperationalBlockPolicy;

        $this->assertFalse($policy->update($editor, $block));
    }

    public function test_operational_block_no_one_can_force_delete(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $customer = \App\Models\Customer\Customer::factory()->create();
        $block = OperationalBlock::create([
            'blockable_type' => 'App\\Models\\Customer\\Customer',
            'blockable_id' => $customer->id,
            'block_type' => BlockType::Payment,
            'reason' => 'Test block',
            'status' => BlockStatus::Active,
        ]);
        $policy = new OperationalBlockPolicy;

        $this->assertFalse($policy->forceDelete($superAdmin, $block));
    }
}
