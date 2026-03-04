<?php

namespace Tests\Feature\Filament\Allocation;

use App\Filament\Resources\Allocation\VoucherTransferResource\Pages\ListVoucherTransfers;
use App\Filament\Resources\Allocation\VoucherTransferResource\Pages\ViewVoucherTransfer;
use App\Models\Allocation\VoucherTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class VoucherTransferResourceTest extends TestCase
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

        Livewire::test(ListVoucherTransfers::class)
            ->assertSuccessful();
    }

    public function test_list_shows_voucher_transfers(): void
    {
        $this->actingAsSuperAdmin();

        $transfers = VoucherTransfer::factory()->count(3)->create();

        Livewire::test(ListVoucherTransfers::class)
            ->assertCanSeeTableRecords($transfers);
    }

    // ── View Page ────────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $transfer = VoucherTransfer::factory()->create();

        Livewire::test(ViewVoucherTransfer::class, ['record' => $transfer->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListVoucherTransfers::class)
            ->assertSuccessful();
    }
}
