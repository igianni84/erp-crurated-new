<?php

namespace App\Services\Procurement;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\SourcingModel;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use App\Models\Procurement\ProcurementIntent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing ProcurementIntent lifecycle.
 *
 * Centralizes all procurement intent business logic including creation
 * from various sources, state transitions, and closure validation.
 */
class ProcurementIntentService
{
    /**
     * Create a ProcurementIntent from a voucher sale.
     *
     * Creates a draft intent with trigger_type = voucher_driven.
     * The sourcing model is inferred from the allocation's source type.
     *
     * @throws InvalidArgumentException If voucher is missing required data
     */
    public function createFromVoucherSale(Voucher $voucher): ProcurementIntent
    {
        $allocation = $voucher->allocation;

        if ($allocation === null) {
            throw new InvalidArgumentException(
                'Cannot create ProcurementIntent: Voucher has no linked allocation.'
            );
        }

        // Get raw attribute values to handle nullable fields properly
        /** @var string|null $sellableSkuId */
        $sellableSkuId = $voucher->getAttribute('sellable_sku_id');
        /** @var string|null $wineVariantId */
        $wineVariantId = $voucher->getAttribute('wine_variant_id');

        // Determine product reference from voucher
        $productReferenceType = $sellableSkuId !== null
            ? 'sellable_skus'
            : 'liquid_products';

        $productReferenceId = $sellableSkuId ?? $wineVariantId;

        if ($productReferenceId === null) {
            throw new InvalidArgumentException(
                'Cannot create ProcurementIntent: Voucher has no product reference (sellable_sku_id or wine_variant_id).'
            );
        }

        // Infer sourcing model from allocation source type
        $sourcingModel = $this->inferSourcingModelFromAllocation($allocation);

        return DB::transaction(function () use ($productReferenceType, $productReferenceId, $sourcingModel, $voucher): ProcurementIntent {
            $intent = ProcurementIntent::create([
                'product_reference_type' => $productReferenceType,
                'product_reference_id' => $productReferenceId,
                'quantity' => 1, // Voucher represents 1 bottle
                'trigger_type' => ProcurementTriggerType::VoucherDriven,
                'sourcing_model' => $sourcingModel,
                'status' => ProcurementIntentStatus::Draft,
                'rationale' => "Created from voucher sale. Voucher ID: {$voucher->id}",
            ]);

            $this->logCreation($intent, [
                'source' => 'voucher_sale',
                'voucher_id' => $voucher->id,
                'allocation_id' => $voucher->allocation_id,
            ]);

            return $intent;
        });
    }

