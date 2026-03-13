<?php

namespace Tests\Feature\Policies;

use App\Models\Fulfillment\ShippingOrderException;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use App\Policies\Fulfillment\ShippingOrderExceptionPolicy;
use App\Policies\Inventory\InventoryCasePolicy;
use App\Policies\Inventory\InventoryMovementPolicy;
use App\Policies\Inventory\SerializedBottlePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadOnlyPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{policy: class-string, model: class-string}>
     */
    public static function policyProvider(): array
    {
        return [
            'InventoryMovement' => ['policy' => InventoryMovementPolicy::class, 'model' => InventoryMovement::class],
            'SerializedBottle' => ['policy' => SerializedBottlePolicy::class, 'model' => SerializedBottle::class],
            'InventoryCase' => ['policy' => InventoryCasePolicy::class, 'model' => InventoryCase::class],
            'ShippingOrderException' => ['policy' => ShippingOrderExceptionPolicy::class, 'model' => ShippingOrderException::class],
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
    public function test_no_one_can_create(string $policy, string $model): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->create($superAdmin));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_no_one_can_update(string $policy, string $model): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->update($superAdmin, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_no_one_can_delete(string $policy, string $model): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->delete($superAdmin, $record));
    }

    /**
     * @param  class-string  $policy
     * @param  class-string  $model
     */
    #[DataProvider('policyProvider')]
    public function test_no_one_can_restore(string $policy, string $model): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $record = $model::factory()->create();
        $policyInstance = new $policy;

        $this->assertFalse($policyInstance->restore($superAdmin, $record));
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
