<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\InboundBatchStatus;
use App\Jobs\Inventory\MintProvenanceNftJob;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryException;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for managing serialization logic.
 *
 * Centralizes serialization operations including location authorization checks,
 * batch serialization execution, serial number generation, and NFT minting queue.
 */
class SerializationService
{
    /**
     * Check if serialization can be performed at a location.
     *
     * @return bool True if location is authorized for serialization
     *
     * @throws \InvalidArgumentException If location is null
     */
    public function canSerializeAtLocation(Location $location): bool
    {
        return $location->canSerialize();
    }

    /**
     * Serialize bottles from an inbound batch.
     *
     * Creates SerializedBottle records for the specified quantity from the batch.
     * Each bottle inherits allocation_id from the InboundBatch (immutable allocation lineage).
     *
     * @param  InboundBatch  $batch  The batch to serialize from
     * @param  int  $quantity  Number of bottles to serialize
     * @param  User  $operator  The user performing serialization
     * @return Collection<int, SerializedBottle> The created serialized bottles
     *
     * @throws \InvalidArgumentException If serialization cannot be performed
     */
    public function serializeBatch(InboundBatch $batch, int $quantity, User $operator): Collection
    {
        // Validate quantity is positive
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Quantity must be greater than zero'
            );
        }

        // Validate serialization can start on this batch
        if (! $batch->canStartSerialization()) {
            throw new \InvalidArgumentException(
                'Cannot start serialization on this batch. Check batch status and location authorization.'
            );
        }

        // Validate quantity doesn't exceed remaining unserialized
        $remainingUnserialized = $batch->remaining_unserialized;
        if ($quantity > $remainingUnserialized) {
            throw new \InvalidArgumentException(
                "Cannot serialize {$quantity} bottles. Only {$remainingUnserialized} remain unserialized."
            );
        }

        // Validate location is authorized for serialization
        $location = $batch->receivingLocation;
        if (! $location) {
            throw new \InvalidArgumentException(
                'Batch has no receiving location configured'
            );
        }

        if (! $this->canSerializeAtLocation($location)) {
            throw new \InvalidArgumentException(
                'Serialization not authorized at this location'
            );
        }

        // Validate batch has allocation lineage
        if (! $batch->hasAllocationLineage()) {
            throw new \InvalidArgumentException(
                'Batch must have allocation lineage to serialize bottles'
            );
        }

        // Validate batch has product reference
        if (! $batch->product_reference_type || ! $batch->product_reference_id) {
            throw new \InvalidArgumentException(
                'Batch must have a product reference to serialize bottles'
            );
        }

        // Create the serialized bottles
        $bottles = new Collection;
        $now = now();

        for ($i = 0; $i < $quantity; $i++) {
            $bottle = SerializedBottle::create([
                'serial_number' => $this->generateSerialNumber(),
                'wine_variant_id' => $batch->product_reference_id,
                'format_id' => $this->getDefaultFormatId($batch),
                'allocation_id' => $batch->allocation_id,
                'inbound_batch_id' => $batch->id,
                'current_location_id' => $location->id,
                'ownership_type' => $batch->ownership_type,
                'state' => BottleState::Stored,
                'serialized_at' => $now,
                'serialized_by' => $operator->id,
            ]);

            $bottles->push($bottle);

            // Queue NFT minting for each bottle
            $this->queueNftMinting($bottle);
        }

        // Update batch serialization status
        $this->updateBatchSerializationStatus($batch);

        return $bottles;
    }

    /**
     * Generate a unique serial number for a bottle.
     *
     * Format: CRU-{timestamp}-{random}
     * Example: CRU-20260204-A1B2C3D4
     *
     * @return string A unique serial number
     */
    public function generateSerialNumber(): string
    {
        $prefix = 'CRU';
        $datePart = now()->format('Ymd');
        $randomPart = strtoupper(Str::random(8));

        $serialNumber = "{$prefix}-{$datePart}-{$randomPart}";

        // Ensure uniqueness (retry if exists)
        $attempts = 0;
        $maxAttempts = 10;

        while (SerializedBottle::where('serial_number', $serialNumber)->exists()) {
            if ($attempts >= $maxAttempts) {
                throw new \RuntimeException(
                    'Failed to generate unique serial number after maximum attempts'
                );
            }

            $randomPart = strtoupper(Str::random(8));
            $serialNumber = "{$prefix}-{$datePart}-{$randomPart}";
            $attempts++;
        }

        return $serialNumber;
    }

    /**
     * Queue NFT minting for a serialized bottle.
     *
     * Dispatches MintProvenanceNftJob to handle async NFT creation.
     *
     * @param  SerializedBottle  $bottle  The bottle to mint NFT for
     */
    public function queueNftMinting(SerializedBottle $bottle): void
    {
        MintProvenanceNftJob::dispatch($bottle);
    }

    /**
     * Update the serialization status of an inbound batch.
     *
     * Status is determined by comparing serialized count to received quantity:
     * - If no bottles serialized: pending_serialization
     * - If some bottles serialized: partially_serialized
     * - If all bottles serialized: fully_serialized
     *
     * Note: Discrepancy status is not affected by this method (handled separately).
     *
     * @param  InboundBatch  $batch  The batch to update
     */
    public function updateBatchSerializationStatus(InboundBatch $batch): void
    {
        // Don't change status if batch is in discrepancy
        if ($batch->serialization_status === InboundBatchStatus::Discrepancy) {
            return;
        }

        $serializedCount = $batch->serialized_count;
        $receivedQuantity = $batch->quantity_received;

        if ($serializedCount === 0) {
            $newStatus = InboundBatchStatus::PendingSerialization;
        } elseif ($serializedCount >= $receivedQuantity) {
            $newStatus = InboundBatchStatus::FullySerialized;
        } else {
            $newStatus = InboundBatchStatus::PartiallySerialized;
        }

        $batch->update(['serialization_status' => $newStatus]);
    }

    /**
     * Get the default format ID for bottles from a batch.
     *
     * This extracts format information from the batch's packaging or product reference.
     * For now, returns the first available format for the wine variant.
     *
     * @param  InboundBatch  $batch  The batch to get format for
     * @return string The format ID
     *
     * @throws \InvalidArgumentException If no format can be determined
     */
    protected function getDefaultFormatId(InboundBatch $batch): string
    {
        // Try to get format from product reference if it's a WineVariant
        if ($batch->product_reference_type === 'App\\Models\\Pim\\WineVariant') {
            $wineVariant = $batch->productReference;
            if ($wineVariant && method_exists($wineVariant, 'formats')) {
                $format = $wineVariant->formats()->first();
                if ($format) {
                    return $format->id;
                }
            }
        }

        // Fall back to looking up a default format
        // This should be replaced with proper format handling in the UI
        $formatClass = 'App\\Models\\Pim\\Format';
        if (class_exists($formatClass)) {
            $format = $formatClass::first();
            if ($format) {
                return $format->id;
            }
        }

        throw new \InvalidArgumentException(
            'Cannot determine format for serialization. Please ensure batch has valid product reference with formats.'
        );
    }

    /**
     * Process a WMS-triggered serialization event.
     *
     * This method handles serialization requests from the WMS (Warehouse Management System).
     * If the location is not authorized for serialization, the event is REJECTED and logged
     * as a blocked system event. No override exists for this blocker.
     *
     * @param  InboundBatch  $batch  The batch to serialize
     * @param  int  $quantity  Number of bottles to serialize
     * @param  string  $wmsEventId  The WMS event ID for tracking
     * @return Collection<int, SerializedBottle>|null The created bottles, or null if rejected
     *
     * @throws \InvalidArgumentException For validation errors other than location authorization
     */
    public function processWmsSerializationEvent(
        InboundBatch $batch,
        int $quantity,
        string $wmsEventId
    ): ?Collection {
        $location = $batch->receivingLocation;

        // Hard blocker: Location must be authorized for serialization
        // This is a BLOCKING requirement with no override
        if ($location === null || ! $location->serialization_authorized) {
            $this->logBlockedWmsSerializationEvent($batch, $wmsEventId, $location);

            return null;
        }

        // Check if location is active
        if (! $location->canSerialize()) {
            $this->logBlockedWmsSerializationEvent(
                $batch,
                $wmsEventId,
                $location,
                'Location is not active or serialization not authorized'
            );

            return null;
        }

        // If authorization passes, proceed with normal serialization
        // Use a system user for WMS-triggered operations
        $systemUser = $this->getSystemUser();

        try {
            $bottles = $this->serializeBatch($batch, $quantity, $systemUser);

            // Log successful WMS serialization event
            Log::info('WMS serialization event processed successfully', [
                'wms_event_id' => $wmsEventId,
                'batch_id' => $batch->id,
                'location_id' => $location->id,
                'location_name' => $location->name,
                'quantity' => $quantity,
                'bottles_created' => $bottles->count(),
            ]);

            return $bottles;
        } catch (\InvalidArgumentException $e) {
            // Log the failure but re-throw for the caller to handle
            Log::warning('WMS serialization event failed validation', [
                'wms_event_id' => $wmsEventId,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Log a blocked WMS serialization event.
     *
     * Creates an InventoryException record and logs to system log.
     * This provides an audit trail for rejected WMS events due to location authorization.
     *
     * @param  InboundBatch  $batch  The batch that was attempted
     * @param  string  $wmsEventId  The WMS event ID
     * @param  Location|null  $location  The location (may be null if not found)
     * @param  string|null  $additionalReason  Additional context for the rejection
     */
    protected function logBlockedWmsSerializationEvent(
        InboundBatch $batch,
        string $wmsEventId,
        ?Location $location,
        ?string $additionalReason = null
    ): void {
        $locationName = $location !== null ? $location->name : 'Unknown';
        $locationId = $location !== null ? $location->id : 'N/A';
        $locationAuthorized = $location !== null ? $location->serialization_authorized : false;

        $reason = "WMS serialization event BLOCKED. Serialization not authorized at this location.\n\n";
        $reason .= "WMS Event ID: {$wmsEventId}\n";
        $reason .= "Location: {$locationName} (ID: {$locationId})\n";
        $reason .= "Batch ID: {$batch->id}\n";

        if ($additionalReason !== null) {
            $reason .= "Additional Info: {$additionalReason}\n";
        }

        $reason .= "\nThis event was automatically rejected by the system. ";
        $reason .= 'No override exists for serialization at unauthorized locations.';

        // Create an immutable InventoryException record for audit trail
        InventoryException::create([
            'exception_type' => 'wms_serialization_blocked',
            'inbound_batch_id' => $batch->id,
            'reason' => $reason,
            'resolution' => null, // Not resolved - this is a blocked event
            'created_by' => $this->getSystemUserId(),
        ]);

        // Log to system log for monitoring
        Log::warning('WMS serialization event blocked - location not authorized', [
            'wms_event_id' => $wmsEventId,
            'batch_id' => $batch->id,
            'location_id' => $locationId,
            'location_name' => $locationName,
            'location_serialization_authorized' => $locationAuthorized,
            'additional_reason' => $additionalReason,
        ]);
    }

    /**
     * Get the system user for WMS-triggered operations.
     *
     * @return User The system user
     *
     * @throws \RuntimeException If no system user can be found
     */
    protected function getSystemUser(): User
    {
        // Try to find a system user by email
        $systemUser = User::where('email', 'system@crurated.com')->first();

        if ($systemUser !== null) {
            return $systemUser;
        }

        // Fall back to first admin user (using role enum, not roles relationship)
        $adminUser = User::whereIn('role', ['admin', 'super_admin'])->first();

        if ($adminUser !== null) {
            return $adminUser;
        }

        // Last resort: first user
        $firstUser = User::first();

        if ($firstUser !== null) {
            return $firstUser;
        }

        throw new \RuntimeException(
            'No user available for WMS-triggered operations. Please ensure at least one user exists.'
        );
    }

    /**
     * Get the system user ID for audit trail purposes.
     *
     * @return int The system user ID, or 0 if no user found
     */
    protected function getSystemUserId(): int
    {
        try {
            return $this->getSystemUser()->id;
        } catch (\RuntimeException $e) {
            // Return 0 as fallback - this shouldn't happen in production
            Log::error('Failed to get system user ID for audit trail', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Check if serialization is blocked at a location.
     *
     * This is a convenience method to check the hard blocker for serialization.
     * Returns true if serialization is NOT allowed (blocked).
     *
     * @param  Location  $location  The location to check
     * @return bool True if serialization is BLOCKED, false if allowed
     */
    public function isSerializationBlocked(Location $location): bool
    {
        return ! $location->serialization_authorized || ! $location->canSerialize();
    }

    /**
     * Get the blocking reason for serialization at a location.
     *
     * Returns a human-readable reason why serialization is blocked,
     * or null if serialization is allowed.
     *
     * @param  Location  $location  The location to check
     * @return string|null The blocking reason, or null if not blocked
     */
    public function getSerializationBlockReason(Location $location): ?string
    {
        if (! $location->serialization_authorized) {
            return 'Serialization not authorized at this location';
        }

        if (! $location->isActive()) {
            return 'Location is not active';
        }

        return null;
    }
}
