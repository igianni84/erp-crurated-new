<?php

namespace Tests\Feature\Filament\Allocation;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\VoucherResource\Pages\ListVouchers;
use App\Filament\Resources\Allocation\VoucherResource\Pages\ViewVoucher;
use App\Models\Allocation\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class VoucherResourceTest extends TestCase
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

        Livewire::test(ListVouchers::class)
            ->assertSuccessful();
    }

    public function test_list_shows_vouchers(): void
    {
        $this->actingAsSuperAdmin();

        $vouchers = Voucher::factory()->count(3)->create();

        Livewire::test(ListVouchers::class)
            ->assertCanSeeTableRecords($vouchers);
    }

    public function test_list_can_filter_by_lifecycle_state(): void
    {
        $this->actingAsSuperAdmin();

        $issued = Voucher::factory()->create();
        $locked = Voucher::factory()->locked()->create();

        Livewire::test(ListVouchers::class)
            ->filterTable('lifecycle_state', [VoucherLifecycleState::Issued->value])
            ->assertCanSeeTableRecords([$issued])
            ->assertCanNotSeeTableRecords([$locked]);
    }

    public function test_list_can_filter_suspended_vouchers(): void
    {
        $this->actingAsSuperAdmin();

        $normal = Voucher::factory()->create(['suspended' => false]);
        $suspended = Voucher::factory()->suspended()->create();

        Livewire::test(ListVouchers::class)
            ->filterTable('suspended', true)
            ->assertCanSeeTableRecords([$suspended])
            ->assertCanNotSeeTableRecords([$normal]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $voucher = Voucher::factory()->create();

        Livewire::test(ViewVoucher::class, ['record' => $voucher->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_locked_voucher(): void
    {
        $this->actingAsSuperAdmin();

        $voucher = Voucher::factory()->locked()->create();

        Livewire::test(ViewVoucher::class, ['record' => $voucher->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListVouchers::class)
            ->assertSuccessful();
    }
}
