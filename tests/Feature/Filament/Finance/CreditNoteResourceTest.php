<?php

namespace Tests\Feature\Filament\Finance;

use App\Filament\Resources\Finance\CreditNoteResource\Pages\ListCreditNotes;
use App\Filament\Resources\Finance\CreditNoteResource\Pages\ViewCreditNote;
use App\Models\Finance\CreditNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class CreditNoteResourceTest extends TestCase
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

        Livewire::test(ListCreditNotes::class)
            ->assertSuccessful();
    }

    public function test_list_shows_credit_notes(): void
    {
        $this->actingAsSuperAdmin();

        $creditNotes = CreditNote::factory()->count(3)->create();

        Livewire::test(ListCreditNotes::class)
            ->assertCanSeeTableRecords($creditNotes);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $creditNote = CreditNote::factory()->create();

        Livewire::test(ViewCreditNote::class, ['record' => $creditNote->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListCreditNotes::class)
            ->assertSuccessful();
    }
}
