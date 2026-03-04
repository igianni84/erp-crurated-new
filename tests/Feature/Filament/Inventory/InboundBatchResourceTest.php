<?php

namespace Tests\Feature\Filament\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\CreateInboundBatch;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\ListInboundBatches;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\ViewInboundBatch;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Models\Pim\WineVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class InboundBatchResourceTest extends TestCase
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

        Livewire::test(ListInboundBatches::class)
            ->assertSuccessful();
    }

    public function test_list_shows_inbound_batches(): void
    {
        $this->actingAsSuperAdmin();

        $batches = InboundBatch::factory()->count(3)->create();

        Livewire::test(ListInboundBatches::class)
            ->assertCanSeeTableRecords($batches);
    }

    public function test_list_can_filter_by_serialization_status(): void
    {
        $this->actingAsSuperAdmin();

        $pending = InboundBatch::factory()->create([
            'serialization_status' => InboundBatchStatus::PendingSerialization,
        ]);
        $fullySerialized = InboundBatch::factory()->fullySerialized()->create();

        Livewire::test(ListInboundBatches::class)
            ->filterTable('serialization_status', InboundBatchStatus::PendingSerialization->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$fullySerialized]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateInboundBatch::class)
            ->assertSuccessful();
    }

    public function test_can_create_inbound_batch(): void
    {
        $this->actingAsSuperAdmin();

        $wineVariant = WineVariant::factory()->create();
        $allocation = Allocation::factory()->create();
        $location = Location::factory()->create();

        Livewire::test(CreateInboundBatch::class)
            ->fillForm([
                'manual_creation_reason' => 'WMS system failure during import — legacy data migration required for reconciliation.',
                'source_type' => 'producer',
                'product_reference_type' => WineVariant::class,
                'product_reference_id' => $wineVariant->id,
                'allocation_id' => $allocation->id,
                'quantity_expected' => 12,
                'quantity_received' => 12,
                'packaging_type' => 'bottles',
                'receiving_location_id' => $location->id,
                'ownership_type' => OwnershipType::CururatedOwned->value,
                'received_date' => now()->toDateString(),
                'serialization_status' => InboundBatchStatus::PendingSerialization->value,
                'audit_confirmation' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inbound_batches', [
            'source_type' => 'producer',
            'product_reference_type' => WineVariant::class,
            'product_reference_id' => $wineVariant->id,
            'allocation_id' => $allocation->id,
            'quantity_expected' => 12,
            'quantity_received' => 12,
            'receiving_location_id' => $location->id,
        ]);

        // Verify manual_creation_reason was merged into condition_notes
        $batch = InboundBatch::latest('created_at')->first();
        $this->assertNotNull($batch);
        $this->assertStringContainsString('[MANUAL CREATION - Reason:', $batch->condition_notes);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateInboundBatch::class)
            ->fillForm([
                'manual_creation_reason' => null,
                'source_type' => null,
                'product_reference_id' => null,
                'allocation_id' => null,
                'audit_confirmation' => false,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'manual_creation_reason' => 'required',
                'source_type' => 'required',
                'product_reference_id' => 'required',
                'allocation_id' => 'required',
                'audit_confirmation' => 'accepted',
            ]);
    }

    public function test_create_validates_manual_reason_min_length(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateInboundBatch::class)
            ->fillForm([
                'manual_creation_reason' => 'Too short',
            ])
            ->call('create')
            ->assertHasFormErrors(['manual_creation_reason' => 'min']);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $batch = InboundBatch::factory()->create();

        Livewire::test(ViewInboundBatch::class, ['record' => $batch->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListInboundBatches::class)
            ->assertSuccessful();
    }

    public function test_viewer_cannot_create_inbound_batch(): void
    {
        $this->actingAsViewer();

        Livewire::test(CreateInboundBatch::class)
            ->assertForbidden();
    }
}
