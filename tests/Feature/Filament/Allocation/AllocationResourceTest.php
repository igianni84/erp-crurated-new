<?php

namespace Tests\Feature\Filament\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Filament\Resources\Allocation\AllocationResource\Pages\CreateAllocation;
use App\Filament\Resources\Allocation\AllocationResource\Pages\EditAllocation;
use App\Filament\Resources\Allocation\AllocationResource\Pages\ListAllocations;
use App\Filament\Resources\Allocation\AllocationResource\Pages\ViewAllocation;
use App\Models\Allocation\Allocation;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class AllocationResourceTest extends TestCase
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

        Livewire::test(ListAllocations::class)
            ->assertSuccessful();
    }

    public function test_list_shows_allocations(): void
    {
        $this->actingAsSuperAdmin();

        $allocations = Allocation::factory()->count(3)->create();

        Livewire::test(ListAllocations::class)
            ->assertCanSeeTableRecords($allocations);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = Allocation::factory()->create(['status' => AllocationStatus::Draft]);
        $active = Allocation::factory()->active()->create();

        Livewire::test(ListAllocations::class)
            ->filterTable('status', [AllocationStatus::Draft->value])
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_filter_by_source_type(): void
    {
        $this->actingAsSuperAdmin();

        $producer = Allocation::factory()->create(['source_type' => AllocationSourceType::ProducerAllocation]);
        $owned = Allocation::factory()->create(['source_type' => AllocationSourceType::OwnedStock]);

        Livewire::test(ListAllocations::class)
            ->filterTable('source_type', [AllocationSourceType::ProducerAllocation->value])
            ->assertCanSeeTableRecords([$producer])
            ->assertCanNotSeeTableRecords([$owned]);
    }

    // ── Create Page (Wizard) ────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateAllocation::class)
            ->assertSuccessful();
    }

    public function test_can_create_allocation_via_wizard(): void
    {
        $this->actingAsSuperAdmin();

        $wineMaster = WineMaster::factory()->create();
        $wineVariant = WineVariant::factory()->create(['wine_master_id' => $wineMaster->id]);
        $format = Format::factory()->standard()->create();

        Livewire::test(CreateAllocation::class)
            ->fillForm([
                'wine_master_id' => $wineMaster->id,
                'wine_variant_id' => $wineVariant->id,
                'format_id' => $format->id,
                'source_type' => AllocationSourceType::ProducerAllocation->value,
                'supply_form' => AllocationSupplyForm::Bottled->value,
                'total_quantity' => 24,
                'serialization_required' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('allocations', [
            'wine_variant_id' => $wineVariant->id,
            'format_id' => $format->id,
            'source_type' => AllocationSourceType::ProducerAllocation->value,
            'total_quantity' => 24,
            'status' => AllocationStatus::Draft->value,
        ]);
    }

    public function test_create_validates_required_wine_variant(): void
    {
        $this->actingAsSuperAdmin();

        $wineMaster = WineMaster::factory()->create();
        $format = Format::factory()->create();

        Livewire::test(CreateAllocation::class)
            ->fillForm([
                'wine_master_id' => $wineMaster->id,
                'wine_variant_id' => null,
                'format_id' => $format->id,
                'source_type' => AllocationSourceType::ProducerAllocation->value,
                'supply_form' => AllocationSupplyForm::Bottled->value,
                'total_quantity' => 24,
            ])
            ->call('create')
            ->assertHasFormErrors(['wine_variant_id']);
    }

    public function test_create_validates_required_format(): void
    {
        $this->actingAsSuperAdmin();

        $wineVariant = WineVariant::factory()->create();

        Livewire::test(CreateAllocation::class)
            ->fillForm([
                'wine_master_id' => $wineVariant->wine_master_id,
                'wine_variant_id' => $wineVariant->id,
                'format_id' => null,
                'source_type' => AllocationSourceType::ProducerAllocation->value,
                'supply_form' => AllocationSupplyForm::Bottled->value,
                'total_quantity' => 24,
            ])
            ->call('create')
            ->assertHasFormErrors(['format_id']);
    }

    public function test_create_validates_total_quantity_min(): void
    {
        $this->actingAsSuperAdmin();

        $wineVariant = WineVariant::factory()->create();
        $format = Format::factory()->create();

        Livewire::test(CreateAllocation::class)
            ->fillForm([
                'wine_master_id' => $wineVariant->wine_master_id,
                'wine_variant_id' => $wineVariant->id,
                'format_id' => $format->id,
                'source_type' => AllocationSourceType::ProducerAllocation->value,
                'supply_form' => AllocationSupplyForm::Bottled->value,
                'total_quantity' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['total_quantity']);
    }

    public function test_allocation_auto_creates_constraint(): void
    {
        $this->actingAsSuperAdmin();

        $wineVariant = WineVariant::factory()->create();
        $format = Format::factory()->create();

        Livewire::test(CreateAllocation::class)
            ->fillForm([
                'wine_master_id' => $wineVariant->wine_master_id,
                'wine_variant_id' => $wineVariant->id,
                'format_id' => $format->id,
                'source_type' => AllocationSourceType::ProducerAllocation->value,
                'supply_form' => AllocationSupplyForm::Bottled->value,
                'total_quantity' => 12,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $allocation = Allocation::query()->latest('created_at')->first();
        $this->assertNotNull($allocation);
        $this->assertNotNull($allocation->constraint, 'AllocationConstraint should be auto-created');
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $allocation = Allocation::factory()->create();

        Livewire::test(EditAllocation::class, ['record' => $allocation->id])
            ->assertSuccessful();
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $allocation = Allocation::factory()->create();

        Livewire::test(ViewAllocation::class, ['record' => $allocation->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_active_allocation(): void
    {
        $this->actingAsSuperAdmin();

        $allocation = Allocation::factory()->active()->create();

        Livewire::test(ViewAllocation::class, ['record' => $allocation->id])
            ->assertSuccessful();
    }
}