    /**
     * Create a ProcurementIntent from an allocation.
     *
     * Creates a draft intent with trigger_type = allocation_driven.
     * Useful for pre-emptive procurement based on allocation demand.
     *
     * @throws InvalidArgumentException If quantity is invalid
     */
    public function createFromAllocation(Allocation $allocation, int $quantity): ProcurementIntent
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Quantity must be greater than 0.'
            );
        }

        // Determine product reference from allocation
        // Allocations reference WineVariant + Format, which maps to a SellableSku
        $productReferenceType = $allocation->isLiquid()
            ? 'liquid_products'
            : 'sellable_skus';

        // For bottle allocations, we need the wine_variant_id (will be resolved later via lookup)
        // For liquid allocations, same approach
        /** @var string|null $productReferenceId */
        $productReferenceId = $allocation->getAttribute('wine_variant_id');

        if ($productReferenceId === null) {
            throw new InvalidArgumentException(
                'Cannot create ProcurementIntent: Allocation has no wine_variant_id.'
            );
        }

        // Infer sourcing model from allocation source type
        $sourcingModel = $this->inferSourcingModelFromAllocation($allocation);

        return DB::transaction(function () use ($productReferenceType, $productReferenceId, $quantity, $sourcingModel, $allocation): ProcurementIntent {
            $intent = ProcurementIntent::create([
                'product_reference_type' => $productReferenceType,
                'product_reference_id' => $productReferenceId,
                'quantity' => $quantity,
                'trigger_type' => ProcurementTriggerType::AllocationDriven,
                'sourcing_model' => $sourcingModel,
                'status' => ProcurementIntentStatus::Draft,
                'rationale' => "Created from allocation demand. Allocation ID: {$allocation->id}",
            ]);

            $this->logCreation($intent, [
                'source' => 'allocation',
                'allocation_id' => $allocation->id,
                'quantity' => $quantity,
            ]);

            return $intent;
        });
    }

    /**
     * Create a manual (strategic) ProcurementIntent.
     *
     * Creates a draft intent with trigger_type = strategic.
     * Used for speculative or discretionary procurement.
     *
     * @param  array<string, mixed>  $data  Required keys: product_reference_type, product_reference_id, quantity, sourcing_model. Optional: preferred_inbound_location, rationale
     *
     * @throws InvalidArgumentException If required data is missing
     */
    public function createManual(array $data): ProcurementIntent
    {
        $requiredFields = ['product_reference_type', 'product_reference_id', 'quantity', 'sourcing_model'];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                throw new InvalidArgumentException(
                    "Missing required field: {$field}"
                );
            }
        }

        /** @var int $quantity */
        $quantity = $data['quantity'];
        /** @var string $productReferenceType */
        $productReferenceType = $data['product_reference_type'];
        /** @var string $productReferenceId */
        $productReferenceId = $data['product_reference_id'];
        /** @var SourcingModel $sourcingModel */
        $sourcingModel = $data['sourcing_model'];

        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Quantity must be greater than 0.'
            );
        }

        $validProductTypes = ['sellable_skus', 'liquid_products'];
        if (! in_array($productReferenceType, $validProductTypes, true)) {
            throw new InvalidArgumentException(
                'Invalid product_reference_type. Must be one of: '.implode(', ', $validProductTypes)
            );
        }

        /** @var string|null $preferredInboundLocation */
        $preferredInboundLocation = $data['preferred_inbound_location'] ?? null;
        /** @var string|null $rationale */
        $rationale = $data['rationale'] ?? null;

        return DB::transaction(function () use ($productReferenceType, $productReferenceId, $quantity, $sourcingModel, $preferredInboundLocation, $rationale, $data): ProcurementIntent {
            $intent = ProcurementIntent::create([
                'product_reference_type' => $productReferenceType,
                'product_reference_id' => $productReferenceId,
                'quantity' => $quantity,
                'trigger_type' => ProcurementTriggerType::Strategic,
                'sourcing_model' => $sourcingModel,
                'preferred_inbound_location' => $preferredInboundLocation,
                'rationale' => $rationale,
                'status' => ProcurementIntentStatus::Draft,
            ]);

            $this->logCreation($intent, [
                'source' => 'manual',
                'data' => $data,
            ]);

            return $intent;
        });
    }

    /**
     * Approve a procurement intent (draft → approved).
     *
     * Records the approver and approval timestamp.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function approve(ProcurementIntent $intent): ProcurementIntent
    {
        if (! $intent->status->canTransitionTo(ProcurementIntentStatus::Approved)) {
            throw new InvalidArgumentException(
                "Cannot approve intent: current status '{$intent->status->label()}' does not allow transition to Approved. "
                .'Only Draft intents can be approved.'
            );
        }

        $oldStatus = $intent->status;

        $intent->status = ProcurementIntentStatus::Approved;
        $intent->approved_at = now();
        $intent->approved_by = Auth::id();
        $intent->save();

        $this->logStatusTransition($intent, $oldStatus, ProcurementIntentStatus::Approved);

        return $intent;
    }

    /**
     * Mark a procurement intent as executed (approved → executed).
     *
     * Indicates that procurement activities have begun.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function markExecuted(ProcurementIntent $intent): ProcurementIntent
    {
        if (! $intent->status->canTransitionTo(ProcurementIntentStatus::Executed)) {
            throw new InvalidArgumentException(
                "Cannot mark intent as executed: current status '{$intent->status->label()}' does not allow transition to Executed. "
                .'Only Approved intents can be marked as executed.'
            );
        }

        $oldStatus = $intent->status;

        $intent->status = ProcurementIntentStatus::Executed;
        $intent->save();

        $this->logStatusTransition($intent, $oldStatus, ProcurementIntentStatus::Executed);

        return $intent;
    }

    /**
     * Close a procurement intent (executed → closed).
     *
     * Requires all linked objects to be in completed/closed states.
     *
     * @throws InvalidArgumentException If transition is not allowed or linked objects are incomplete
     */
    public function close(ProcurementIntent $intent): ProcurementIntent
    {
        if (! $intent->status->canTransitionTo(ProcurementIntentStatus::Closed)) {
            throw new InvalidArgumentException(
                "Cannot close intent: current status '{$intent->status->label()}' does not allow transition to Closed. "
                .'Only Executed intents can be closed.'
            );
        }

        // Validate all linked objects are completed
        $validation = $this->canClose($intent);

        if (! $validation['can_close']) {
            $pendingItems = implode(', ', $validation['pending_items']);
            throw new InvalidArgumentException(
                "Cannot close intent: the following linked objects are not completed: {$pendingItems}. "
                .'All linked Purchase Orders must be closed, all Bottling Instructions must be executed, '
                .'and all Inbounds must be completed before closing the intent.'
            );
        }

        $oldStatus = $intent->status;

        $intent->status = ProcurementIntentStatus::Closed;
        $intent->save();

        $this->logStatusTransition($intent, $oldStatus, ProcurementIntentStatus::Closed);

        return $intent;
    }

    /**
     * Check if a procurement intent can be closed.
     *
     * Validates that all linked Purchase Orders, Bottling Instructions,
     * and Inbounds are in their respective completed states.
     *
     * @return array{can_close: bool, pending_items: list<string>}
     */
    public function canClose(ProcurementIntent $intent): array
    {
        $pendingItems = [];

        // Check all linked Purchase Orders are closed
        $pendingPOs = $intent->purchaseOrders()
            ->where('status', '!=', PurchaseOrderStatus::Closed->value)
            ->get();

        foreach ($pendingPOs as $po) {
            $pendingItems[] = "PO {$po->id} (status: {$po->status->label()})";
        }

        // Check all linked Bottling Instructions are executed
        $pendingBottling = $intent->bottlingInstructions()
            ->where('status', '!=', BottlingInstructionStatus::Executed->value)
            ->get();

        foreach ($pendingBottling as $instruction) {
            $pendingItems[] = "Bottling Instruction {$instruction->id} (status: {$instruction->status->label()})";
        }

        // Check all linked Inbounds are completed
        $pendingInbounds = $intent->inbounds()
            ->where('status', '!=', InboundStatus::Completed->value)
            ->get();

        foreach ($pendingInbounds as $inbound) {
            $pendingItems[] = "Inbound {$inbound->id} (status: {$inbound->status->label()})";
        }

        return [
            'can_close' => $pendingItems === [],
            'pending_items' => $pendingItems,
        ];
    }

    /**
     * Infer the sourcing model from an allocation's source type.
     */
    protected function inferSourcingModelFromAllocation(Allocation $allocation): SourcingModel
    {
        // Map allocation source types to sourcing models
        // Based on AllocationSourceType enum values
        return match ($allocation->source_type) {
            AllocationSourceType::ProducerAllocation => SourcingModel::Purchase,
            AllocationSourceType::OwnedStock => SourcingModel::Purchase,
            AllocationSourceType::PassiveConsignment => SourcingModel::PassiveConsignment,
            AllocationSourceType::ThirdPartyCustody => SourcingModel::ThirdPartyCustody,
        };
    }

    /**
     * Log the creation of a procurement intent.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logCreation(ProcurementIntent $intent, array $context): void
    {
        $intent->auditLogs()->create([
            'event' => AuditLog::EVENT_CREATED,
            'new_values' => [
                'status' => $intent->status->value,
                'trigger_type' => $intent->trigger_type->value,
                'sourcing_model' => $intent->sourcing_model->value,
                'quantity' => $intent->quantity,
                'context' => $context,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log a status transition to the audit log.
     */
    protected function logStatusTransition(
        ProcurementIntent $intent,
        ProcurementIntentStatus $oldStatus,
        ProcurementIntentStatus $newStatus
    ): void {
        $newValues = [
            'status' => $newStatus->value,
            'status_label' => $newStatus->label(),
        ];

        // Include approval info when transitioning to Approved
        if ($newStatus === ProcurementIntentStatus::Approved) {
            $newValues['approved_at'] = $intent->approved_at?->toIso8601String();
            $newValues['approved_by'] = $intent->approved_by;
        }

        $intent->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }
}
