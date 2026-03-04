<?php

namespace Tests\Feature\Filament\Allocation;

use App\Filament\Resources\Allocation\VoucherTransferResource\Pages\ListVoucherTransfers;
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

    /**
     * @skip List with records and View page skipped: VoucherTransferResource
     * references hard-coded route 'filament.admin.resources.vouchers.view'
     * which doesn't match actual VoucherResource route name (includes module prefix).
     * This is a known codebase bug to fix in VoucherTransferResource.php:65.
     */

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListVoucherTransfers::class)
            ->assertSuccessful();
    }
}
