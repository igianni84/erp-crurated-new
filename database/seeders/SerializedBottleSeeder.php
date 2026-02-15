<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Inventory\ConsumptionReason;
use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\OwnershipType;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use App\Services\Inventory\MovementService;
use App\Services\Inventory\SerializationService;
use Illuminate\Database\Seeder;

/**
 * SerializedBottleSeeder - Creates serialized bottles via SerializationService lifecycle.
 *
 * Uses SerializationService::serializeBatch() which:
 * - Validates batch status, location authorization, allocation lineage
 * - Creates SerializedBottle records with state=Stored
 * - Generates serial numbers (CRU-YYYYMMDD-XXXXXXXX)
 * - Updates batch serialization_status automatically
 *
 * Terminal states are then applied via MovementService:
 * - recordDestruction() for damaged bottles
 * - recordMissing() for unaccounted bottles
 * - recordConsumption() for tasted/sampled bottles
 */
class SerializedBottleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $serializationService = app(SerializationService::class);
        $movementService = app(MovementService::class);

        // Get serialization-authorized locations
        $locations = Location::where('serialization_authorized', true)
            ->where('status', LocationStatus::Active)
            ->get();

        if ($locations->isEmpty()) {
            $this->command->warn('No serialization-authorized locations found. Run LocationSeeder first.');

            return;
        }

        // Get allocations that require serialization
        $allocations = Allocation::where('serialization_required', true)
            ->where('status', AllocationStatus::Active)
            ->with(['wineVariant.wineMaster', 'format'])
            ->get();

        if ($allocations->isEmpty()) {
            $this->command->warn('No allocations requiring serialization found. Run AllocationSeeder first.');

            return;
        }

        $admin = User::first();
        if (! $admin) {
            $this->command->warn('No admin user found. Run UserSeeder first.');

            return;
        }

        $totalCreated = 0;
        $bottlesForTerminalStates = [];

        foreach ($allocations as $allocation) {
            // Skip liquid allocations
            if ($allocation->supply_form->value === 'liquid') {
                continue;
            }

            // Create 1-3 inbound batches per allocation
            $batchCount = fake()->numberBetween(1, 3);

            for ($b = 0; $b < $batchCount; $b++) {
                $created = $this->createBatchAndSerialize(
                    $serializationService,
                    $allocation,
                    $locations,
                    $admin,
                    $bottlesForTerminalStates
                );
                $totalCreated += $created;
            }
        }

        // Apply terminal states via MovementService
        $terminalCount = $this->applyTerminalStates($movementService, $bottlesForTerminalStates, $admin);

        $this->command->info("Created {$totalCreated} serialized bottles ({$terminalCount} transitioned to terminal states via MovementService).");
    }

    /**
     * Create an inbound batch and serialize bottles via SerializationService.
     */
    private function createBatchAndSerialize(
        SerializationService $serializationService,
        Allocation $allocation,
        $locations,
        User $admin,
        array &$bottlesForTerminalStates
    ): int {
        $location = $locations->random();

        // Determine batch quantity (6-24 bottles typically)
        $batchQuantity = fake()->randomElement([6, 6, 12, 12, 18, 24]);

        // Determine ownership type based on allocation source type
        $ownershipType = match ($allocation->source_type) {
            AllocationSourceType::ProducerAllocation, AllocationSourceType::OwnedStock => OwnershipType::CururatedOwned,
            AllocationSourceType::PassiveConsignment => OwnershipType::InCustody,
            AllocationSourceType::ThirdPartyCustody => OwnershipType::ThirdPartyOwned,
        };

        // Create inbound batch as PendingSerialization (service will update status)
        $batch = InboundBatch::create([
            'source_type' => fake()->randomElement(['procurement', 'transfer', 'manual_receipt']),
            'product_reference_type' => 'App\\Models\\Pim\\WineVariant',
            'product_reference_id' => $allocation->wine_variant_id,
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
            'serialization_status' => InboundBatchStatus::PendingSerialization,
            'wms_reference_id' => fake()->boolean(40) ? 'WMS-'.fake()->regexify('[A-Z0-9]{10}') : null,
        ]);

        // Determine how many to serialize: 85% fully, 15% partially
        $serializeCount = fake()->boolean(85)
            ? $batchQuantity
            : fake()->numberBetween(1, $batchQuantity - 1);

        try {
            $bottles = $serializationService->serializeBatch($batch, $serializeCount, $admin);

            // Mark ~10% of bottles for terminal states (destroyed, missing, consumed)
            foreach ($bottles as $bottle) {
                if (fake()->boolean(10)) {
                    $bottlesForTerminalStates[] = $bottle;
                }
            }

            return $bottles->count();
        } catch (\Throwable $e) {
            $this->command->warn("Serialization failed for batch {$batch->id}: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Apply terminal states to a subset of bottles via MovementService.
     */
    private function applyTerminalStates(
        MovementService $movementService,
        array $bottles,
        User $admin
    ): int {
        $transitioned = 0;

        foreach ($bottles as $bottle) {
            // Distribution: 40% destroyed, 30% missing, 30% consumed
            $stateRandom = fake()->numberBetween(1, 100);

            try {
                if ($stateRandom <= 40) {
                    // Destroyed
                    $movementService->recordDestruction(
                        $bottle,
                        fake()->randomElement([
                            'Cork failure - wine spoiled',
                            'Bottle cracked during handling',
                            'Label damage beyond recovery',
                            'Temperature excursion damage',
                            'Transit damage - insurance claimed',
                        ]),
                        $admin,
                        fake()->boolean(50) ? 'INS-'.fake()->regexify('[A-Z0-9]{8}') : null
                    );
                } elseif ($stateRandom <= 70) {
                    // Missing
                    $movementService->recordMissing(
                        $bottle,
                        fake()->randomElement([
                            'Inventory count discrepancy',
                            'WMS scan mismatch',
                            'Unaccounted during transfer',
                            'Cycle count variance',
                        ]),
                        $admin,
                        fake()->boolean(30) ? 'Last seen in main warehouse zone A' : null,
                        fake()->boolean(20) ? 'AGR-'.fake()->regexify('[A-Z0-9]{6}') : null
                    );
                } else {
                    // Consumed
                    $movementService->recordConsumption(
                        $bottle,
                        fake()->randomElement([
                            ConsumptionReason::EventConsumption,
                            ConsumptionReason::Sampling,
                            ConsumptionReason::DamageWriteoff,
                        ]),
                        $admin,
                        fake()->randomElement([
                            'Vinitaly 2025 tasting booth',
                            'Private collector dinner',
                            'Quality assurance sampling',
                            'Press tasting event',
                            'Member appreciation dinner',
                        ])
                    );
                }

                $transitioned++;
            } catch (\Throwable $e) {
                $this->command->warn("Terminal state failed for bottle {$bottle->id}: {$e->getMessage()}");
            }
        }

        return $transitioned;
    }
}
