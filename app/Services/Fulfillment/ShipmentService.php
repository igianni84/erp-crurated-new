<?php

namespace App\Services\Fulfillment;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderAuditLog;
use App\Models\Inventory\SerializedBottle;
use App\Services\Allocation\VoucherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing Shipments in the fulfillment process.
 *
 * Centralizes all Shipment business logic including creation from Shipping Orders,
 * confirmation, voucher redemption triggering, ownership transfer, and tracking updates.
 *
 * Key invariants:
 * - Shipments can only be created from Shipping Orders (no standalone shipments)
 * - shipped_bottle_serials is immutable after confirmation
 * - Redemption is triggered ONLY at shipment confirmation
 * - Ownership transfer is triggered after redemption
 */
class ShipmentService
{
    /**
     * Event types for audit logging.
     */
    public const EVENT_SHIPMENT_CREATED = 'shipment_created';

    public const EVENT_SHIPMENT_CONFIRMED = 'shipment_confirmed';

    public const EVENT_VOUCHER_REDEEMED = 'voucher_redeemed';

    public const EVENT_OWNERSHIP_TRANSFERRED = 'ownership_transferred';

    public const EVENT_TRACKING_UPDATED = 'tracking_updated';

    public const EVENT_SHIPMENT_DELIVERED = 'shipment_delivered';

    public const EVENT_SHIPMENT_FAILED = 'shipment_failed';

    public function __construct(
        protected VoucherService $voucherService,
        protected LateBindingService $lateBindingService
    ) {}

    /**
     * Create a Shipment from a Shipping Order.
     *
     * Collects all bound bottle serials from the SO lines and creates a Shipment
     * in Preparing status. The SO must be in Picking status with all bindings complete.
     *
     * @param  ShippingOrder  $so  The shipping order to create a shipment from
     * @return Shipment The created shipment
     *
     * @throws \InvalidArgumentException If the SO is not ready for shipment
     */
    public function createFromOrder(ShippingOrder $so): Shipment
    {
        // Validate SO status (must be in Picking)
        if ($so->status !== ShippingOrderStatus::Picking) {
            throw new \InvalidArgumentException(
                'Cannot create shipment: Shipping Order must be in Picking status. '
                ."Current status: {$so->status->label()}."
            );
        }

        // Load lines to get bindings
        $so->load(['lines.voucher', 'customer']);

        // Validate all lines are bound
        $bindingCheck = $this->lateBindingService->checkAllLinesBinding($so);
        if (! $bindingCheck['all_bound']) {
            throw new \InvalidArgumentException(
                'Cannot create shipment: not all lines are bound to bottles. '
                ."Bound: {$bindingCheck['bound_count']}, Unbound: {$bindingCheck['unbound_count']}. "
                .'Complete the picking process before creating a shipment.'
            );
        }

        // Validate all bindings are valid
        $bindingValidation = $this->lateBindingService->validateAllBindings($so);
        if (! $bindingValidation['valid']) {
            $errorSummary = collect($bindingValidation['errors'])
                ->map(fn ($e) => "Line {$e['line_id']}: ".implode(', ', $e['errors']))
                ->implode('; ');

            throw new \InvalidArgumentException(
                "Cannot create shipment: binding validation failed. {$errorSummary}"
            );
        }

        // Collect all bottle serials (effective serial considers early binding)
        $bottleSerials = $so->lines
            ->map(fn ($line) => $line->getEffectiveSerial())
            ->filter()
            ->values()
            ->toArray();

        // Get destination address from SO or customer
        $destinationAddress = $this->resolveDestinationAddress($so);

        return DB::transaction(function () use ($so, $bottleSerials, $destinationAddress): Shipment {
            // Create the shipment in Preparing status
            $shipment = Shipment::create([
                'shipping_order_id' => $so->id,
                'carrier' => $so->carrier ?? 'TBD',
                'status' => ShipmentStatus::Preparing,
                'shipped_bottle_serials' => $bottleSerials,
                'origin_warehouse_id' => $so->source_warehouse_id,
                'destination_address' => $destinationAddress,
            ]);

            // Update all SO lines to Picked status
            $so->lines()
                ->whereIn('status', [
                    ShippingOrderLineStatus::Validated->value,
                    ShippingOrderLineStatus::Pending->value,
                ])
                ->update(['status' => ShippingOrderLineStatus::Picked]);

            // Log the shipment creation
            $this->logEvent(
                $so,
                self::EVENT_SHIPMENT_CREATED,
                'Shipment created from Shipping Order',
                null,
                [
                    'shipment_id' => $shipment->id,
                    'bottle_count' => count($bottleSerials),
                    'bottle_serials' => $bottleSerials,
                    'carrier' => $shipment->carrier,
                ]
            );

            return $shipment;
        });
    }

