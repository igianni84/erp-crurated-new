<?php

namespace Tests\Feature\Filament\Commercial;

use App\Enums\Commercial\BundlePricingLogic;
use App\Enums\Commercial\BundleStatus;
use App\Filament\Resources\BundleResource\Pages\CreateBundle;
use App\Filament\Resources\BundleResource\Pages\EditBundle;
use App\Filament\Resources\BundleResource\Pages\ListBundles;
use App\Filament\Resources\BundleResource\Pages\ViewBundle;
use App\Models\Commercial\Bundle;
use App\Models\Pim\SellableSku;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class BundleResourceTest extends TestCase
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

        Livewire::test(ListBundles::class)
            ->assertSuccessful();
    }

    public function test_list_shows_bundles(): void
    {
        $this->actingAsSuperAdmin();

        $bundles = Bundle::factory()->count(3)->create();

        Livewire::test(ListBundles::class)
            ->assertCanSeeTableRecords($bundles);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = Bundle::factory()->create(['status' => BundleStatus::Draft]);
        $active = Bundle::factory()->active()->create();

        Livewire::test(ListBundles::class)
            ->filterTable('status', BundleStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_filter_by_pricing_logic(): void
    {
        $this->actingAsSuperAdmin();

        $sumComponents = Bundle::factory()->create(['pricing_logic' => BundlePricingLogic::SumComponents]);
        $fixedPrice = Bundle::factory()->fixedPrice()->create();

        Livewire::test(ListBundles::class)
            ->filterTable('pricing_logic', BundlePricingLogic::SumComponents->value)
            ->assertCanSeeTableRecords([$sumComponents])
            ->assertCanNotSeeTableRecords([$fixedPrice]);
    }

    // ── Create Page (Wizard) ─────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateBundle::class)
            ->assertSuccessful();
    }

    public function test_can_create_bundle_via_wizard(): void
    {
        $this->actingAsSuperAdmin();

        $undoRepeaterFake = Repeater::fake();

        $sku = SellableSku::factory()->active()->create();

        Livewire::test(CreateBundle::class)
            ->fillForm([
                'name' => 'Test Premium Bundle',
                'bundle_components' => [
                    ['sellable_sku_id' => $sku->id, 'quantity' => 2],
                ],
                'pricing_logic' => BundlePricingLogic::SumComponents->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $this->assertDatabaseHas('bundles', [
            'name' => 'Test Premium Bundle',
            'pricing_logic' => BundlePricingLogic::SumComponents->value,
            'status' => BundleStatus::Draft->value,
        ]);
    }

    public function test_can_create_bundle_with_fixed_price(): void
    {
        $this->actingAsSuperAdmin();

        $undoRepeaterFake = Repeater::fake();

        $sku = SellableSku::factory()->active()->create();

        Livewire::test(CreateBundle::class)
            ->fillForm([
                'name' => 'Fixed Price Bundle',
                'bundle_components' => [
                    ['sellable_sku_id' => $sku->id, 'quantity' => 1],
                ],
                'pricing_logic' => BundlePricingLogic::FixedPrice->value,
                'fixed_price' => '149.99',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $this->assertDatabaseHas('bundles', [
            'name' => 'Fixed Price Bundle',
            'pricing_logic' => BundlePricingLogic::FixedPrice->value,
            'fixed_price' => '149.99',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateBundle::class)
            ->fillForm([
                'name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $bundle = Bundle::factory()->create(['status' => BundleStatus::Draft]);

        Livewire::test(EditBundle::class, ['record' => $bundle->id])
            ->assertSuccessful();
    }

    public function test_can_update_bundle(): void
    {
        $this->actingAsSuperAdmin();

        $bundle = Bundle::factory()->create(['status' => BundleStatus::Draft]);

        Livewire::test(EditBundle::class, ['record' => $bundle->id])
            ->fillForm([
                'name' => 'Updated Bundle Name',
                'bundle_sku' => 'BDL-UPDATED-001',
                'pricing_logic' => BundlePricingLogic::SumComponents->value,
                'status' => BundleStatus::Draft->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bundles', [
            'id' => $bundle->id,
            'name' => 'Updated Bundle Name',
            'bundle_sku' => 'BDL-UPDATED-001',
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $bundle = Bundle::factory()->create();

        Livewire::test(ViewBundle::class, ['record' => $bundle->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListBundles::class)
            ->assertSuccessful();
    }
}
