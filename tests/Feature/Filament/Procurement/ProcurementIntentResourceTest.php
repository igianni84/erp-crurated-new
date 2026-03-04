<?php

namespace Tests\Feature\Filament\Procurement;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\SourcingModel;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages\CreateProcurementIntent;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages\ListProcurementIntents;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages\ViewProcurementIntent;
use App\Models\Procurement\ProcurementIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ProcurementIntentResourceTest extends TestCase
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

        Livewire::test(ListProcurementIntents::class)
            ->assertSuccessful();
    }

    public function test_list_shows_procurement_intents(): void
    {
        $this->actingAsSuperAdmin();

        $intents = ProcurementIntent::factory()->count(3)->create();

        Livewire::test(ListProcurementIntents::class)
            ->assertCanSeeTableRecords($intents);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = ProcurementIntent::factory()->create();
        $approved = ProcurementIntent::factory()->approved()->create();

        Livewire::test(ListProcurementIntents::class)
            ->filterTable('status', [ProcurementIntentStatus::Draft->value])
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$approved]);
    }

    public function test_list_can_filter_by_trigger_type(): void
    {
        $this->actingAsSuperAdmin();

        $strategic = ProcurementIntent::factory()->create([
            'trigger_type' => ProcurementTriggerType::Strategic,
        ]);
        $voucherDriven = ProcurementIntent::factory()->voucherDriven()->create();

        Livewire::test(ListProcurementIntents::class)
            ->filterTable('trigger_type', [ProcurementTriggerType::Strategic->value])
            ->assertCanSeeTableRecords([$strategic])
            ->assertCanNotSeeTableRecords([$voucherDriven]);
    }

    public function test_list_can_filter_by_sourcing_model(): void
    {
        $this->actingAsSuperAdmin();

        $purchase = ProcurementIntent::factory()->create([
            'sourcing_model' => SourcingModel::Purchase,
        ]);
        $consignment = ProcurementIntent::factory()->create([
            'sourcing_model' => SourcingModel::PassiveConsignment,
        ]);

        Livewire::test(ListProcurementIntents::class)
            ->filterTable('sourcing_model', [SourcingModel::Purchase->value])
            ->assertCanSeeTableRecords([$purchase])
            ->assertCanNotSeeTableRecords([$consignment]);
    }

    // ── Create Page (Wizard) ────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateProcurementIntent::class)
            ->assertSuccessful();
    }

    public function test_can_create_procurement_intent_via_wizard(): void
    {
        $this->actingAsSuperAdmin();

        $pimStack = $this->createPimStack();

        Livewire::test(CreateProcurementIntent::class)
            ->fillForm([
                'product_type' => 'bottle_sku',
                'wine_master_id' => $pimStack['wine_master']->id,
                'wine_variant_id' => $pimStack['wine_variant']->id,
                'format_id' => $pimStack['format']->id,
                'trigger_type' => ProcurementTriggerType::Strategic->value,
                'sourcing_model' => SourcingModel::Purchase->value,
                'quantity' => 24,
                'preferred_inbound_location' => 'main_warehouse',
                'rationale' => 'Test procurement',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('procurement_intents', [
            'quantity' => 24,
            'trigger_type' => ProcurementTriggerType::Strategic->value,
            'sourcing_model' => SourcingModel::Purchase->value,
            'status' => ProcurementIntentStatus::Draft->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $intent = ProcurementIntent::factory()->create();

        Livewire::test(ViewProcurementIntent::class, ['record' => $intent->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_approved_intent(): void
    {
        $this->actingAsSuperAdmin();

        $intent = ProcurementIntent::factory()->approved()->create();

        Livewire::test(ViewProcurementIntent::class, ['record' => $intent->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListProcurementIntents::class)
            ->assertSuccessful();
    }

    public function test_viewer_can_view_procurement_intent(): void
    {
        $this->actingAsViewer();

        $intent = ProcurementIntent::factory()->create();

        Livewire::test(ViewProcurementIntent::class, ['record' => $intent->id])
            ->assertSuccessful();
    }
}
