<?php

namespace Tests\Feature\Filament\Procurement;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Filament\Resources\Procurement\InboundResource\Pages\CreateInbound;
use App\Filament\Resources\Procurement\InboundResource\Pages\ListInbounds;
use App\Filament\Resources\Procurement\InboundResource\Pages\ViewInbound;
use App\Models\Procurement\Inbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class InboundResourceTest extends TestCase
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

        Livewire::test(ListInbounds::class)
            ->assertSuccessful();
    }

    public function test_list_shows_inbounds(): void
    {
        $this->actingAsSuperAdmin();

        $inbounds = Inbound::factory()->count(3)->create();

        Livewire::test(ListInbounds::class)
            ->assertCanSeeTableRecords($inbounds);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $recorded = Inbound::factory()->create();
        $routed = Inbound::factory()->routed()->create();

        Livewire::test(ListInbounds::class)
            ->filterTable('status', [InboundStatus::Recorded->value])
            ->assertCanSeeTableRecords([$recorded])
            ->assertCanNotSeeTableRecords([$routed]);
    }

    public function test_list_can_filter_by_ownership_flag(): void
    {
        $this->actingAsSuperAdmin();

        $owned = Inbound::factory()->create(['ownership_flag' => OwnershipFlag::Owned]);
        $pending = Inbound::factory()->pendingOwnership()->create();

        Livewire::test(ListInbounds::class)
            ->filterTable('ownership_flag', [OwnershipFlag::Owned->value])
            ->assertCanSeeTableRecords([$owned])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    // ── Create Page (Wizard) ────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateInbound::class)
            ->assertSuccessful();
    }

    public function test_can_create_inbound_via_wizard(): void
    {
        $this->actingAsSuperAdmin();

        $pimStack = $this->createPimStack();

        Livewire::test(CreateInbound::class)
            ->fillForm([
                'warehouse' => 'main_warehouse',
                'received_date' => now()->format('Y-m-d'),
                'quantity' => 12,
                'packaging' => InboundPackaging::Cases->value,
                'ownership_flag' => OwnershipFlag::Owned->value,
                'product_reference_type' => 'sellable_skus',
                'product_reference_id' => $pimStack['sellable_sku']->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inbounds', [
            'warehouse' => 'main_warehouse',
            'quantity' => 12,
            'packaging' => InboundPackaging::Cases->value,
            'ownership_flag' => OwnershipFlag::Owned->value,
            'status' => InboundStatus::Recorded->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $inbound = Inbound::factory()->create();

        Livewire::test(ViewInbound::class, ['record' => $inbound->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_completed_inbound(): void
    {
        $this->actingAsSuperAdmin();

        $inbound = Inbound::factory()->completed()->create();

        Livewire::test(ViewInbound::class, ['record' => $inbound->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListInbounds::class)
            ->assertSuccessful();
    }

    public function test_viewer_can_view_inbound(): void
    {
        $this->actingAsViewer();

        $inbound = Inbound::factory()->create();

        Livewire::test(ViewInbound::class, ['record' => $inbound->id])
            ->assertSuccessful();
    }
}
