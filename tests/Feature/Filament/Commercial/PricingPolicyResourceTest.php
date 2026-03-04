<?php

namespace Tests\Feature\Filament\Commercial;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\PricingPolicyInputSource;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Filament\Resources\PricingPolicyResource\Pages\EditPricingPolicy;
use App\Filament\Resources\PricingPolicyResource\Pages\ListPricingPolicies;
use App\Filament\Resources\PricingPolicyResource\Pages\ViewPricingPolicy;
use App\Models\Commercial\PricingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class PricingPolicyResourceTest extends TestCase
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

        Livewire::test(ListPricingPolicies::class)
            ->assertSuccessful();
    }

    public function test_list_shows_pricing_policies(): void
    {
        $this->actingAsSuperAdmin();

        $policies = PricingPolicy::factory()->count(3)->create();

        Livewire::test(ListPricingPolicies::class)
            ->assertCanSeeTableRecords($policies);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = PricingPolicy::factory()->create(['status' => PricingPolicyStatus::Draft]);
        $active = PricingPolicy::factory()->active()->create();

        Livewire::test(ListPricingPolicies::class)
            ->filterTable('status', PricingPolicyStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_filter_by_policy_type(): void
    {
        $this->actingAsSuperAdmin();

        $costPlus = PricingPolicy::factory()->create(['policy_type' => PricingPolicyType::CostPlusMargin]);
        $rounding = PricingPolicy::factory()->create(['policy_type' => PricingPolicyType::Rounding]);

        Livewire::test(ListPricingPolicies::class)
            ->filterTable('policy_type', PricingPolicyType::CostPlusMargin->value)
            ->assertCanSeeTableRecords([$costPlus])
            ->assertCanNotSeeTableRecords([$rounding]);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $policy = PricingPolicy::factory()->create(['status' => PricingPolicyStatus::Draft]);

        Livewire::test(EditPricingPolicy::class, ['record' => $policy->id])
            ->assertSuccessful();
    }

    public function test_can_update_pricing_policy(): void
    {
        $this->actingAsSuperAdmin();

        $policy = PricingPolicy::factory()->create(['status' => PricingPolicyStatus::Draft]);

        Livewire::test(EditPricingPolicy::class, ['record' => $policy->id])
            ->fillForm([
                'name' => 'Updated Policy Name',
                'policy_type' => PricingPolicyType::FixedAdjustment->value,
                'input_source' => PricingPolicyInputSource::Emp->value,
                'execution_cadence' => ExecutionCadence::Scheduled->value,
                'status' => PricingPolicyStatus::Draft->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pricing_policies', [
            'id' => $policy->id,
            'name' => 'Updated Policy Name',
            'policy_type' => PricingPolicyType::FixedAdjustment->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $policy = PricingPolicy::factory()->create();

        Livewire::test(ViewPricingPolicy::class, ['record' => $policy->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListPricingPolicies::class)
            ->assertSuccessful();
    }
}