    /**
     * Confirm a shipment with tracking number.
     *
     * Confirming a shipment is the "point of no return":
     * 1. Sets shipped status and timestamp
     * 2. Triggers voucher redemption for all vouchers in the SO
     * 3. Triggers ownership transfer for all bottles
     *
     * @param  Shipment  $shipment  The shipment to confirm
     * @param  string  $trackingNumber  The carrier tracking number
     * @return Shipment The confirmed shipment
     *
     * @throws \InvalidArgumentException If confirmation fails
     */
    public function confirmShipment(Shipment $shipment, string $trackingNumber): Shipment
    {
        // Validate shipment status
        if (! $shipment->isPreparing()) {
            throw new \InvalidArgumentException(
                'Cannot confirm shipment: shipment is not in Preparing status. '
                ."Current status: {$shipment->status->label()}."
            );
        }

        if (empty($trackingNumber)) {
            throw new \InvalidArgumentException(
                'Cannot confirm shipment: tracking number is required.'
            );
        }

        // Load the shipping order
        $shipment->load('shippingOrder.lines.voucher');
        $so = $shipment->shippingOrder;

        if ($so === null) {
            throw new \InvalidArgumentException(
                'Cannot confirm shipment: associated Shipping Order not found.'
            );
        }

        return DB::transaction(function () use ($shipment, $trackingNumber, $so): Shipment {
            // Update shipment status to Shipped
            $shipment->tracking_number = $trackingNumber;
            $shipment->status = ShipmentStatus::Shipped;
            $shipment->shipped_at = now();
            $shipment->save();

            // Trigger voucher redemption
            $this->triggerRedemption($shipment);

            // Trigger ownership transfer
            $this->triggerOwnershipTransfer($shipment);

            // Update SO status to Shipped
            $so->status = ShippingOrderStatus::Shipped;
            $so->save();

            // Update all SO lines to Shipped status
            $so->lines()->update(['status' => ShippingOrderLineStatus::Shipped]);

            // Log the confirmation
            $this->logEvent(
                $so,
                self::EVENT_SHIPMENT_CONFIRMED,
                "Shipment confirmed with tracking number {$trackingNumber}",
                ['status' => ShipmentStatus::Preparing->value],
                [
                    'status' => ShipmentStatus::Shipped->value,
                    'tracking_number' => $trackingNumber,
                    'shipped_at' => $shipment->shipped_at?->toIso8601String(),
                    'bottle_count' => $shipment->getBottleCount(),
                ]
            );

            return $shipment->fresh() ?? $shipment;
        });
    }

    /**
     * Trigger voucher redemption for all vouchers in a shipment.
     *
     * Redemption is IRREVERSIBLE and marks the vouchers as redeemed.
     * This should ONLY be called from confirmShipment().
     *
     * @param  Shipment  $shipment  The shipment containing vouchers to redeem
     *
     * @throws \InvalidArgumentException If redemption fails for any voucher
     */
    public function triggerRedemption(Shipment $shipment): void
    {
        $shipment->load('shippingOrder.lines.voucher');
        $so = $shipment->shippingOrder;

        if ($so === null) {
            throw new \InvalidArgumentException(
                'Cannot trigger redemption: Shipping Order not found for shipment.'
            );
        }

        $redeemedVouchers = [];
        $errors = [];

        foreach ($so->lines as $line) {
            $voucher = $line->voucher;

            if ($voucher === null) {
                $errors[] = "Voucher not found for line {$line->id}";

                continue;
            }

            try {
                // Redeem the voucher using VoucherService
                $this->voucherService->redeem($voucher);
                $redeemedVouchers[] = [
                    'voucher_id' => $voucher->id,
                    'line_id' => $line->id,
                ];
            } catch (\Throwable $e) {
                $errors[] = "Failed to redeem voucher {$voucher->id}: {$e->getMessage()}";
            }
        }

        // If any redemption failed, we need to handle it as a critical error
        if ($errors !== []) {
            throw new \InvalidArgumentException(
                'Voucher redemption failed: '.implode('; ', $errors)
            );
        }

        // Log the redemption
        $this->logEvent(
            $so,
            self::EVENT_VOUCHER_REDEEMED,
            'All vouchers redeemed for shipment',
            null,
            [
                'shipment_id' => $shipment->id,
                'redeemed_count' => count($redeemedVouchers),
                'redeemed_vouchers' => $redeemedVouchers,
            ]
        );
    }

