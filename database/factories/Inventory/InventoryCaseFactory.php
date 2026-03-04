<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\CaseIntegrityStatus;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Pim\CaseConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCase>
 */
class InventoryCaseFactory extends Factory
{
    protected $model = InventoryCase::class;

    public function definition(): array
    {
        return [
            'case_configuration_id' => CaseConfiguration::factory(),
            'allocation_id' => Allocation::factory(),
            'current_location_id' => Location::factory(),
            'is_original' => true,
            'is_breakable' => true,
            'integrity_status' => CaseIntegrityStatus::Intact,
        ];
    }

    public function broken(): static
    {
        return $this->state(fn (array $attributes) => [
            'integrity_status' => CaseIntegrityStatus::Broken,
            'broken_at' => now(),
            'broken_reason' => 'Customer requested case breaking',
        ]);
    }
}
