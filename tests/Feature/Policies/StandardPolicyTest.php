<?php

namespace Tests\Feature\Policies;

use App\Models\Allocation\CaseEntitlement;
use App\Models\Commercial\Bundle;
use App\Models\Commercial\DiscountRule;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PricingPolicy;
use App\Models\Pim\Appellation;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Country;
use App\Models\Pim\Format;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\Procurement\BottlingInstruction;
use App\Models\Procurement\Inbound;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use App\Policies\Commercial\BundlePolicy;
use App\Policies\Commercial\DiscountRulePolicy;
use App\Policies\Commercial\OfferPolicy;
use App\Policies\Commercial\PriceBookPolicy;
use App\Policies\Commercial\PricingPolicyPolicy;
use App\Policies\Customer\CaseEntitlementPolicy;
use App\Policies\Pim\AppellationPolicy;
use App\Policies\Pim\CaseConfigurationPolicy;
use App\Policies\Pim\CountryPolicy;
use App\Policies\Pim\FormatPolicy;
use App\Policies\Pim\LiquidProductPolicy;
use App\Policies\Pim\ProducerPolicy;
use App\Policies\Pim\RegionPolicy;
use App\Policies\Pim\SellableSkuPolicy;
use App\Policies\Pim\WineMasterPolicy;
use App\Policies\Pim\WineVariantPolicy;
use App\Policies\Procurement\BottlingInstructionPolicy;
use App\Policies\Procurement\InboundPolicy;
use App\Policies\Procurement\ProcurementIntentPolicy;
use App\Policies\Procurement\PurchaseOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StandardPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{policy: class-string, model: class-string}>
     */
    public static function policyProvider(): array
    {
        return [
            'Country' => ['policy' => CountryPolicy::class, 'model' => Country::class],
            'Region' => ['policy' => RegionPolicy::class, 'model' => Region::class],
            'Appellation' => ['policy' => AppellationPolicy::class, 'model' => Appellation::class],
            'Format' => ['policy' => FormatPolicy::class, 'model' => Format::class],
            'CaseConfiguration' => ['policy' => CaseConfigurationPolicy::class, 'model' => CaseConfiguration::class],
            'Producer' => ['policy' => ProducerPolicy::class, 'model' => Producer::class],
            'LiquidProduct' => ['policy' => LiquidProductPolicy::class, 'model' => LiquidProduct::class],
            'WineMaster' => ['policy' => WineMasterPolicy::class, 'model' => WineMaster::class],
            'WineVariant' => ['policy' => WineVariantPolicy::class, 'model' => WineVariant::class],
            'SellableSku' => ['policy' => SellableSkuPolicy::class, 'model' => SellableSku::class],
            'Bundle' => ['policy' => BundlePolicy::class, 'model' => Bundle::class],
            'DiscountRule' => ['policy' => DiscountRulePolicy::class, 'model' => DiscountRule::class],
            'Offer' => ['policy' => OfferPolicy::class, 'model' => Offer::class],
            'PriceBook' => ['policy' => PriceBookPolicy::class, 'model' => PriceBook::class],
            'PricingPolicy' => ['policy' => PricingPolicyPolicy::class, 'model' => PricingPolicy::class],
            'ProcurementIntent' => ['policy' => ProcurementIntentPolicy::class, 'model' => ProcurementIntent::class],
            'PurchaseOrder' => ['policy' => PurchaseOrderPolicy::class, 'model' => PurchaseOrder::class],
            'Inbound' => ['policy' => InboundPolicy::class, 'model' => Inbound::class],
            'BottlingInstruction' => ['policy' => BottlingInstructionPolicy::class, 'model' => BottlingInstruction::class],
            'CaseEntitlement' => ['policy' => CaseEntitlementPolicy::class, 'model' => CaseEntitlement::class],
        ];
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_any_user_can_view_any(string $policy, string $model): void
    {
        $viewer = User::factory()->viewer()->create();
        $policyInstance = new $policy;

        $this->assertTrue($policyInstance->viewAny($viewer));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_any_user_can_view(string $policy, string $model): void
    {
        $viewer = User::factory()->viewer()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertTrue($policyInstance->view($viewer, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_editor_can_create(string $policy, string $model): void
    {
        $editor = User::factory()->editor()->create();
        $policyInstance = new $policy;

        $this->assertTrue($policyInstance->create($editor));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_viewer_cannot_create(string $policy, string $model): void
    {
        $viewer = User::factory()->viewer()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->create($viewer));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_editor_can_update(string $policy, string $model): void
    {
        $editor = User::factory()->editor()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertTrue($policyInstance->update($editor, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_viewer_cannot_update(string $policy, string $model): void
    {
        $viewer = User::factory()->viewer()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->update($viewer, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_admin_can_delete(string $policy, string $model): void
    {
        $admin = User::factory()->admin()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertTrue($policyInstance->delete($admin, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_editor_cannot_delete(string $policy, string $model): void
    {
        $editor = User::factory()->editor()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->delete($editor, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_admin_can_restore(string $policy, string $model): void
    {
        $admin = User::factory()->admin()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertTrue($policyInstance->restore($admin, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_no_one_can_force_delete(string $policy, string $model): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->forceDelete($superAdmin, $record));
    }
}
