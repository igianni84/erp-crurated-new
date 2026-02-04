<?php

namespace App\Services\Procurement;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Models\AuditLog;
use App\Models\Procurement\Inbound;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing Inbound lifecycle.
 *
 * Centralizes all inbound business logic including creation,
 * state transitions, validation, and hand-off to Module B.
 *
 * IMPORTANT: Inbound records physical arrival of goods.
 * It does NOT imply ownership - ownership_flag must be explicitly set.
 */
class InboundService
{
    /**
     * Record a new inbound (creates with status = recorded).
     *
     * Creates an inbound record representing physical arrival of goods.
     * Note: This does NOT imply ownership - ownership_flag defaults to 'pending'.
     *
     * @param  array<string, mixed>  $data  Required keys: warehouse, product_reference_type, product_reference_id, quantity, packaging, ownership_flag, received_date. Optional: procurement_intent_id, purchase_order_id, condition_notes, serialization_required, serialization_location_authorized, serialization_routing_rule
     *
     * @throws \InvalidArgumentException If required data is missing or invalid
     */
    public function record(array $data): Inbound
    {
        $requiredFields = [
            'warehouse',
            'product_reference_type',
            'product_reference_id',
            'quantity',
            'packaging',
            'ownership_flag',
            'received_date',
        ];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                throw new \InvalidArgumentException(
                    "Missing required field: {$field}"
                );
            }
        }

        /** @var int $quantity */
        $quantity = $data['quantity'];
        /** @var string $productReferenceType */
        $productReferenceType = $data['product_reference_type'];

        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Quantity must be greater than 0.'
            );
        }

        $validProductTypes = ['sellable_skus', 'liquid_products'];
        if (! in_array($productReferenceType, $validProductTypes, true)) {
            throw new \InvalidArgumentException(
                'Invalid product_reference_type. Must be one of: '.implode(', ', $validProductTypes)
            );
        }

        // Validate packaging if it's a string (convert to enum)
        $packaging = $data['packaging'];
        if (is_string($packaging)) {
            $packaging = InboundPackaging::tryFrom($packaging);
            if ($packaging === null) {
                throw new \InvalidArgumentException(
                    'Invalid packaging. Must be one of: cases, loose, mixed'
                );
            }
        }

        // Validate ownership_flag if it's a string (convert to enum)
        $ownershipFlag = $data['ownership_flag'];
        if (is_string($ownershipFlag)) {
            $ownershipFlag = OwnershipFlag::tryFrom($ownershipFlag);
            if ($ownershipFlag === null) {
                throw new \InvalidArgumentException(
                    'Invalid ownership_flag. Must be one of: owned, in_custody, pending'
                );
            }
        }

        return DB::transaction(function () use ($data, $packaging, $ownershipFlag): Inbound {
            $inbound = Inbound::create([
                'procurement_intent_id' => $data['procurement_intent_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse' => $data['warehouse'],
                'product_reference_type' => $data['product_reference_type'],
                'product_reference_id' => $data['product_reference_id'],
                'quantity' => $data['quantity'],
                'packaging' => $packaging,
                'ownership_flag' => $ownershipFlag,
                'received_date' => $data['received_date'],
                'condition_notes' => $data['condition_notes'] ?? null,
                'serialization_required' => $data['serialization_required'] ?? true,
                'serialization_location_authorized' => $data['serialization_location_authorized'] ?? null,
                'serialization_routing_rule' => $data['serialization_routing_rule'] ?? null,
                'status' => InboundStatus::Recorded,
                'handed_to_module_b' => false,
            ]);

            $this->logCreation($inbound, $data);

            return $inbound;
        });
    }

    /**
     * Route an inbound (recorded → routed).
     *
     * Assigns a serialization location and validates routing constraints.
     *
     * @throws \InvalidArgumentException If transition is not allowed or routing is invalid
     */
    public function route(Inbound $inbound, string $location): Inbound
    {
        if (! $inbound->status->canTransitionTo(InboundStatus::Routed)) {
            throw new \InvalidArgumentException(
                "Cannot route inbound: current status '{$inbound->status->label()}' does not allow transition to Routed. "
                .'Only Recorded inbounds can be routed.'
            );
        }

        if (empty($location)) {
            throw new \InvalidArgumentException(
                'Serialization location is required for routing.'
            );
        }

        // Update the serialization location
        $inbound->serialization_location_authorized = $location;

        // Validate serialization routing
        $this->validateSerializationRouting($inbound);

        $oldStatus = $inbound->status;

        return DB::transaction(function () use ($inbound, $oldStatus, $location): Inbound {
            $inbound->status = InboundStatus::Routed;
            $inbound->save();

            $this->logStatusTransition($inbound, $oldStatus, InboundStatus::Routed, [
                'serialization_location_authorized' => $location,
            ]);

            return $inbound;
        });
    }

    /**
     * Complete an inbound (routed → completed).
     *
     * Validates that ownership has been clarified before completion.
     *
     * @throws \InvalidArgumentException If transition is not allowed or ownership is unclear
     */
    public function complete(Inbound $inbound): Inbound
    {
        if (! $inbound->status->canTransitionTo(InboundStatus::Completed)) {
            throw new \InvalidArgumentException(
                "Cannot complete inbound: current status '{$inbound->status->label()}' does not allow transition to Completed. "
                .'Only Routed inbounds can be completed.'
            );
        }

        // Validate ownership clarity
        $this->validateOwnershipClarity($inbound);

        $oldStatus = $inbound->status;

        return DB::transaction(function () use ($inbound, $oldStatus): Inbound {
            $inbound->status = InboundStatus::Completed;
            $inbound->save();

            $this->logStatusTransition($inbound, $oldStatus, InboundStatus::Completed);

            return $inbound;
        });
    }

    /**
     * Hand off an inbound to Module B for inventory management.
     *
     * This is a one-way operation - once handed off, it cannot be reversed.
     *
     * @throws \InvalidArgumentException If hand-off is not allowed
     */
    public function handOffToModuleB(Inbound $inbound): Inbound
    {
        if (! $inbound->status->allowsHandOff()) {
            throw new \InvalidArgumentException(
                "Cannot hand off inbound to Module B: current status '{$inbound->status->label()}' does not allow hand-off. "
                .'Only Completed inbounds can be handed off.'
            );
        }

        if ($inbound->handed_to_module_b) {
            throw new \InvalidArgumentException(
                'Cannot hand off inbound to Module B: inbound has already been handed off.'
            );
        }

        // Validate ownership clarity
        $this->validateOwnershipClarity($inbound);

        // Validate serialization routing
        $this->validateSerializationRouting($inbound);

        return DB::transaction(function () use ($inbound): Inbound {
            $inbound->handed_to_module_b = true;
            $inbound->handed_to_module_b_at = now();
            $inbound->save();

            $this->logHandOff($inbound);

            return $inbound;
        });
    }

    /**
     * Validate that ownership has been clarified (not pending).
     *
     * @throws \InvalidArgumentException If ownership is still pending
     */
    public function validateOwnershipClarity(Inbound $inbound): void
    {
        if (! $inbound->hasOwnershipClarity()) {
            throw new \InvalidArgumentException(
                'Ownership must be clarified (owned or in_custody) before this operation. '
                .'Current ownership flag: '.$inbound->ownership_flag->label().'.'
            );
        }
    }

    /**
     * Validate serialization routing.
     *
     * Ensures that if serialization is required, an authorized location has been set.
     *
     * @throws \InvalidArgumentException If serialization routing is invalid
     */
    public function validateSerializationRouting(Inbound $inbound): void
    {
        if (! $inbound->serialization_required) {
            return;
        }

        if ($inbound->serialization_location_authorized === null) {
            throw new \InvalidArgumentException(
                'Serialization location must be specified because serialization is required for this inbound.'
            );
        }

        // In future, this could check against ProducerSupplierConfig.serialization_constraints
        // For now, we just verify that a location has been set
    }

    /**
     * Update the ownership flag for an inbound.
     *
     * @throws \InvalidArgumentException If the inbound is already completed with hand-off
     */
    public function updateOwnershipFlag(Inbound $inbound, OwnershipFlag $newFlag): Inbound
    {
        if ($inbound->handed_to_module_b) {
            throw new \InvalidArgumentException(
                'Cannot update ownership flag: inbound has already been handed off to Module B.'
            );
        }

        $oldFlag = $inbound->ownership_flag;

        return DB::transaction(function () use ($inbound, $oldFlag, $newFlag): Inbound {
            $inbound->ownership_flag = $newFlag;
            $inbound->save();

            $this->logOwnershipUpdate($inbound, $oldFlag, $newFlag);

            return $inbound;
        });
    }

    /**
     * Log the creation of an inbound.
     *
     * @param  array<string, mixed>  $data
     */
    protected function logCreation(Inbound $inbound, array $data): void
    {
        $inbound->auditLogs()->create([
            'event' => AuditLog::EVENT_CREATED,
            'new_values' => [
                'status' => $inbound->status->value,
                'warehouse' => $inbound->warehouse,
                'quantity' => $inbound->quantity,
                'packaging' => $inbound->packaging->value,
                'ownership_flag' => $inbound->ownership_flag->value,
                'serialization_required' => $inbound->serialization_required,
                'procurement_intent_id' => $inbound->procurement_intent_id,
                'purchase_order_id' => $inbound->purchase_order_id,
                'is_linked' => $inbound->isLinked(),
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log a status transition to the audit log.
     *
     * @param  array<string, mixed>  $additionalContext
     */
    protected function logStatusTransition(
        Inbound $inbound,
        InboundStatus $oldStatus,
        InboundStatus $newStatus,
        array $additionalContext = []
    ): void {
        $newValues = [
            'status' => $newStatus->value,
            'status_label' => $newStatus->label(),
        ];

        if ($additionalContext !== []) {
            $newValues['context'] = $additionalContext;
        }

        $inbound->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log the hand-off to Module B.
     */
    protected function logHandOff(Inbound $inbound): void
    {
        $inbound->auditLogs()->create([
            'event' => AuditLog::EVENT_FLAG_CHANGE,
            'old_values' => [
                'handed_to_module_b' => false,
            ],
            'new_values' => [
                'handed_to_module_b' => true,
                'handed_to_module_b_at' => $inbound->handed_to_module_b_at?->toIso8601String(),
                'ownership_flag' => $inbound->ownership_flag->value,
                'serialization_location' => $inbound->serialization_location_authorized,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log an ownership flag update.
     */
    protected function logOwnershipUpdate(
        Inbound $inbound,
        OwnershipFlag $oldFlag,
        OwnershipFlag $newFlag
    ): void {
        $inbound->auditLogs()->create([
            'event' => AuditLog::EVENT_FLAG_CHANGE,
            'old_values' => [
                'ownership_flag' => $oldFlag->value,
                'ownership_flag_label' => $oldFlag->label(),
            ],
            'new_values' => [
                'ownership_flag' => $newFlag->value,
                'ownership_flag_label' => $newFlag->label(),
            ],
            'user_id' => Auth::id(),
        ]);
    }
}
