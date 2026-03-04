<?php

namespace Tests\Feature\Filament\Allocation;

use App\Filament\Resources\Allocation\CaseEntitlementResource\Pages\ListCaseEntitlements;
use App\Filament\Resources\Allocation\CaseEntitlementResource\Pages\ViewCaseEntitlement;
use App\Models\Allocation\CaseEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class CaseEntitlementResourceTest extends TestCase
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

        Livewire::test(ListCaseEntitlements::class)
            ->assertSuccessful();
    }

    public function test_list_shows_case_entitlements(): void
    {
        $this->actingAsSuperAdmin();

        $caseEntitlements = CaseEntitlement::factory()->count(3)->create();

        Livewire::test(ListCaseEntitlements::class)
            ->assertCanSeeTableRecords($caseEntitlements);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $caseEntitlement = CaseEntitlement::factory()->create();

        Livewire::test(ViewCaseEntitlement::class, ['record' => $caseEntitlement->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListCaseEntitlements::class)
            ->assertSuccessful();
    }
}
