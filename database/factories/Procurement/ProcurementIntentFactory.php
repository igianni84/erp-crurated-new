<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\SourcingModel;
use App\Models\Pim\SellableSku;
use App\Models\Procurement\ProcurementIntent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementIntent>
 */
class ProcurementIntentFactory extends Factory
{
    protected $model = ProcurementIntent::class;

    public function definition(): array
    {
        return [
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => SellableSku::factory(),
            'quantity' => fake()->numberBetween(6, 60),
            'trigger_type' => ProcurementTriggerType::Strategic,
            'sourcing_model' => SourcingModel::Purchase,
            'preferred_inbound_location' => 'main_warehouse',
            'status' => ProcurementIntentStatus::Draft,
            'needs_ops_review' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementIntentStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function executed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementIntentStatus::Executed,
            'approved_at' => now()->subDays(7),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementIntentStatus::Closed,
            'approved_at' => now()->subDays(14),
        ]);
    }

    public function voucherDriven(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => ProcurementTriggerType::VoucherDriven,
        ]);
    }
}
