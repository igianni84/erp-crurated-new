<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * SerializedBottleSeeder - Creates serialized bottles with provenance
 *
 * Serialized bottles are first-class objects in the inventory system
 * with unique serial numbers and permanent allocation lineage.
 *
 * Bottle states:
 * - Stored: In warehouse, available for sale/shipment
 * - ReservedForPicking: Reserved for upcoming shipment
 * - Shipped: Left the warehouse
 * - Consumed: Opened (tasting, event)
 * - Destroyed: Damaged beyond use
 * - Missing: Unaccounted for
 * - MisSerialized: Serialization error
 */
class SerializedBottleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get serialization-authorized locations
        $locations = Location::where('serialization_authorized', true)
            ->where('status', 'active')
            ->get();

        if ($locations->isEmpty()) {
            $this->command->warn('No serialization-authorized locations found. Run LocationSeeder first.');

            return;
        }

        // Get allocations that require serialization (include active and exhausted)
        $allocations = Allocation::where('serialization_required', true)
            ->whereIn('status', [AllocationStatus::Active, AllocationStatus::Exhausted])
            ->with(['wineVariant.wineMaster', 'format'])
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No allocations requiring serialization found. Run AllocationSeeder first.');

            return;
        }

        // Get inventory cases
        $cases = InventoryCase::with('allocation')->get();

        // Get admin user
        $admin = User::first();

        $totalCreated = 0;

        // Create inbound batches and serialized bottles
        foreach ($allocations as $allocation) {
            // Skip liquid allocations
            if ($allocation->supply_form->value === 'liquid') {
                continue;
            }

            // Create inbound batches for this allocation (1-3 batches per allocation)
            $batchCount = fake()->numberBetween(1, 3);

            for ($b = 0; $b < $batchCount; $b++) {
                $bottlesCreated = $this->createInboundBatchWithBottles(
                    $allocation,
                    $locations,
                    $cases,
                    $admin
                );
                $totalCreated += $bottlesCreated;
            }
        }

        // Create some bottles without inbound batches (legacy data)
        $legacyCreated = $this->createLegacyBottles($allocations, $locations, $cases, $admin);
        $totalCreated += $legacyCreated;

        $this->command->info("Created {$totalCreated} serialized bottles.");
    }

    /**
     * Create an inbound batch with serialized bottles.
     */
    private function createInboundBatchWithBottles(
        Allocation $allocation,
        $locations,
        $cases,
        $admin
    ): int {
        $location = $locations->random();

        // Determine batch quantity (6-24 bottles typically)
        $batchQuantity = fake()->randomElement([6, 6, 12, 12, 18, 24]);

        // Determine batch status: 15% pending, 25% partially serialized, 60% fully serialized
        $statusRandom = fake()->numberBetween(1, 100);
        $batchStatus = match (true) {
            $statusRandom <= 15 => InboundBatchStatus::PendingSerialization,
            $statusRandom <= 40 => InboundBatchStatus::PartiallySerialized,
            default => InboundBatchStatus::FullySerialized,
        };

        // Calculate serialized count based on status
        $serializedCount = match ($batchStatus) {
            InboundBatchStatus::PendingSerialization => 0,
            InboundBatchStatus::PartiallySerialized => fake()->numberBetween(1, $batchQuantity - 1),
            InboundBatchStatus::FullySerialized, InboundBatchStatus::Discrepancy => $batchQuantity,
        };

        // Determine ownership type based on allocation source type
        $ownershipType = match ($allocation->source_type->value) {
            'producer_allocation', 'owned_stock' => OwnershipType::CururatedOwned,
            'passive_consignment' => OwnershipType::InCustody,
            'third_party_custody' => OwnershipType::ThirdPartyOwned,
            default => OwnershipType::CururatedOwned,
        };

        // Create inbound batch with correct model fields
        $batch = InboundBatch::create([
            'source_type' => fake()->randomElement(['procurement', 'transfer', 'manual_receipt']),
            'product_reference_type' => 'sellable_skus',
            'product_reference_id' => $allocation->sellable_sku_id ?? $allocation->id,
            'allocation_id' => $allocation->id,
            'procurement_intent_id' => null,
            'quantity_expected' => $batchQuantity,
            'quantity_received' => $batchQuantity,
            'packaging_type' => fake()->randomElement(['owc', 'oc', 'loose']),
            'receiving_location_id' => $location->id,
            'ownership_type' => $ownershipType,
            'received_date' => now()->subDays(fake()->numberBetween(7, 180)),
            'condition_notes' => fake()->boolean(25)
                ? fake()->randomElement([
                    'Excellent condition, labels pristine',
                    'Good condition, minor label wear on 2 bottles',
                    'Priority batch from producer allocation',
                    'Temperature logged throughout transport',
                    'Some capsule oxidation noted',
                ])
                : null,
            'serialization_status' => $batchStatus,
            'wms_reference_id' => fake()->boolean(40) ? 'WMS-'.fake()->regexify('[A-Z0-9]{10}') : null,
        ]);

        // Create serialized bottles for this batch
        return $this->createSerializedBottles(
            $batch,
            $serializedCount,
            $allocation,
            $location,
            $cases,
            $admin,
            $ownershipType
        );
    }

    /**
     * Create serialized bottles for an inbound batch.
     */
    private function createSerializedBottles(
        InboundBatch $batch,
        int $count,
        Allocation $allocation,
        Location $location,
        $cases,
        $admin,
        OwnershipType $ownershipType
    ): int {
        // Find cases for this allocation
        $allocationCases = $cases->where('allocation_id', $allocation->id);

        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            // Generate unique serial number
            $serialNumber = $this->generateSerialNumber($allocation->wineVariant, $allocation->format);

            // Determine bottle state with realistic distribution
            // 60% stored, 15% reserved for picking, 15% shipped, 5% consumed, 3% missing, 2% destroyed
            $stateRandom = fake()->numberBetween(1, 100);
            $state = match (true) {
                $stateRandom <= 60 => BottleState::Stored,
                $stateRandom <= 75 => BottleState::ReservedForPicking,
                $stateRandom <= 90 => BottleState::Shipped,
                $stateRandom <= 95 => BottleState::Consumed,
                $stateRandom <= 98 => BottleState::Missing,
                default => BottleState::Destroyed,
            };

            // Assign to a case (50% chance if cases exist for this allocation)
            $case = null;
            if ($allocationCases->isNotEmpty() && fake()->boolean(50)) {
                $case = $allocationCases->random();
            }

            // NFT minting (15% of stored bottles for premium wines)
            $hasNft = $state === BottleState::Stored
                && $this->isPremiumWine($allocation->wineVariant?->wineMaster?->name ?? '')
                && fake()->boolean(15);

            // Custody holder for non-owned bottles
            $custodyHolder = null;
            if ($ownershipType !== OwnershipType::CururatedOwned) {
                $custodyHolder = fake()->randomElement([
                    'Producer - held on consignment',
                    'Third Party Logistics Partner',
                    'Client Private Cellar',
                    'Bonded Warehouse Partner',
                ]);
            }

            SerializedBottle::create([
                'serial_number' => $serialNumber,
                'wine_variant_id' => $allocation->wine_variant_id,
                'format_id' => $allocation->format_id,
                'allocation_id' => $allocation->id,
                'inbound_batch_id' => $batch->id,
                'current_location_id' => $location->id, // Always required, even for shipped bottles (last known location)
                'case_id' => $case?->id,
                'ownership_type' => $ownershipType,
                'custody_holder' => $custodyHolder,
                'state' => $state,
                'serialized_at' => $batch->received_date?->startOfDay()->addHours(12 + fake()->numberBetween(0, 8)),
                'serialized_by' => $admin?->id,
                'nft_reference' => $hasNft ? 'NFT-'.Str::uuid()->toString() : null,
                'nft_minted_at' => $hasNft ? now()->subDays(fake()->numberBetween(1, 60)) : null,
                'correction_reference' => null,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Create legacy bottles with legacy inbound batches.
     */
    private function createLegacyBottles($allocations, $locations, $cases, $admin): int
    {
        $created = 0;

        // Select some allocations for legacy bottles
        $legacyAllocations = $allocations
            ->where('serialization_required', true)
            ->take(10);

        foreach ($legacyAllocations as $allocation) {
            // Skip liquid allocations
            if ($allocation->supply_form->value === 'liquid') {
                continue;
            }

            $location = $locations->random();
            $legacyCount = fake()->numberBetween(3, 12);

            $ownershipType = match ($allocation->source_type->value) {
                'producer_allocation', 'owned_stock' => OwnershipType::CururatedOwned,
                'passive_consignment' => OwnershipType::InCustody,
                'third_party_custody' => OwnershipType::ThirdPartyOwned,
                default => OwnershipType::CururatedOwned,
            };

            // Create a legacy inbound batch for these bottles (inbound_batch_id is required)
            $legacyBatch = InboundBatch::create([
                'source_type' => 'manual_receipt',
                'product_reference_type' => 'sellable_skus',
                'product_reference_id' => $allocation->sellable_sku_id ?? $allocation->id,
                'allocation_id' => $allocation->id,
                'procurement_intent_id' => null,
                'quantity_expected' => $legacyCount,
                'quantity_received' => $legacyCount,
                'packaging_type' => 'loose',
                'receiving_location_id' => $location->id,
                'ownership_type' => $ownershipType,
                'received_date' => now()->subMonths(fake()->numberBetween(6, 24)),
                'condition_notes' => 'Legacy import - historical inventory data',
                'serialization_status' => InboundBatchStatus::FullySerialized,
                'wms_reference_id' => 'LEGACY-'.fake()->regexify('[A-Z0-9]{8}'),
            ]);

            $allocationCases = $cases->where('allocation_id', $allocation->id);

            for ($i = 0; $i < $legacyCount; $i++) {
                $serialNumber = $this->generateSerialNumber($allocation->wineVariant, $allocation->format);

                // Legacy bottles are mostly stored or shipped
                $state = fake()->randomElement([
                    BottleState::Stored,
                    BottleState::Stored,
                    BottleState::Stored,
                    BottleState::Shipped,
                    BottleState::Consumed,
                ]);

                $case = null;
                if ($allocationCases->isNotEmpty() && fake()->boolean(40)) {
                    $case = $allocationCases->random();
                }

                // Use noon UTC to avoid DST issues
                $serializedAt = now()->subMonths(fake()->numberBetween(6, 24))->startOfDay()->addHours(12);

                SerializedBottle::create([
                    'serial_number' => $serialNumber,
                    'wine_variant_id' => $allocation->wine_variant_id,
                    'format_id' => $allocation->format_id,
                    'allocation_id' => $allocation->id,
                    'inbound_batch_id' => $legacyBatch->id,
                    'current_location_id' => $location->id,
                    'case_id' => $case?->id,
                    'ownership_type' => $ownershipType,
                    'custody_holder' => $ownershipType !== OwnershipType::CururatedOwned
                        ? 'Legacy custody record'
                        : null,
                    'state' => $state,
                    'serialized_at' => $serializedAt,
                    'serialized_by' => $admin?->id,
                    'nft_reference' => null,
                    'nft_minted_at' => null,
                    'correction_reference' => null, // This is a FK to another bottle ID, not a string
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Generate a unique serial number for a bottle.
     */
    private function generateSerialNumber(WineVariant $variant, Format $format): string
    {
        // Format: {WINE_CODE}-{VINTAGE}-{FORMAT}-{RANDOM}
        // Example: SASS-2019-750-A1B2C3D4

        $wineMaster = $variant->wineMaster;
        $wineCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $wineMaster->name ?? 'WINE'), 0, 4));
        $vintage = $variant->vintage_year;
        $formatCode = $format->volume_ml;
        $randomPart = strtoupper(Str::random(8));

        return "{$wineCode}-{$vintage}-{$formatCode}-{$randomPart}";
    }

    /**
     * Check if wine is premium (for NFT minting).
     */
    private function isPremiumWine(string $wineName): bool
    {
        $premiumKeywords = [
            'Romanee-Conti', 'La Tache', 'Richebourg', 'Montrachet',
            'Petrus', 'Le Pin', 'Monfortino', 'Masseto',
            'Margaux', 'Latour', 'Lafite', 'Mouton', 'Haut-Brion',
            'Musigny', 'Chambertin', 'Salon', 'Cristal', 'Krug',
        ];

        foreach ($premiumKeywords as $keyword) {
            if (str_contains($wineName, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
