<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Pim\CaseConfiguration;
use Illuminate\Database\Seeder;

/**
 * InventoryCaseSeeder - Creates comprehensive physical wine cases in inventory
 *
 * Cases represent physical containers in the warehouse:
 * - OWC (Original Wooden Case): Premium cases from producer
 * - OC (Original Cardboard): Standard cardboard cases
 * - None: Loose bottles, no case
 *
 * Case integrity:
 * - Intact: All bottles present, case sealed
 * - Broken: Case opened, some bottles may have been removed
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

        // Separate case configs by type
        $owcConfigs = $caseConfigs->where('case_type', 'owc');
        $ocConfigs = $caseConfigs->where('case_type', 'oc');
        $noneConfigs = $caseConfigs->where('case_type', 'none');

        // Get allocations with physical inventory (active or exhausted)
        $allocations = Allocation::whereIn('status', [AllocationStatus::Active, AllocationStatus::Exhausted])
            ->with(['wineVariant.wineMaster', 'format'])
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No allocations found. Run AllocationSeeder first.');

            return;
        }

        $totalCreated = 0;

        foreach ($allocations as $allocation) {
            // Skip liquid allocations (no physical bottles yet)
            if ($allocation->supply_form->value === 'liquid') {
                continue;
            }

            // Determine appropriate case configuration based on wine type
            $wineName = $allocation->wineVariant?->wineMaster?->name ?? '';
            $caseConfig = $this->selectCaseConfiguration($wineName, $owcConfigs, $ocConfigs, $noneConfigs, $caseConfigs);

            if (! $caseConfig) {
                // Fallback to any available config
                $caseConfig = $caseConfigs->first();
            }

            if (! $caseConfig) {
                continue;
            }

            $bottlesPerCase = $caseConfig->bottles_per_case ?? 6;

            // Calculate how many cases based on available quantity (total - sold = remaining)
            $remainingBottles = $allocation->total_quantity - $allocation->sold_quantity;
            $physicalBottles = max($remainingBottles, (int) ($allocation->total_quantity * 0.7)); // At least 70% of total for inventory

            $numCases = (int) ceil($physicalBottles / $bottlesPerCase);

            // Limit for performance but ensure reasonable coverage
            $numCases = min($numCases, 25);
            $numCases = max($numCases, 1);

            for ($i = 0; $i < $numCases; $i++) {
                $location = $locations->random();

                // Determine integrity status
                // 75% Intact, 20% Broken for fulfillment, 5% Broken for other reasons
                $integrityRandom = fake()->numberBetween(1, 100);
                if ($integrityRandom <= 75) {
                    $integrityStatus = CaseIntegrityStatus::Intact;
                    $brokenAt = null;
                    $brokenBy = null;
                    $brokenReason = null;
                } else {
                    $integrityStatus = CaseIntegrityStatus::Broken;
                    $brokenAt = fake()->dateTimeBetween('-3 months', '-1 day');
                    $brokenBy = 1; // Admin user

                    if ($integrityRandom <= 95) {
                        // Broken for fulfillment (most common)
                        $brokenReason = fake()->randomElement([
                            'Broken for customer fulfillment order',
                            'Partial case shipment to customer',
                            'Customer requested mixed case',
                            'Split for multiple orders',
                            'Individual bottle selection for VIP order',
                        ]);
                    } else {
                        // Broken for other reasons
                        $brokenReason = fake()->randomElement([
                            'Case damaged during handling',
                            'Quality inspection - bottles removed',
                            'Label verification required',
                            'Repackaging for storage optimization',
                            'Insurance assessment',
                        ]);
                    }
                }

                // Determine if original case from producer
                $isOriginal = $caseConfig->case_type === 'owc' || fake()->boolean(80);

                // Determine if case is breakable
                $isBreakable = $caseConfig->is_breakable ?? true;

                // Premium wines in OWC are less likely to be breakable
                if ($caseConfig->case_type === 'owc' && $this->isPremiumWine($wineName)) {
                    $isBreakable = fake()->boolean(30); // Only 30% breakable for premium
                }

                InventoryCase::create([
                    'case_configuration_id' => $caseConfig->id,
                    'allocation_id' => $allocation->id,
                    'inbound_batch_id' => null, // Will be linked by SerializedBottleSeeder
                    'current_location_id' => $location->id,
                    'is_original' => $isOriginal,
                    'is_breakable' => $isBreakable,
                    'integrity_status' => $integrityStatus,
                    'broken_at' => $brokenAt,
                    'broken_by' => $brokenBy,
                    'broken_reason' => $brokenReason,
                ]);

                $totalCreated++;
            }
        }

        // Create some cases in different locations for variety
        $this->createDistributedCases($allocations, $caseConfigs, $locations, $totalCreated);

        $this->command->info("Created {$totalCreated} inventory cases.");
    }

    /**
     * Select appropriate case configuration based on wine type.
     */
    private function selectCaseConfiguration($wineName, $owcConfigs, $ocConfigs, $noneConfigs, $allConfigs): ?CaseConfiguration
    {
        // Premium wines get OWC
        $premiumWines = [
            'Romanee-Conti', 'La Tache', 'Richebourg', 'Montrachet',
            'Petrus', 'Le Pin', 'Chateau Margaux', 'Chateau Latour',
            'Chateau Lafite', 'Chateau Mouton', 'Chateau Haut-Brion',
            'Barolo Monfortino', 'Masseto', 'Musigny', 'Chambertin',
            'Salon', 'Dom Perignon', 'Cristal', 'Krug',
        ];

        foreach ($premiumWines as $premium) {
            if (str_contains($wineName, $premium)) {
                // Premium wines: 80% OWC 6-bottle, 20% OWC 12-bottle or 3-bottle
                if ($owcConfigs->isNotEmpty()) {
                    if (fake()->boolean(80)) {
                        return $owcConfigs->where('bottles_per_case', 6)->first()
                            ?? $owcConfigs->first();
                    }

                    return $owcConfigs->random();
                }

                return $allConfigs->first();
            }
        }

        // Standard fine wines get mix of OWC and OC
        $standardFineWines = [
            'Sassicaia', 'Ornellaia', 'Tignanello', 'Solaia',
            'Brunello', 'Barolo', 'Barbaresco', 'Amarone',
            'Chateau Cheval Blanc', 'Chateau Ausone',
        ];

        foreach ($standardFineWines as $wine) {
            if (str_contains($wineName, $wine)) {
                // 60% OWC, 40% OC
                if (fake()->boolean(60) && $owcConfigs->isNotEmpty()) {
                    return $owcConfigs->random();
                }
                if ($ocConfigs->isNotEmpty()) {
                    return $ocConfigs->random();
                }

                return $allConfigs->first();
            }
        }

        // Entry-level wines get OC or none
        if (fake()->boolean(70) && $ocConfigs->isNotEmpty()) {
            return $ocConfigs->random();
        }
        if ($noneConfigs->isNotEmpty()) {
            return $noneConfigs->random();
        }

        return $allConfigs->first();
    }

    /**
     * Check if wine is premium.
     */
    private function isPremiumWine(string $wineName): bool
    {
        $premiumKeywords = [
            'Romanee-Conti', 'La Tache', 'Richebourg', 'Montrachet',
            'Petrus', 'Le Pin', 'Monfortino', 'Masseto',
            'Margaux', 'Latour', 'Lafite', 'Mouton', 'Haut-Brion',
        ];

        foreach ($premiumKeywords as $keyword) {
            if (str_contains($wineName, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create additional cases distributed across locations.
     */
    private function createDistributedCases($allocations, $caseConfigs, $locations, &$totalCreated): void
    {
        // Select a subset of allocations for additional distribution
        $selectedAllocations = $allocations
            ->where('status', AllocationStatus::Active)
            ->take(10);

        foreach ($selectedAllocations as $allocation) {
            // Create 1-3 additional cases in different locations
            $additionalCount = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $additionalCount; $i++) {
                $location = $locations->random();
                $caseConfig = $caseConfigs->random();

                InventoryCase::create([
                    'case_configuration_id' => $caseConfig->id,
                    'allocation_id' => $allocation->id,
                    'inbound_batch_id' => null,
                    'current_location_id' => $location->id,
                    'is_original' => fake()->boolean(70),
                    'is_breakable' => fake()->boolean(80),
                    'integrity_status' => fake()->boolean(85)
                        ? CaseIntegrityStatus::Intact
                        : CaseIntegrityStatus::Broken,
                    'broken_at' => null,
                    'broken_by' => null,
                    'broken_reason' => null,
                ]);

                $totalCreated++;
            }
        }
    }
}