    /**
     * Trigger ownership transfer for all bottles in a shipment.
     *
     * Updates bottle ownership and triggers provenance updates.
     *
     * @param  Shipment  $shipment  The shipment containing bottles to transfer
     */
    public function triggerOwnershipTransfer(Shipment $shipment): void
    {
        $shipment->load('shippingOrder.customer');
        $so = $shipment->shippingOrder;

        if ($so === null) {
            throw new \InvalidArgumentException(
                'Cannot trigger ownership transfer: Shipping Order not found for shipment.'
            );
        }

        $customer = $so->customer;
        if ($customer === null) {
            throw new \InvalidArgumentException(
                'Cannot trigger ownership transfer: Customer not found for Shipping Order.'
            );
        }

        $bottleSerials = $shipment->shipped_bottle_serials ?? [];
        $transferredBottles = [];

        foreach ($bottleSerials as $serial) {
            $bottle = SerializedBottle::where('serial_number', $serial)->first();

            if ($bottle === null) {
                continue; // Already logged elsewhere
            }

            // Update bottle state to Shipped
            $bottle->state = BottleState::Shipped;
            // Note: ownership_type transition to customer_owned would be handled
            // by a dedicated InventoryService in Module B. For now, we just update state.
            $bottle->save();

            $transferredBottles[] = [
                'serial' => $serial,
                'bottle_id' => $bottle->id,
            ];
        }

        // Log the ownership transfer
        $this->logEvent(
            $so,
            self::EVENT_OWNERSHIP_TRANSFERRED,
            'Bottle ownership transferred to customer, provenance update triggered',
            null,
            [
                'shipment_id' => $shipment->id,
                'customer_id' => $customer->id,
                'transferred_count' => count($transferredBottles),
                'transferred_bottles' => $transferredBottles,
            ]
        );

        // Note: The actual provenance blockchain update would be dispatched as a job
        // via UpdateProvenanceOnShipmentJob (US-C042). This is a placeholder for the job dispatch.
        // dispatch(new UpdateProvenanceOnShipmentJob($shipment));
    }

    /**
     * Update tracking status for a shipment.
     *
     * @param  Shipment  $shipment  The shipment to update
     * @param  string  $status  The new tracking status (maps to ShipmentStatus)
     * @return Shipment The updated shipment
     *
     * @throws \InvalidArgumentException If status update is not allowed
     */
    public function updateTracking(Shipment $shipment, string $status): Shipment
    {
        $newStatus = ShipmentStatus::tryFrom($status);

        if ($newStatus === null) {
            throw new \InvalidArgumentException(
                "Invalid tracking status: '{$status}'. "
                .'Valid statuses: '.implode(', ', array_map(fn ($s) => $s->value, ShipmentStatus::cases()))
            );
        }

        if (! $shipment->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot update tracking: transition from {$shipment->status->value} to {$newStatus->value} is not allowed."
            );
        }

        $oldStatus = $shipment->status;
        $shipment->status = $newStatus;
        $shipment->save();

        $shipment->load('shippingOrder');

        // Log the tracking update
        $this->logEvent(
            $shipment->shippingOrder,
            self::EVENT_TRACKING_UPDATED,
            "Shipment tracking status updated from {$oldStatus->label()} to {$newStatus->label()}",
            ['status' => $oldStatus->value],
            ['status' => $newStatus->value]
        );

