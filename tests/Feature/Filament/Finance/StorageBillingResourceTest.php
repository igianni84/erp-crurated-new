<?php

namespace Tests\Feature\Filament\Finance;

use App\Filament\Resources\Finance\StorageBillingResource\Pages\ListStorageBilling;
use App\Filament\Resources\Finance\StorageBillingResource\Pages\ViewStorageBilling;
use App\Models\Finance\StorageBillingPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class StorageBillingResourceTest extends TestCase
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

        Livewire::test(ListStorageBilling::class)
            ->assertSuccessful();
    }

    public function test_list_shows_storage_billing_periods(): void
    {
        $this->actingAsSuperAdmin();

        $storageBillingPeriods = StorageBillingPeriod::factory()->count(3)->create();

        Livewire::test(ListStorageBilling::class)
            ->assertCanSeeTableRecords($storageBillingPeriods);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $storageBillingPeriod = StorageBillingPeriod::factory()->create();

        Livewire::test(ViewStorageBilling::class, ['record' => $storageBillingPeriod->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListStorageBilling::class)
            ->assertSuccessful();
    }
}
