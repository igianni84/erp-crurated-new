<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Enums\Inventory\LocationStatus;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Pim\CaseConfiguration;
use App\Models\User;
use App\Services\Inventory\MovementService;
use Illuminate\Database\Seeder;

/**
 * InventoryCaseSeeder - Creates physical wine cases via MovementService lifecycle.
 *
 * All cases are created as Intact (the only valid initial state).
 * Broken cases are transitioned via MovementService::breakCase() which:
 * - Validates is_breakable + Intact preconditions
 * - Updates integrity_status to Broken with audit fields
 * - Creates an InventoryMovement record for the break action
 * - Respects the IRREVERSIBLE invariant (Intact → Broken, never back)
 */
class InventoryCaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $movementService = app(MovementService::class);

        // Get active locations that support serialization
        $locations = Location::where('serialization_authorized', true)
            ->where('status', LocationStatus::Active)
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

        // Get allocations with physical inventory (active only — sold_quantity managed by VoucherService)
        $allocations = Allocation::where('status', AllocationStatus::Active)
            ->with(['wineVariant.wineMaster', 'format'])
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No active allocations found. Run AllocationSeeder first.');

            return;
        }

        $admin = User::first();
        $totalCreated = 0;
        $casesToBreak = [];

        foreach ($allocations as $allocation) {
            // Skip liquid allocations (no physical bottles yet)
            if ($allocation->supply_form->value === 'liquid') {
                continue;
            }

            // Determine appropriate case configuration based on wine type
            $wineName = $allocation->wineVariant->wineMaster->name ?? '';
            $caseConfig = $this->selectCaseConfiguration($wineName, $owcConfigs, $ocConfigs, $noneConfigs, $caseConfigs);

            if (! $caseConfig) {
                /** @var CaseConfiguration $caseConfig */
                $caseConfig = $caseConfigs->first();
            }

            $bottlesPerCase = $caseConfig->bottles_per_case ?? 6;

            // Calculate how many cases based on total quantity
            $remainingBottles = $allocation->total_quantity - $allocation->sold_quantity;
            $physicalBottles = max($remainingBottles, (int) ($allocation->total_quantity * 0.7));

            $numCases = (int) ceil($physicalBottles / $bottlesPerCase);
            $numCases = min($numCases, 25);
            $numCases = max($numCases, 1);

            for ($i = 0; $i < $numCases; $i++) {
                $location = $locations->random();

                // Create ALL cases as Intact — the only valid initial state
                $case = InventoryCase::create([
                    'case_configuration_id' => $caseConfig->id,
                    'allocation_id' => $allocation->id,
                    'inbound_batch_id' => null, // Will be linked by SerializedBottleSeeder
                    'current_location_id' => $location->id,
                    'is_original' => $caseConfig->case_type === 'owc' || fake()->boolean(80),
                    'is_breakable' => $this->determineBreakability($caseConfig, $wineName),
                    'integrity_status' => CaseIntegrityStatus::Intact,
                    'broken_at' => null,
                    'broken_by' => null,
                    'broken_reason' => null,
                ]);

                $totalCreated++;

                // Mark ~25% of breakable cases for breaking via service
                if ($case->is_breakable && fake()->boolean(25)) {
                    $casesToBreak[] = $case;
                }
            }
        }

        // Now break cases via MovementService::breakCase() — proper lifecycle transition
        $brokenCount = 0;
        $breakReasons = [
            'Customer fulfillment order - individual bottles requested',
            'Partial case shipment to customer',
            'Customer requested mixed case assembly',
            'Split for multiple shipping orders',
            'Individual bottle selection for VIP order',
            'Case damaged during handling - bottles intact',
            'Quality inspection - bottles removed for verification',
            'Repackaging for storage optimization',
        ];

        foreach ($casesToBreak as $case) {
            try {
                $reason = fake()->randomElement($breakReasons);
                $movementService->breakCase($case, $reason, $admin);
                $brokenCount++;
            } catch (\Throwable $e) {
                $this->command->warn("Case break failed for case {$case->id}: {$e->getMessage()}");
            }
        }

        $this->command->info("Created {$totalCreated} inventory cases ({$brokenCount} broken via MovementService).");
    }

    /**
     * Determine if a case should be breakable based on config and wine type.
     */
    private function determineBreakability(CaseConfiguration $caseConfig, string $wineName): bool
    {
        $isBreakable = $caseConfig->is_breakable ?? true;

        // Premium wines in OWC are less likely to be breakable
        if ($caseConfig->case_type === 'owc' && $this->isPremiumWine($wineName)) {
            $isBreakable = fake()->boolean(30);
        }

        return $isBreakable;
    }

    /**
     * Select appropriate case configuration based on wine type.
     */
    private function selectCaseConfiguration($wineName, $owcConfigs, $ocConfigs, $noneConfigs, $allConfigs): ?CaseConfiguration
    {
        $premiumWines = [
            'Romanee-Conti', 'La Tache', 'Richebourg', 'Montrachet',
            'Petrus', 'Le Pin', 'Chateau Margaux', 'Chateau Latour',
            'Chateau Lafite', 'Chateau Mouton', 'Chateau Haut-Brion',
            'Barolo Monfortino', 'Masseto', 'Musigny', 'Chambertin',
            'Salon', 'Dom Perignon', 'Cristal', 'Krug',
        ];

        foreach ($premiumWines as $premium) {
            if (str_contains($wineName, $premium)) {
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

        $standardFineWines = [
            'Sassicaia', 'Ornellaia', 'Tignanello', 'Solaia',
            'Brunello', 'Barolo', 'Barbaresco', 'Amarone',
            'Chateau Cheval Blanc', 'Chateau Ausone',
        ];

        foreach ($standardFineWines as $wine) {
            if (str_contains($wineName, $wine)) {
                if (fake()->boolean(60) && $owcConfigs->isNotEmpty()) {
                    return $owcConfigs->random();
                }
                if ($ocConfigs->isNotEmpty()) {
                    return $ocConfigs->random();
                }

                return $allConfigs->first();
            }
        }

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
}
