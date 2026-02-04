<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\ConsumptionReason;
use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\MovementItem;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Process a WMS event with deduplication and audit logging.
     *
     * If the WMS event has already been processed (duplicate), it is ignored
     * and logged to the audit trail. This ensures operators see a clean,
     * deduplicated history with no double movements.
     *
     * @param  string  $wmsEventId  The WMS event ID
     * @param  callable  $processCallback  Callback to process the event if not duplicate
     * @return InventoryMovement|null The created movement, or null if duplicate
     */
    public function processWmsEvent(string $wmsEventId, callable $processCallback): ?InventoryMovement
    {
        // Check for duplicate
        if ($this->isDuplicateWmsEvent($wmsEventId)) {
            // Log the duplicate event for audit purposes
            $this->logIgnoredWmsEvent($wmsEventId);

            return null;
        }

        // Process the event
        return $processCallback();
    }

    /**
     * Log an ignored (duplicate) WMS event for audit purposes.
     *
     * Creates an InventoryException record to track the ignored event.
     * This ensures full audit trail is preserved even for rejected events.
     *
     * @param  string  $wmsEventId  The WMS event ID that was ignored
     */
    public function logIgnoredWmsEvent(string $wmsEventId): void
    {
        // Log to application log for monitoring
        Log::info("WMS event ignored (duplicate): {$wmsEventId}");

        // Get the existing movement that caused this to be a duplicate
        $existingMovement = InventoryMovement::where('wms_event_id', $wmsEventId)->first();

        // Get a system user for the audit record
        $systemUser = $this->getSystemUser();

        // Create an InventoryException record for audit trail
        InventoryException::create([
            'exception_type' => 'wms_event_duplicate_ignored',
            'reason' => "Duplicate WMS event ignored: {$wmsEventId}. Original movement ID: ".($existingMovement !== null ? $existingMovement->id : 'unknown'),
            'created_by' => $systemUser->id,
        ]);
    }

    /**
     * Get a system user for WMS operations.
     *
     * Tries to find a system user, falls back to admin or first user.
     *
     * @return User The system user
     *
     * @throws \InvalidArgumentException If no user exists in the system
     */
    protected function getSystemUser(): User
    {
        // Try to find a system user by email
        $systemUser = User::where('email', 'system@crurated.com')->first();
        if ($systemUser) {
            return $systemUser;
        }

        // Fall back to an admin user
        $adminUser = User::whereIn('role', ['admin', 'super_admin'])->first();
        if ($adminUser) {
            return $adminUser;
        }

        // Last resort: first user in the system
        $firstUser = User::first();
        if ($firstUser) {
            return $firstUser;
        }

        throw new \InvalidArgumentException('No user exists in the system to attribute WMS event audit log');
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
     * Break a case (open it for individual bottle access).
     *
     * Marks the case as broken and records the action in the audit trail.
     * Breaking is IRREVERSIBLE - the case can never be handled as a unit again.
     * The contained bottles immediately become "loose stock" and are managed individually.
     *
     * @param  InventoryCase  $case  The case to break
     * @param  string  $reason  The reason for breaking the case
     * @param  User  $executor  The user breaking the case
     * @return InventoryCase The broken case
     *
     * @throws \InvalidArgumentException If breaking is not allowed
     */
    public function breakCase(
        InventoryCase $case,
        string $reason,
        User $executor
    ): InventoryCase {
        // Validate case can be broken
        if (! $case->canBreak()) {
            if ($case->isBroken()) {
                throw new \InvalidArgumentException(
                    'Case is already broken. Breaking is irreversible.'
                );
            }
            if (! $case->is_breakable) {
                throw new \InvalidArgumentException(
                    'This case is not breakable. It must remain sealed.'
                );
            }
            throw new \InvalidArgumentException(
                'Case cannot be broken in its current state.'
            );
        }

        return DB::transaction(function () use ($case, $reason, $executor) {
            // Update case integrity status and breaking details
            $case->update([
                'integrity_status' => \App\Enums\Inventory\CaseIntegrityStatus::Broken,
                'broken_at' => now(),
                'broken_by' => $executor->id,
                'broken_reason' => $reason,
            ]);

            // Create a movement record to log the breaking action
            // Using InternalTransfer type to record this physical event
            $this->createMovement([
                'movement_type' => MovementType::InternalTransfer,
                'trigger' => MovementTrigger::ErpOperator,
                'source_location_id' => $case->current_location_id,
                'destination_location_id' => $case->current_location_id, // Same location - just a status change
                'custody_changed' => false,
                'reason' => "Case broken: {$reason}",
                'executed_by' => $executor->id,
                'items' => [
                    [
                        'case_id' => $case->id,
                        'quantity' => 1,
                        'notes' => 'Case opened - bottles now managed individually',
                    ],
                ],
            ]);

            return $case->fresh();
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

    /**
     * Place a serialized bottle in consignment at a consignee location.
     *
     * Creates a consignment placement movement where ownership remains with Crurated
     * but custody changes to the consignee. Only Crurated-owned bottles can be placed
     * in consignment.
     *
     * @param  SerializedBottle  $bottle  The bottle to place in consignment
     * @param  Location  $consigneeLocation  The consignee location
     * @param  User|null  $executor  The user performing the placement
     * @param  string|null  $reason  Optional reason for the consignment
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If placement is not allowed
     */
    public function placeBottleInConsignment(
        SerializedBottle $bottle,
        Location $consigneeLocation,
        ?User $executor = null,
        ?string $reason = null
    ): InventoryMovement {
        // Validate bottle can be transferred
        if ($bottle->isInTerminalState()) {
            throw new \InvalidArgumentException(
                'Cannot place bottle in consignment: bottle is in terminal state '.$bottle->state->label()
            );
        }

        // Validate bottle is Crurated-owned
        if (! $bottle->ownership_type->hasFullOwnership()) {
            throw new \InvalidArgumentException(
                'Cannot place bottle in consignment: only Crurated-owned bottles can be placed in consignment. Current ownership: '.$bottle->ownership_type->label()
            );
        }

        // Get source location
        $sourceLocation = $bottle->currentLocation;
        if (! $sourceLocation) {
            throw new \InvalidArgumentException(
                'Bottle has no current location'
            );
        }

        // Don't allow placement to same location
        if ($sourceLocation->id === $consigneeLocation->id) {
            throw new \InvalidArgumentException(
                'Cannot place bottle in consignment at its current location'
            );
        }

        return DB::transaction(function () use ($bottle, $consigneeLocation, $sourceLocation, $executor, $reason) {
            // Create the movement
            $movement = $this->createMovement([
                'movement_type' => MovementType::ConsignmentPlacement,
                'trigger' => MovementTrigger::ErpOperator,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $consigneeLocation->id,
                'custody_changed' => true, // Custody changes to consignee
                'reason' => $reason,
                'executed_by' => $executor?->id,
                'items' => [
                    [
                        'serialized_bottle_id' => $bottle->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

            // Update bottle location and custody holder
            $bottle->update([
                'current_location_id' => $consigneeLocation->id,
                'custody_holder' => $consigneeLocation->name,
            ]);

            return $movement;
        });
    }

    /**
     * Place a case in consignment at a consignee location.
     *
     * Creates a consignment placement movement where ownership remains with Crurated
     * but custody changes to the consignee. Only cases with Crurated-owned allocation
     * can be placed in consignment. All contained bottles are also moved.
     *
     * @param  InventoryCase  $case  The case to place in consignment
     * @param  Location  $consigneeLocation  The consignee location
     * @param  User|null  $executor  The user performing the placement
     * @param  string|null  $reason  Optional reason for the consignment
     * @return InventoryMovement The created movement
     *
     * @throws \InvalidArgumentException If placement is not allowed
     */
    public function placeCaseInConsignment(
        InventoryCase $case,
        Location $consigneeLocation,
        ?User $executor = null,
        ?string $reason = null
    ): InventoryMovement {
        // Validate case can be transferred as unit
        if (! $case->canHandleAsUnit()) {
            throw new \InvalidArgumentException(
                'Cannot place broken case in consignment. Place individual bottles instead.'
            );
        }

        // Get source location
        $sourceLocation = $case->currentLocation;
        if (! $sourceLocation) {
            throw new \InvalidArgumentException(
                'Case has no current location'
            );
        }

        // Don't allow placement to same location
        if ($sourceLocation->id === $consigneeLocation->id) {
            throw new \InvalidArgumentException(
                'Cannot place case in consignment at its current location'
            );
        }

        return DB::transaction(function () use ($case, $consigneeLocation, $sourceLocation, $executor, $reason) {
            // Create the movement
            $movement = $this->createMovement([
                'movement_type' => MovementType::ConsignmentPlacement,
                'trigger' => MovementTrigger::ErpOperator,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $consigneeLocation->id,
                'custody_changed' => true, // Custody changes to consignee
                'reason' => $reason,
                'executed_by' => $executor?->id,
                'items' => [
                    [
                        'case_id' => $case->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

            // Update case location
            $case->update(['current_location_id' => $consigneeLocation->id]);

            // Update all contained bottles' locations and custody holder
            $case->serializedBottles()->update([
                'current_location_id' => $consigneeLocation->id,
                'custody_holder' => $consigneeLocation->name,
            ]);

            return $movement;
        });
    }
}
