<?php

namespace Tests\Feature\Filament\Inventory;

use App\Filament\Resources\Inventory\CaseResource\Pages\ListCases;
use App\Filament\Resources\Inventory\CaseResource\Pages\ViewCase;
use App\Models\Inventory\InventoryCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class CaseResourceTest extends TestCase
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

        Livewire::test(ListCases::class)
            ->assertSuccessful();
    }

    public function test_list_shows_cases(): void
    {
        $this->actingAsSuperAdmin();

        $cases = InventoryCase::factory()->count(3)->create();

        Livewire::test(ListCases::class)
            ->assertCanSeeTableRecords($cases);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $case = InventoryCase::factory()->create();

        Livewire::test(ViewCase::class, ['record' => $case->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListCases::class)
            ->assertSuccessful();
    }
}
