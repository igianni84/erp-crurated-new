<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\XeroSyncStatus;
use App\Enums\Finance\XeroSyncType;
use App\Models\Finance\Invoice;
use App\Models\Finance\XeroSyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<XeroSyncLog>
 */
class XeroSyncLogFactory extends Factory
{
    protected $model = XeroSyncLog::class;

    public function definition(): array
    {
        return [
            'sync_type' => XeroSyncType::Invoice,
            'syncable_type' => (new Invoice)->getMorphClass(),
            'syncable_id' => Str::uuid()->toString(),
            'status' => XeroSyncStatus::Pending,
            'retry_count' => 0,
        ];
    }

    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XeroSyncStatus::Synced,
            'xero_id' => Str::uuid()->toString(),
            'synced_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => XeroSyncStatus::Failed,
            'error_message' => fake()->sentence(),
            'retry_count' => fake()->numberBetween(1, 3),
        ]);
    }
}
