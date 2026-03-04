<?php

namespace Tests\Feature\Filament\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Resources\Procurement\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\Procurement\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Resources\Procurement\PurchaseOrderResource\Pages\ViewPurchaseOrder;
use App\Models\Customer\Party;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class PurchaseOrderResourceTest extends TestCase
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

        Livewire::test(ListPurchaseOrders::class)
            ->assertSuccessful();
    }

    public function test_list_shows_purchase_orders(): void
    {
        $this->actingAsSuperAdmin();

        $orders = PurchaseOrder::factory()->count(3)->create();

        Livewire::test(ListPurchaseOrders::class)
            ->assertCanSeeTableRecords($orders);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = PurchaseOrder::factory()->create();
        $confirmed = PurchaseOrder::factory()->confirmed()->create();

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('status', [PurchaseOrderStatus::Draft->value])
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$confirmed]);
    }

    public function test_list_can_filter_by_ownership_transfer(): void
    {
        $this->actingAsSuperAdmin();

        $withTransfer = PurchaseOrder::factory()->create(['ownership_transfer' => true]);
        $withoutTransfer = PurchaseOrder::factory()->create(['ownership_transfer' => false]);

        Livewire::test(ListPurchaseOrders::class)
            ->filterTable('ownership_transfer', true)
            ->assertCanSeeTableRecords([$withTransfer])
            ->assertCanNotSeeTableRecords([$withoutTransfer]);
    }

    // ── Create Page (Wizard) ────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreatePurchaseOrder::class)
            ->assertSuccessful();
    }

    public function test_can_create_purchase_order_via_wizard(): void
    {
        $this->actingAsSuperAdmin();

        $intent = ProcurementIntent::factory()->approved()->create();
        $supplier = Party::factory()->create();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'procurement_intent_id' => $intent->id,
                'supplier_party_id' => $supplier->id,
                'quantity' => 24,
                'unit_cost' => 150.00,
                'currency' => 'EUR',
                'ownership_transfer' => true,
                'expected_delivery_start' => now()->addDays(14)->format('Y-m-d'),
                'expected_delivery_end' => now()->addDays(30)->format('Y-m-d'),
                'destination_warehouse' => 'main_warehouse',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'procurement_intent_id' => $intent->id,
            'supplier_party_id' => $supplier->id,
            'quantity' => 24,
            'currency' => 'EUR',
            'status' => PurchaseOrderStatus::Draft->value,
        ]);
    }

    public function test_create_validates_required_intent(): void
    {
        $this->actingAsSuperAdmin();

        $supplier = Party::factory()->create();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'procurement_intent_id' => null,
                'supplier_party_id' => $supplier->id,
                'quantity' => 24,
                'unit_cost' => 150.00,
                'currency' => 'EUR',
            ])
            ->call('create')
            ->assertHasFormErrors(['procurement_intent_id']);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $order = PurchaseOrder::factory()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $order->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_confirmed_order(): void
    {
        $this->actingAsSuperAdmin();

        $order = PurchaseOrder::factory()->confirmed()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $order->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListPurchaseOrders::class)
            ->assertSuccessful();
    }

    public function test_viewer_can_view_purchase_order(): void
    {
        $this->actingAsViewer();

        $order = PurchaseOrder::factory()->create();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $order->id])
            ->assertSuccessful();
    }
}
