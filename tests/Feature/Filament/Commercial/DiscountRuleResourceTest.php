<?php

namespace Tests\Feature\Filament\Commercial;

use App\Enums\Commercial\DiscountRuleStatus;
use App\Enums\Commercial\DiscountRuleType;
use App\Filament\Resources\DiscountRuleResource\Pages\CreateDiscountRule;
use App\Filament\Resources\DiscountRuleResource\Pages\EditDiscountRule;
use App\Filament\Resources\DiscountRuleResource\Pages\ListDiscountRules;
use App\Filament\Resources\DiscountRuleResource\Pages\ViewDiscountRule;
use App\Models\Commercial\DiscountRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class DiscountRuleResourceTest extends TestCase
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

        Livewire::test(ListDiscountRules::class)
            ->assertSuccessful();
    }

    public function test_list_shows_discount_rules(): void
    {
        $this->actingAsSuperAdmin();

        $rules = DiscountRule::factory()->count(3)->create();

        Livewire::test(ListDiscountRules::class)
            ->assertCanSeeTableRecords($rules);
    }

    public function test_list_can_filter_by_rule_type(): void
    {
        $this->actingAsSuperAdmin();

        $percentage = DiscountRule::factory()->create(['rule_type' => DiscountRuleType::Percentage]);
        $fixedAmount = DiscountRule::factory()->fixedAmount()->create();

        Livewire::test(ListDiscountRules::class)
            ->filterTable('rule_type', DiscountRuleType::Percentage->value)
            ->assertCanSeeTableRecords([$percentage])
            ->assertCanNotSeeTableRecords([$fixedAmount]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $active = DiscountRule::factory()->create(['status' => DiscountRuleStatus::Active]);
        $inactive = DiscountRule::factory()->inactive()->create();

        Livewire::test(ListDiscountRules::class)
            ->filterTable('status', DiscountRuleStatus::Active->value)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateDiscountRule::class)
            ->assertSuccessful();
    }

    public function test_can_create_discount_rule(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateDiscountRule::class)
            ->fillForm([
                'name' => 'Test Discount Rule',
                'rule_type' => DiscountRuleType::Percentage->value,
                'status' => DiscountRuleStatus::Active->value,
                'logic_definition.value' => '15',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('discount_rules', [
            'name' => 'Test Discount Rule',
            'rule_type' => DiscountRuleType::Percentage->value,
            'status' => DiscountRuleStatus::Active->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateDiscountRule::class)
            ->fillForm([
                'name' => null,
                'rule_type' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'rule_type' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $rule = DiscountRule::factory()->create();

        Livewire::test(EditDiscountRule::class, ['record' => $rule->id])
            ->assertSuccessful();
    }

    public function test_can_update_discount_rule(): void
    {
        $this->actingAsSuperAdmin();

        $rule = DiscountRule::factory()->create(['rule_type' => DiscountRuleType::Percentage, 'status' => DiscountRuleStatus::Active]);

        Livewire::test(EditDiscountRule::class, ['record' => $rule->id])
            ->fillForm([
                'name' => 'Updated Discount Rule',
                'rule_type' => DiscountRuleType::Percentage->value,
                'status' => DiscountRuleStatus::Inactive->value,
                'logic_definition.value' => '20',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('discount_rules', [
            'id' => $rule->id,
            'name' => 'Updated Discount Rule',
            'status' => DiscountRuleStatus::Inactive->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $rule = DiscountRule::factory()->create();

        Livewire::test(ViewDiscountRule::class, ['record' => $rule->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListDiscountRules::class)
            ->assertSuccessful();
    }
}
