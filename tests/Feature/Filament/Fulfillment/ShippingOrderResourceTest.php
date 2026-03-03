<?php

namespace Tests\Feature\Filament\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages\CreateShippingOrder;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages\EditShippingOrder;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages\ListShippingOrders;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages\ViewShippingOrder;
use App\Models\Fulfillment\ShippingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ShippingOrderResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page ───────────────────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListShippingOrders::class)
            ->assertSuccessful();
    }

    public function test_list_shows_shipping_orders(): void
    {
        $this->actingAsSuperAdmin();

        $orders = ShippingOrder::factory()->count(3)->create();

        Livewire::test(ListShippingOrders::class)
            ->assertCanSeeTableRecords($orders);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Draft]);
        $planned = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Planned]);

        Livewire::test(ListShippingOrders::class)
            ->filterTable('status', [ShippingOrderStatus::Draft->value])
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$planned]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateShippingOrder::class)
            ->assertSuccessful();
    }

    public function test_create_validates_required_customer(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateShippingOrder::class)
            ->fillForm([
                'customer_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['customer_id']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders_for_draft(): void
    {
        $this->actingAsSuperAdmin();

        $order = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Draft]);

        Livewire::test(EditShippingOrder::class, ['record' => $order->id])
            ->assertSuccessful();
    }

    public function test_edit_page_blocked_for_non_draft(): void
    {
        $this->actingAsSuperAdmin();

        $order = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Planned]);

        $this->get(EditShippingOrder::getUrl(['record' => $order->id]))
            ->assertForbidden();
    }

    public function test_can_update_draft_shipping_order(): void
    {
        $this->actingAsSuperAdmin();

        $order = ShippingOrder::factory()->create();

        Livewire::test(EditShippingOrder::class, ['record' => $order->id])
            ->fillForm([
                'destination_address' => 'Via Roma 1, 20121 Milano, IT',
                'carrier' => 'DHL',
                'shipping_method' => 'Express',
                'special_instructions' => 'Handle with care',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('shipping_orders', [
            'id' => $order->id,
            'destination_address' => 'Via Roma 1, 20121 Milano, IT',
            'carrier' => 'DHL',
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $order = ShippingOrder::factory()->create();

        Livewire::test(ViewShippingOrder::class, ['record' => $order->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_for_planned_order(): void
    {
        $this->actingAsSuperAdmin();

        $order = ShippingOrder::factory()->create(['status' => ShippingOrderStatus::Planned]);

        Livewire::test(ViewShippingOrder::class, ['record' => $order->id])
            ->assertSuccessful();
    }
}
