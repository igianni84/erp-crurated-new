<?php

namespace Tests\Feature\Filament\Finance;

use App\Filament\Resources\Finance\RefundResource\Pages\ListRefunds;
use App\Filament\Resources\Finance\RefundResource\Pages\ViewRefund;
use App\Models\Finance\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class RefundResourceTest extends TestCase
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

        Livewire::test(ListRefunds::class)
            ->assertSuccessful();
    }

    public function test_list_shows_refunds(): void
    {
        $this->actingAsSuperAdmin();

        $refunds = Refund::factory()->count(3)->create();

        Livewire::test(ListRefunds::class)
            ->assertCanSeeTableRecords($refunds);
    }

    // ── View Page ────────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $refund = Refund::factory()->create();

        Livewire::test(ViewRefund::class, ['record' => $refund->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListRefunds::class)
            ->assertSuccessful();
    }
}
