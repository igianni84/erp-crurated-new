<?php

namespace Tests\Feature\Filament\Fulfillment;

use App\Filament\Resources\Fulfillment\ShipmentResource\Pages\ListShipments;
use App\Filament\Resources\Fulfillment\ShipmentResource\Pages\ViewShipment;
use App\Models\Fulfillment\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ShipmentResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page (Read-Only) ───────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListShipments::class)
            ->assertSuccessful();
    }

    public function test_list_shows_shipments(): void
    {
        $this->actingAsSuperAdmin();

        $shipments = Shipment::factory()->count(3)->create();

        Livewire::test(ListShipments::class)
            ->assertCanSeeTableRecords($shipments);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $shipment = Shipment::factory()->create();

        Livewire::test(ViewShipment::class, ['record' => $shipment->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListShipments::class)
            ->assertSuccessful();
    }
}