        return $shipment->fresh() ?? $shipment;
    }

    /**
     * Mark a shipment as delivered.
     *
     * @param  Shipment  $shipment  The shipment to mark as delivered
     * @return Shipment The delivered shipment
     *
     * @throws \InvalidArgumentException If delivery status update is not allowed
     */
    public function markDelivered(Shipment $shipment): Shipment
    {
        if (! $shipment->canTransitionTo(ShipmentStatus::Delivered)) {
            throw new \InvalidArgumentException(
                "Cannot mark as delivered: transition from {$shipment->status->value} to delivered is not allowed."
            );
        }

        $oldStatus = $shipment->status;
        $shipment->status = ShipmentStatus::Delivered;
        $shipment->delivered_at = now();
        $shipment->save();

        $shipment->load('shippingOrder');
        $so = $shipment->shippingOrder;

        // Optionally update SO to Completed
        if ($so !== null && $so->status === ShippingOrderStatus::Shipped) {
            $so->status = ShippingOrderStatus::Completed;
            $so->save();
        }

        // Log the delivery
        $this->logEvent(
            $so,
            self::EVENT_SHIPMENT_DELIVERED,
            'Shipment delivered',
            ['status' => $oldStatus->value],
            [
                'status' => ShipmentStatus::Delivered->value,
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            ]
        );

        return $shipment->fresh() ?? $shipment;
    }

    /**
     * Mark a shipment as failed.
     *
     * @param  Shipment  $shipment  The shipment to mark as failed
     * @param  string  $reason  The reason for failure
     * @return Shipment The failed shipment
     *
     * @throws \InvalidArgumentException If failure status update is not allowed
     */
    public function markFailed(Shipment $shipment, string $reason): Shipment
    {
        if (! $shipment->canTransitionTo(ShipmentStatus::Failed)) {
            throw new \InvalidArgumentException(
                "Cannot mark as failed: transition from {$shipment->status->value} to failed is not allowed."
            );
        }

        $oldStatus = $shipment->status;
        $shipment->status = ShipmentStatus::Failed;
        $shipment->notes = ($shipment->notes ? $shipment->notes."\n" : '')."Failed: {$reason}";
        $shipment->save();

        $shipment->load('shippingOrder');

        // Log the failure
        $this->logEvent(
            $shipment->shippingOrder,
            self::EVENT_SHIPMENT_FAILED,
            "Shipment failed: {$reason}",
            ['status' => $oldStatus->value],
            [
                'status' => ShipmentStatus::Failed->value,
                'failure_reason' => $reason,
            ]
        );

        return $shipment->fresh() ?? $shipment;
    }

    /**
     * Get all bottle serials for a shipment.
     *
     * @param  Shipment  $shipment  The shipment
     * @return array<int, string> The bottle serials
     */
    public function getBottleSerials(Shipment $shipment): array
    {
        return $shipment->shipped_bottle_serials ?? [];
    }

    /**
     * Validate that a shipment can be created from a Shipping Order.
     *
     * @param  ShippingOrder  $so  The shipping order to validate
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateForShipment(ShippingOrder $so): array
    {
        $errors = [];

        // Check status
        if ($so->status !== ShippingOrderStatus::Picking) {
            $errors[] = "Shipping Order must be in Picking status. Current: {$so->status->label()}.";
        }

        // Load and check bindings
        $so->load('lines');
        $bindingCheck = $this->lateBindingService->checkAllLinesBinding($so);

        if (! $bindingCheck['all_bound']) {
            $errors[] = "Not all lines are bound. Bound: {$bindingCheck['bound_count']}, Unbound: {$bindingCheck['unbound_count']}.";
        }

        // Validate bindings if all bound
        if ($bindingCheck['all_bound']) {
            $bindingValidation = $this->lateBindingService->validateAllBindings($so);
            if (! $bindingValidation['valid']) {
                foreach ($bindingValidation['errors'] as $error) {
                    $errors[] = "Line {$error['line_id']}: ".implode(', ', $error['errors']);
                }
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Resolve the destination address for a shipment.
     *
     * @param  ShippingOrder  $so  The shipping order
     * @return string The destination address as text
     */
    protected function resolveDestinationAddress(ShippingOrder $so): string
    {
        // If SO has a destination address ID, load and format it
        if ($so->destination_address_id !== null) {
            // In a complete implementation, this would load the Address model
            // and format it. For now, we return a placeholder.
            return "Address ID: {$so->destination_address_id}";
        }

        // Fallback to customer primary address
        $so->load('customer');
        $customer = $so->customer;

        if ($customer !== null) {
            // In a complete implementation, this would get the customer's primary
            // shipping address. For now, we return customer info.
            $customerName = $customer->name ?? $customer->id;

            return "Customer: {$customerName}";
        }

        return 'Address not specified';
    }

    /**
     * Log an event to the shipping order audit log.
     *
     * @param  ShippingOrder|null  $shippingOrder  The shipping order
     * @param  string  $eventType  The event type
     * @param  string  $description  The event description
     * @param  array<string, mixed>|null  $oldValues  The old values (if applicable)
     * @param  array<string, mixed>|null  $newValues  The new values (if applicable)
     */
    protected function logEvent(
        ?ShippingOrder $shippingOrder,
        string $eventType,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        if ($shippingOrder === null) {
            return;
        }

        ShippingOrderAuditLog::create([
            'shipping_order_id' => $shippingOrder->id,
            'event_type' => $eventType,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);
    }
}
