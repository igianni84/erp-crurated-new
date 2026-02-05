<?php

namespace Database\Seeders;

use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * InventoryMovementSeeder - Creates inventory movement records
 *
 * Inventory movements are immutable records of physical inventory events.
 * They form an append-only audit ledger.
 *
 * Movement types:
 * - InternalTransfer: Moving inventory between own warehouses
 * - ConsignmentPlacement: Placing inventory with consignee
 * - ConsignmentReturn: Retrieving from consignee
 * - EventShipment: Sending to events/tastings
 * - EventConsumption: Consumed at events (reduces inventory)
 */
class InventoryMovementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get locations
        $locations = Location::where('status', 'active')->get();

        if ($locations->isEmpty()) {
            $this->command->warn('No active locations found. Run LocationSeeder first.');

            return;
        }

        // Get admin user
        $admin = User::first();

        // Separate locations by type
        $mainWarehouses = $locations->where('location_type', 'main_warehouse');
        $satelliteWarehouses = $locations->where('location_type', 'satellite_warehouse');
        $consigneeLocations = $locations->where('location_type', 'consignee');
        $eventLocations = $locations->where('location_type', 'event_location');
        $thirdPartyStorage = $locations->where('location_type', 'third_party_storage');

        $totalCreated = 0;

        // =========================================================================
        // 1. INTERNAL TRANSFERS (between warehouses)
        // =========================================================================
        $allWarehouses = $mainWarehouses->merge($satelliteWarehouses);

        if ($allWarehouses->count() >= 2) {
            for ($i = 0; $i < 25; $i++) {
                $source = $allWarehouses->random();
                $destination = $allWarehouses->where('id', '!=', $source->id)->random();

                $triggerType = fake()->randomElement([
                    MovementTrigger::WmsEvent,
                    MovementTrigger::WmsEvent,
                    MovementTrigger::ErpOperator,
                    MovementTrigger::SystemAutomatic,
                ]);

                $reasons = [
                    'Rebalancing inventory across locations',
                    'Consolidation for efficiency',
                    'Temperature-controlled storage requirement',
                    'Preparing for upcoming demand',
                    'Space optimization',
                    'Customer proximity optimization',
                    'Pre-positioning for fulfillment',
                ];

                InventoryMovement::create([
                    'movement_type' => MovementType::InternalTransfer,
                    'trigger' => $triggerType,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'custody_changed' => false,
                    'reason' => fake()->randomElement($reasons),
                    'wms_event_id' => $triggerType === MovementTrigger::WmsEvent
                        ? 'WMS-TRF-'.fake()->regexify('[A-Z0-9]{10}')
                        : null,
                    'executed_at' => fake()->dateTimeBetween('-6 months', 'now'),
                    'executed_by' => $admin?->id,
                ]);

                $totalCreated++;
            }
        }

        // =========================================================================
        // 2. CONSIGNMENT PLACEMENTS (to consignees)
        // =========================================================================
        if ($mainWarehouses->isNotEmpty() && $consigneeLocations->isNotEmpty()) {
            for ($i = 0; $i < 15; $i++) {
                $source = $mainWarehouses->random();
                $destination = $consigneeLocations->random();

                $reasons = [
                    'Partner restaurant display inventory',
                    'Wine club exclusive allocation',
                    'Retailer consignment stock',
                    'Hotel cellar program',
                    'Private client storage at partner facility',
                ];

                InventoryMovement::create([
                    'movement_type' => MovementType::ConsignmentPlacement,
                    'trigger' => MovementTrigger::ErpOperator,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'custody_changed' => true,
                    'reason' => fake()->randomElement($reasons),
                    'wms_event_id' => null,
                    'executed_at' => fake()->dateTimeBetween('-4 months', 'now'),
                    'executed_by' => $admin?->id,
                ]);

                $totalCreated++;
            }
        }

        // =========================================================================
        // 3. CONSIGNMENT RETURNS (from consignees)
        // =========================================================================
        if ($mainWarehouses->isNotEmpty() && $consigneeLocations->isNotEmpty()) {
            for ($i = 0; $i < 8; $i++) {
                $source = $consigneeLocations->random();
                $destination = $mainWarehouses->random();

                $reasons = [
                    'Consignment period ended',
                    'Partner program closure',
                    'Inventory recall for sale',
                    'Quality inspection required',
                    'End of seasonal placement',
                ];

                InventoryMovement::create([
                    'movement_type' => MovementType::ConsignmentReturn,
                    'trigger' => MovementTrigger::ErpOperator,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'custody_changed' => true,
                    'reason' => fake()->randomElement($reasons),
                    'wms_event_id' => null,
                    'executed_at' => fake()->dateTimeBetween('-3 months', 'now'),
                    'executed_by' => $admin?->id,
                ]);

                $totalCreated++;
            }
        }

        // =========================================================================
        // 4. EVENT SHIPMENTS (to event locations)
        // =========================================================================
        if ($mainWarehouses->isNotEmpty() && $eventLocations->isNotEmpty()) {
            for ($i = 0; $i < 12; $i++) {
                $source = $mainWarehouses->random();
                $destination = $eventLocations->random();

                $events = [
                    'Vinitaly 2025 tasting booth',
                    'Private collector dinner - Milan',
                    'Burgundy masterclass event',
                    'Barolo vertical tasting',
                    'Annual member appreciation dinner',
                    'Wine investment seminar',
                    'Press tasting event',
                    'Charity auction preview',
                ];

                InventoryMovement::create([
                    'movement_type' => MovementType::EventShipment,
                    'trigger' => MovementTrigger::ErpOperator,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'custody_changed' => false,
                    'reason' => fake()->randomElement($events),
                    'wms_event_id' => null,
                    'executed_at' => fake()->dateTimeBetween('-2 months', '+1 month'),
                    'executed_by' => $admin?->id,
                ]);

                $totalCreated++;
            }
        }

        // =========================================================================
        // 5. EVENT CONSUMPTION (bottles consumed at events)
        // =========================================================================
        if ($eventLocations->isNotEmpty()) {
            for ($i = 0; $i < 10; $i++) {
                $eventLocation = $eventLocations->random();

                $consumptionReasons = [
                    'Tasting event - 6 bottles opened for guests',
                    'Masterclass - 3 bottles used for education',
                    'VIP dinner - 12 bottles served',
                    'Press tasting - 4 bottles opened',
                    'Quality control check - 1 bottle sampled',
                    'Member event - 8 bottles consumed',
                ];

                InventoryMovement::create([
                    'movement_type' => MovementType::EventConsumption,
                    'trigger' => MovementTrigger::ErpOperator,
                    'source_location_id' => $eventLocation->id,
                    'destination_location_id' => null, // Consumed, no destination
                    'custody_changed' => false,
                    'reason' => fake()->randomElement($consumptionReasons),
                    'wms_event_id' => null,
                    'executed_at' => fake()->dateTimeBetween('-2 months', 'now'),
                    'executed_by' => $admin?->id,
                ]);

                $totalCreated++;
            }
        }

        // =========================================================================
        // 6. THIRD PARTY STORAGE MOVEMENTS
        // =========================================================================
        if ($mainWarehouses->isNotEmpty() && $thirdPartyStorage->isNotEmpty()) {
            // Movements to third party storage
            for ($i = 0; $i < 6; $i++) {
                $source = $mainWarehouses->random();
                $destination = $thirdPartyStorage->random();

                InventoryMovement::create([
                    'movement_type' => MovementType::InternalTransfer,
                    'trigger' => MovementTrigger::ErpOperator,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'custody_changed' => true,
                    'reason' => fake()->randomElement([
                        'Long-term storage at bonded facility',
                        'Climate-controlled aging program',
                        'Capacity overflow management',
                        'Strategic reserve placement',
                    ]),
                    'wms_event_id' => null,
                    'executed_at' => fake()->dateTimeBetween('-6 months', 'now'),
                    'executed_by' => $admin?->id,
                ]);

                $totalCreated++;
            }

            // Movements from third party storage back to main warehouse
            for ($i = 0; $i < 4; $i++) {
                $source = $thirdPartyStorage->random();
                $destination = $mainWarehouses->random();

                InventoryMovement::create([
                    'movement_type' => MovementType::InternalTransfer,
                    'trigger' => MovementTrigger::ErpOperator,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'custody_changed' => true,
                    'reason' => fake()->randomElement([
                        'Retrieval for customer fulfillment',
                        'Consolidation for sale',
                        'Quality inspection required',
                        'Contract period ended',
                    ]),
                    'wms_event_id' => null,
                    'executed_at' => fake()->dateTimeBetween('-3 months', 'now'),
                    'executed_by' => $admin?->id,
                ]);

                $totalCreated++;
            }
        }

        // =========================================================================
        // 7. WMS-TRIGGERED AUTOMATIC MOVEMENTS
        // =========================================================================
        if ($allWarehouses->count() >= 2) {
            for ($i = 0; $i < 10; $i++) {
                $source = $allWarehouses->random();
                $destination = $allWarehouses->where('id', '!=', $source->id)->random();

                InventoryMovement::create([
                    'movement_type' => MovementType::InternalTransfer,
                    'trigger' => MovementTrigger::WmsEvent,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'custody_changed' => false,
                    'reason' => fake()->randomElement([
                        'WMS auto-replenishment',
                        'Pick location optimization',
                        'Temperature zone rebalancing',
                        'Automated inventory rotation',
                    ]),
                    'wms_event_id' => 'WMS-AUTO-'.fake()->regexify('[A-Z0-9]{10}'),
                    'executed_at' => fake()->dateTimeBetween('-1 month', 'now'),
                    'executed_by' => null, // System triggered, no user
                ]);

                $totalCreated++;
            }
        }

        $this->command->info("Created {$totalCreated} inventory movements.");
    }
}
