<?php

namespace Database\Factories\Allocation;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Models\Allocation\CaseEntitlement;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseEntitlement>
 */
class CaseEntitlementFactory extends Factory
{
    protected $model = CaseEntitlement::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'sellable_sku_id' => SellableSku::factory(),
            'status' => CaseEntitlementStatus::Intact,
        ];
    }

    public function broken(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CaseEntitlementStatus::Broken,
            'broken_at' => now(),
            'broken_reason' => 'Customer requested case breaking',
        ]);
    }
}
