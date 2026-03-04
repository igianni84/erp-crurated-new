<?php

namespace Tests\Feature\Filament\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\CreateInboundBatch;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\ListInboundBatches;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\ViewInboundBatch;
use App\Models\Inventory\InboundBatch;
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
