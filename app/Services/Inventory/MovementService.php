<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\ConsumptionReason;
use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\MovementItem;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing inventory movement logic.
 *
 * Centralizes movement operations including creating movements, transferring
 * bottles and cases, recording consumption, and WMS event deduplication.
 */
class MovementService
{
    /**
     * Create a new inventory movement with items.
     *
     * @param  array<string, mixed>  $data  Movement data including optional items
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function createMovement(array $data): InventoryMovement
    {
        // Validate required fields
        if (! isset($data['movement_type'])) {
            throw new \InvalidArgumentException('Movement type is required');
        }

        if (! isset($data['trigger'])) {
            throw new \InvalidArgumentException('Movement trigger is required');
        }

        // Check for duplicate WMS event if provided
        $wmsEventId = $data['wms_event_id'] ?? null;
        if ($wmsEventId !== null) {
            if ($this->isDuplicateWmsEvent($wmsEventId)) {
                throw new \InvalidArgumentException(
                    "Duplicate WMS event: {$wmsEventId} has already been processed"
                );
            }
        }

        // Extract items from data
        /** @var array<int, array<string, mixed>> $items */
        $items = $data['items'] ?? [];
        unset($data['items']);

        // Set executed_at if not provided
        if (! isset($data['executed_at'])) {
            $data['executed_at'] = now();
        }

