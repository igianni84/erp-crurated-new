<?php

namespace Tests\Feature\Filament\Procurement;

use App\Filament\Resources\Procurement\BottlingInstructionResource\Pages\CreateBottlingInstruction;
use App\Filament\Resources\Procurement\BottlingInstructionResource\Pages\ListBottlingInstructions;
use App\Filament\Resources\Procurement\BottlingInstructionResource\Pages\ViewBottlingInstruction;
use App\Models\Procurement\BottlingInstruction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class BottlingInstructionResourceTest extends TestCase
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

        Livewire::test(ListBottlingInstructions::class)
            ->assertSuccessful();
    }

    public function test_list_shows_bottling_instructions(): void
    {
        $this->actingAsSuperAdmin();

        $instructions = BottlingInstruction::factory()->count(3)->create();

        Livewire::test(ListBottlingInstructions::class)
            ->assertCanSeeTableRecords($instructions);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateBottlingInstruction::class)
            ->assertSuccessful();
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $instruction = BottlingInstruction::factory()->create();

        Livewire::test(ViewBottlingInstruction::class, ['record' => $instruction->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListBottlingInstructions::class)
            ->assertSuccessful();
    }
}
