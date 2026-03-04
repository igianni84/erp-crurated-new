<?php

namespace Tests\Feature\Filament\Inventory;

use App\Filament\Resources\Inventory\InventoryMovementResource\Pages\ListInventoryMovements;
use App\Filament\Resources\Inventory\InventoryMovementResource\Pages\ViewInventoryMovement;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class InventoryMovementResourceTest extends TestCase
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

        Livewire::test(ListInventoryMovements::class)
            ->assertSuccessful();
    }

    public function test_list_shows_inventory_movements(): void
    {
        $this->actingAsSuperAdmin();

        $inventoryMovements = InventoryMovement::factory()->count(3)->create();

        Livewire::test(ListInventoryMovements::class)
            ->assertCanSeeTableRecords($inventoryMovements);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $inventoryMovement = InventoryMovement::factory()->create();

        Livewire::test(ViewInventoryMovement::class, ['record' => $inventoryMovement->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListInventoryMovements::class)
            ->assertSuccessful();
    }
}
