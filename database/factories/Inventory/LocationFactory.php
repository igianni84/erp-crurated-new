<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Models\Inventory\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city().' Warehouse',
            'location_type' => LocationType::MainWarehouse,
            'country' => fake()->country(),
            'address' => fake()->address(),
            'serialization_authorized' => true,
            'status' => LocationStatus::Active,
        ];
    }

    public function warehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::MainWarehouse,
        ]);
    }

    public function bonded(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::ThirdPartyStorage,
            'name' => fake()->city().' Bonded Warehouse',
        ]);
    }
}