        // Create movement and items in transaction
        return DB::transaction(function () use ($data, $items) {
            $movement = InventoryMovement::create($data);

            // Create movement items
            foreach ($items as $itemData) {
                $itemData['inventory_movement_id'] = $movement->id;
                $itemData['quantity'] = $itemData['quantity'] ?? 1;
                MovementItem::create($itemData);
            }

            return $movement->fresh(['movementItems']);
        });
    }

    /**
     * Check if a WMS event has already been processed.
     *
     * Used for deduplication of WMS events.
     *
     * @param  string  $wmsEventId  The WMS event ID to check
     * @return bool True if event already exists (is duplicate)
     */
    public function isDuplicateWmsEvent(string $wmsEventId): bool
    {
        return InventoryMovement::where('wms_event_id', $wmsEventId)->exists();
    }

    /**
     * Transfer a serialized bottle to a new location.
     *
     * Creates an internal transfer movement and updates the bottle's location.
     *
     * @param  SerializedBottle  $bottle  The bottle to transfer
     * @param  Location  $destination  The destination location
     * @param  User|null  $executor  The user performing the transfer
     * @param  string|null  $reason  Optional reason for the transfer
     * @param  string|null  $wmsEventId  Optional WMS event ID for deduplication
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If transfer is not allowed
     */
    public function transferBottle(
        SerializedBottle $bottle,
        Location $destination,
        ?User $executor = null,
        ?string $reason = null,
        ?string $wmsEventId = null
    ): InventoryMovement {
        // Validate bottle can be transferred
        if ($bottle->isInTerminalState()) {
            throw new \InvalidArgumentException(
                'Cannot transfer bottle in terminal state: '.$bottle->state->label()
            );
        }

        // Get source location
        $sourceLocation = $bottle->currentLocation;
        if (! $sourceLocation) {
            throw new \InvalidArgumentException(
                'Bottle has no current location'
            );
        }

        // Don't allow transfer to same location
        if ($sourceLocation->id === $destination->id) {
            throw new \InvalidArgumentException(
                'Cannot transfer bottle to its current location'
            );
        }

        // Determine trigger based on WMS event ID
        $trigger = $wmsEventId !== null
            ? MovementTrigger::WmsEvent
            : MovementTrigger::ErpOperator;

        return DB::transaction(function () use ($bottle, $destination, $sourceLocation, $executor, $reason, $wmsEventId, $trigger) {
            // Create the movement
            $movement = $this->createMovement([
                'movement_type' => MovementType::InternalTransfer,
                'trigger' => $trigger,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destination->id,
                'custody_changed' => false,
                'reason' => $reason,
                'wms_event_id' => $wmsEventId,
                'executed_by' => $executor?->id,
                'items' => [
                    [
                        'serialized_bottle_id' => $bottle->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

            // Update bottle location
            $bottle->update(['current_location_id' => $destination->id]);

            return $movement;
        });
    }

    /**
     * Transfer a case to a new location.
     *
     * Creates an internal transfer movement and updates the case's location,
     * as well as all contained bottles' locations.
     *
     * @param  InventoryCase  $case  The case to transfer
     * @param  Location  $destination  The destination location
     * @param  User|null  $executor  The user performing the transfer
     * @param  string|null  $reason  Optional reason for the transfer
     * @param  string|null  $wmsEventId  Optional WMS event ID for deduplication
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If transfer is not allowed
     */
    public function transferCase(
        InventoryCase $case,
        Location $destination,
        ?User $executor = null,
        ?string $reason = null,
        ?string $wmsEventId = null
    ): InventoryMovement {
        // Validate case can be transferred as unit
        if (! $case->canHandleAsUnit()) {
            throw new \InvalidArgumentException(
                'Cannot transfer broken case as a unit. Transfer individual bottles instead.'
            );
        }

        // Get source location
        $sourceLocation = $case->currentLocation;
        if (! $sourceLocation) {
            throw new \InvalidArgumentException(
                'Case has no current location'
            );
        }

        // Don't allow transfer to same location
        if ($sourceLocation->id === $destination->id) {
            throw new \InvalidArgumentException(
                'Cannot transfer case to its current location'
            );
        }

        // Determine trigger based on WMS event ID
        $trigger = $wmsEventId !== null
            ? MovementTrigger::WmsEvent
            : MovementTrigger::ErpOperator;

        return DB::transaction(function () use ($case, $destination, $sourceLocation, $executor, $reason, $wmsEventId, $trigger) {
            // Create the movement
            $movement = $this->createMovement([
                'movement_type' => MovementType::InternalTransfer,
                'trigger' => $trigger,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destination->id,
                'custody_changed' => false,
                'reason' => $reason,
                'wms_event_id' => $wmsEventId,
                'executed_by' => $executor?->id,
                'items' => [
                    [
                        'case_id' => $case->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

            // Update case location
            $case->update(['current_location_id' => $destination->id]);

            // Update all contained bottles' locations
            $case->serializedBottles()->update(['current_location_id' => $destination->id]);

            return $movement;
        });
    }

    /**
     * Record destruction of a serialized bottle.
     *
     * Creates a destruction event and updates the bottle state to destroyed.
     * Used when a bottle is physically damaged, leaking, or contaminated.
     *
     * @param  SerializedBottle  $bottle  The bottle to mark as destroyed
     * @param  string  $reason  The reason for destruction (breakage, leakage, contamination)
     * @param  User|null  $executor  The user performing the destruction
     * @param  string|null  $evidence  Optional evidence notes
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If destruction is not allowed
     */
    public function recordDestruction(
        SerializedBottle $bottle,
        string $reason,
        ?User $executor = null,
        ?string $evidence = null
    ): InventoryMovement {
        // Validate bottle can be destroyed
        if ($bottle->isInTerminalState()) {
            throw new \InvalidArgumentException(
                'Cannot destroy bottle already in terminal state: '.$bottle->state->label()
            );
        }

        // Get the bottle's current location
        $location = $bottle->currentLocation;

        // Build reason text
        $reasonText = "Destroyed - {$reason}";
        if ($evidence !== null && $evidence !== '') {
            $reasonText .= ". Evidence: {$evidence}";
        }

        return DB::transaction(function () use ($bottle, $executor, $reasonText, $location) {
            // Create the movement (consumption movement type with destruction reason)
            $movement = $this->createMovement([
                'movement_type' => MovementType::EventConsumption,
                'trigger' => MovementTrigger::ErpOperator,
                'source_location_id' => $location?->id,
                'destination_location_id' => null, // Destruction has no destination
                'custody_changed' => false,
                'reason' => $reasonText,
                'executed_by' => $executor?->id,
                'items' => [
                    [
                        'serialized_bottle_id' => $bottle->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

            // Update bottle state to destroyed
            $bottle->update(['state' => BottleState::Destroyed]);

            return $movement;
        });
    }

    /**
     * Record a serialized bottle as missing.
     *
     * Creates a missing event and updates the bottle state to missing.
     * Used when a bottle cannot be located (e.g., consignment lost).
     *
     * @param  SerializedBottle  $bottle  The bottle to mark as missing
     * @param  string  $reason  The reason for marking as missing
     * @param  User|null  $executor  The user performing the action
     * @param  string|null  $lastKnownCustody  Last known custody holder
     * @param  string|null  $agreementReference  Agreement reference (for consignment)
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If marking as missing is not allowed
     */
    public function recordMissing(
        SerializedBottle $bottle,
        string $reason,
        ?User $executor = null,
        ?string $lastKnownCustody = null,
        ?string $agreementReference = null
    ): InventoryMovement {
        // Validate bottle can be marked as missing
        if ($bottle->isInTerminalState()) {
            throw new \InvalidArgumentException(
                'Cannot mark bottle as missing when already in terminal state: '.$bottle->state->label()
            );
        }

        // Get the bottle's current location
        $location = $bottle->currentLocation;

        // Build reason text
        $reasonText = "Missing - {$reason}";
        if ($lastKnownCustody !== null && $lastKnownCustody !== '') {
            $reasonText .= ". Last known custody: {$lastKnownCustody}";
        }
        if ($agreementReference !== null && $agreementReference !== '') {
            $reasonText .= ". Agreement reference: {$agreementReference}";
        }

        return DB::transaction(function () use ($bottle, $executor, $reasonText, $location) {
            // Create the movement (using InternalTransfer type with missing reason)
            // Note: There's no dedicated MovementType for missing, we use InternalTransfer
            // to record the last known location transition to "missing" state
            $movement = $this->createMovement([
                'movement_type' => MovementType::InternalTransfer,
                'trigger' => MovementTrigger::ErpOperator,
                'source_location_id' => $location?->id,
                'destination_location_id' => null, // Missing has no destination
                'custody_changed' => true, // Custody effectively changes when item goes missing
                'reason' => $reasonText,
                'executed_by' => $executor?->id,
                'items' => [
                    [
                        'serialized_bottle_id' => $bottle->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

            // Update bottle state to missing
            $bottle->update(['state' => BottleState::Missing]);

            return $movement;
        });
    }

    /**
     * Record consumption of a serialized bottle.
     *
     * Creates a consumption movement and updates the bottle state to consumed.
     *
     * @param  SerializedBottle  $bottle  The bottle to consume
     * @param  ConsumptionReason  $reason  The reason for consumption
     * @param  User|null  $executor  The user performing the consumption
     * @param  string|null  $notes  Optional additional notes
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If consumption is not allowed
     */
    public function recordConsumption(
        SerializedBottle $bottle,
        ConsumptionReason $reason,
        ?User $executor = null,
        ?string $notes = null
    ): InventoryMovement {
        // Validate bottle can be consumed
        if (! $bottle->isStored()) {
            throw new \InvalidArgumentException(
                'Cannot consume bottle not in stored state. Current state: '.$bottle->state->label()
            );
        }

        // Check ownership type for event consumption
        if ($reason === ConsumptionReason::EventConsumption && ! $bottle->ownership_type->canConsumeForEvents()) {
            throw new \InvalidArgumentException(
                'Cannot consume bottle for events: ownership type '.$bottle->ownership_type->label().' not allowed'
            );
        }

        // Get the bottle's current location
        $location = $bottle->currentLocation;

        // Build reason text
        $reasonText = $reason->label();
        if ($notes !== null) {
            $reasonText .= ": {$notes}";
        }

        return DB::transaction(function () use ($bottle, $reason, $executor, $reasonText, $location) {
            // Determine movement type based on reason
            $movementType = $reason === ConsumptionReason::EventConsumption
                ? MovementType::EventConsumption
                : MovementType::EventConsumption; // All consumption uses same movement type

            // Create the movement
            $movement = $this->createMovement([
                'movement_type' => $movementType,
                'trigger' => MovementTrigger::ErpOperator,
                'source_location_id' => $location?->id,
                'destination_location_id' => null, // Consumption has no destination
                'custody_changed' => false,
                'reason' => $reasonText,
                'executed_by' => $executor?->id,
                'items' => [
                    [
                        'serialized_bottle_id' => $bottle->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

            // Update bottle state to consumed
            $bottle->update(['state' => BottleState::Consumed]);

            return $movement;
        });
    }
}
