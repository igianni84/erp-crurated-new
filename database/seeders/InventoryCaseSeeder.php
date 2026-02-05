<?php

namespace Database\Seeders;

use App\Enums\Inventory\CaseIntegrityStatus;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Pim\CaseConfiguration;
use Illuminate\Database\Seeder;

/**
 * InventoryCaseSeeder - Creates physical wine cases in inventory
 */
class InventoryCaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get active locations that support serialization
        $locations = Location::where('serialization_authorized', true)
            ->where('status', 'active')
            ->get();

        if ($locations->isEmpty()) {
            $this->command->warn('No serialization-authorized locations found. Run LocationSeeder first.');

            return;
        }

        // Get case configurations
        $caseConfigs = CaseConfiguration::all();

        if ($caseConfigs->isEmpty()) {
            $this->command->warn('No case configurations found. Run CaseConfigurationSeeder first.');

            return;
        }

        // Get allocations with physical inventory
        $allocations = Allocation::whereIn('status', ['active', 'exhausted'])
            ->with(['wineVariant.wineMaster', 'format'])
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No allocations found. Run AllocationSeeder first.');

            return;
        }

        // For each allocation, create some cases
        foreach ($allocations as $allocation) {
            // Calculate how many cases based on total quantity
            // Assume standard 6-bottle or 12-bottle cases
            $bottlesPerCase = fake()->randomElement([6, 12]);
            $caseConfig = $caseConfigs->where('bottle_count', $bottlesPerCase)->first();

            if (! $caseConfig) {
                $caseConfig = $caseConfigs->first();
                $bottlesPerCase = $caseConfig?->bottle_count ?? 6;
            }

            // Calculate number of cases (allocations have bottles, we group into cases)
            $totalBottles = $allocation->total_quantity;
            $numCases = (int) ceil($totalBottles / $bottlesPerCase);

            // Limit for performance
            $numCases = min($numCases, 10);

            for ($i = 0; $i < $numCases; $i++) {
                $location = $locations->random();

                // 80% intact, 15% broken for fulfillment, 5% broken for other reasons
                $integrityRandom = fake()->numberBetween(1, 100);
                if ($integrityRandom <= 80) {
                    $integrityStatus = CaseIntegrityStatus::Intact;
                    $brokenAt = null;
                    $brokenBy = null;
                    $brokenReason = null;
                } else {
                    $integrityStatus = CaseIntegrityStatus::Broken;
                    $brokenAt = fake()->dateTimeBetween('-3 months', '-1 day');
                    $brokenBy = 1; // Admin user
                    $brokenReason = $integrityRandom <= 95
                        ? 'Broken for customer fulfillment order'
                        : 'Case damaged during handling';
                }

                InventoryCase::create([
                    'case_configuration_id' => $caseConfig->id,
                    'allocation_id' => $allocation->id,
                    'inbound_batch_id' => null, // Would be set if we had inbound batches
                    'current_location_id' => $location->id,
                    'is_original' => true, // Original case from producer
                    'is_breakable' => true,
                    'integrity_status' => $integrityStatus,
                    'broken_at' => $brokenAt,
                    'broken_by' => $brokenBy,
                    'broken_reason' => $brokenReason,
                ]);
            }
        }
    }
}
