<?php

namespace Tests\Feature\Filament\Inventory;

use App\Filament\Resources\Inventory\SerializedBottleResource\Pages\ListSerializedBottles;
use App\Filament\Resources\Inventory\SerializedBottleResource\Pages\ViewSerializedBottle;
use App\Models\Inventory\SerializedBottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class SerializedBottleResourceTest extends TestCase
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

        Livewire::test(ListSerializedBottles::class)
            ->assertSuccessful();
    }

    public function test_list_shows_serialized_bottles(): void
    {
        $this->actingAsSuperAdmin();

        $serializedBottles = SerializedBottle::factory()->count(3)->create();

        Livewire::test(ListSerializedBottles::class)
            ->assertCanSeeTableRecords($serializedBottles);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $serializedBottle = SerializedBottle::factory()->create();

        Livewire::test(ViewSerializedBottle::class, ['record' => $serializedBottle->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListSerializedBottles::class)
            ->assertSuccessful();
    }
}
