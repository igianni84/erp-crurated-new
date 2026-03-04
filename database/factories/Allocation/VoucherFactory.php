<?php

namespace Database\Factories\Allocation;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'allocation_id' => Allocation::factory(),
            'wine_variant_id' => WineVariant::factory(),
            'format_id' => Format::factory(),
            'sellable_sku_id' => SellableSku::factory(),
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => false,
            'suspended' => false,
            'sale_reference' => 'SALE-'.fake()->unique()->numerify('######'),
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_state' => VoucherLifecycleState::Locked,
        ]);
    }

    public function redeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_state' => VoucherLifecycleState::Redeemed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_state' => VoucherLifecycleState::Cancelled,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended' => true,
        ]);
    }
}
