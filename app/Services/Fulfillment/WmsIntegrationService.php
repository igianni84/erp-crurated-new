<?php

namespace App\Services\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderAuditLog;
use App\Models\Fulfillment\ShippingOrderException;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\Inventory\SerializedBottle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing WMS (Warehouse Management System) integration.
 *
 * Handles bidirectional communication with WMS:
 * - Outbound: Send picking instructions when SO moves to picking
 * - Inbound: Receive picking feedback with picked serials
 * - Validation: Validate picked serials against allocation lineage
 * - Confirmation: Process shipment confirmation from WMS
 * - Discrepancies: Handle and record WMS discrepancies
 *
 * Key invariants:
 * - Picked serials MUST match allocation lineage (hard constraint)
 * - Invalid picks create WmsDiscrepancy exceptions
 * - No manual acceptance of invalid picks is allowed
 */
class WmsIntegrationService
{
    /**
     * Event types for audit logging.
     */
    public const EVENT_WMS_INSTRUCTIONS_SENT = 'wms_instructions_sent';

    public const EVENT_WMS_FEEDBACK_RECEIVED = 'wms_feedback_received';

    public const EVENT_WMS_SERIAL_VALIDATED = 'wms_serial_validated';

    public const EVENT_WMS_SERIAL_INVALID = 'wms_serial_invalid';

    public const EVENT_WMS_SHIPMENT_CONFIRMED = 'wms_shipment_confirmed';

    public const EVENT_WMS_DISCREPANCY = 'wms_discrepancy';

    public const EVENT_WMS_RE_PICK_REQUESTED = 'wms_re_pick_requested';

    public function __construct(
        protected LateBindingService $lateBindingService,
        protected ShipmentService $shipmentService
    ) {}

    /**
     * Send picking instructions to WMS.
     *
     * Called when SO transitions to picking status. Sends a structured payload
     * containing all line information needed for WMS to execute the pick.
     *
     * @param  ShippingOrder  $so  The shipping order to send instructions for
     * @return array{success: bool, message_id: string, payload: array<string, mixed>}
     *
     * @throws \InvalidArgumentException If SO is not in valid state
     */
    public function sendPickingInstructions(ShippingOrder $so): array
    {
        // Validate SO status
        if ($so->status !== ShippingOrderStatus::Picking && $so->status !== ShippingOrderStatus::Planned) {
            throw new \InvalidArgumentException(
                'Cannot send picking instructions: Shipping Order must be in Planned or Picking status. '
                ."Current status: {$so->status->label()}."
            );
        }

        $so->load(['lines.voucher.allocation', 'lines.allocation', 'customer']);

        // Build picking instructions payload
        $payload = $this->buildPickingPayload($so);

        // In a real implementation, this would send to WMS via API/message queue.
        // For now, we simulate a successful send and return a message ID.
        $messageId = 'WMS-'.now()->format('YmdHis').'-'.$so->id;

        // Log the WMS message
        $this->logEvent(
            $so,
            self::EVENT_WMS_INSTRUCTIONS_SENT,
            "Picking instructions sent to WMS (message ID: {$messageId})",
            null,
            [
                'message_id' => $messageId,
                'warehouse_id' => $so->source_warehouse_id,
                'line_count' => count($payload['lines']),
                'early_binding_count' => collect($payload['lines'])->where('binding_type', 'early')->count(),
                'late_binding_count' => collect($payload['lines'])->where('binding_type', 'late')->count(),
            ]
        );

        return [
            'success' => true,
            'message_id' => $messageId,
            'payload' => $payload,
        ];
    }

    /**
     * Receive and process picking feedback from WMS.
     *
     * Processes the feedback containing picked serials from WMS.
     * For each serial, validates against allocation lineage and executes binding.
     *
     * @param  array<int, array<string, mixed>>  $pickedSerials  Array of picked serial data (expects line_id and serial_number keys)
     * @param  ShippingOrder  $so  The shipping order
     * @return array{success: bool, bound_count: int, discrepancy_count: int, details: list<array{line_id: string, serial: string, status: string, error?: string}>}
     *
     * @throws \InvalidArgumentException If SO is not in picking status
     */
    public function receivePickingFeedback(array $pickedSerials, ShippingOrder $so): array
    {
        // Validate SO status
        if ($so->status !== ShippingOrderStatus::Picking) {
            throw new \InvalidArgumentException(
                'Cannot receive picking feedback: Shipping Order must be in Picking status. '
                ."Current status: {$so->status->label()}."
            );
        }

        $so->load('lines.voucher');

        $boundCount = 0;
        $discrepancyCount = 0;
        $details = [];

        return DB::transaction(function () use ($pickedSerials, $so, &$boundCount, &$discrepancyCount, &$details): array {
            foreach ($pickedSerials as $pickedData) {
                $lineId = isset($pickedData['line_id']) && is_string($pickedData['line_id'])
                    ? $pickedData['line_id']
                    : null;
                $serialNumber = isset($pickedData['serial_number']) && is_string($pickedData['serial_number'])
                    ? $pickedData['serial_number']
                    : null;

                if ($lineId === null || $serialNumber === null) {
                    $discrepancyCount++;
                    $details[] = [
                        'line_id' => $lineId !== null ? $lineId : 'unknown',
                        'serial' => $serialNumber !== null ? $serialNumber : 'unknown',
                        'status' => 'invalid',
                        'error' => 'Missing line_id or serial_number in feedback',
                    ];

                    continue;
                }

                // Find the line
                $line = $so->lines->firstWhere('id', $lineId);
                if ($line === null) {
                    $discrepancyCount++;
                    $details[] = [
                        'line_id' => $lineId,
                        'serial' => $serialNumber,
                        'status' => 'invalid',
                        'error' => 'Line not found in Shipping Order',
                    ];

                    continue;
                }

                // Skip if line has early binding (already bound)
                if ($line->hasEarlyBinding()) {
                    $details[] = [
                        'line_id' => $lineId,
                        'serial' => $serialNumber,
                        'status' => 'skipped',
                        'error' => 'Line has early binding - using pre-bound serial',
                    ];

                    continue;
                }

                // Skip if already bound
                if ($line->isBound()) {
                    $details[] = [
                        'line_id' => $lineId,
                        'serial' => $serialNumber,
                        'status' => 'skipped',
                        'error' => 'Line is already bound',
                    ];

                    continue;
                }

                // Validate the serial
                $validation = $this->validateSerial($serialNumber, $line);

                if (! $validation['valid']) {
                    $discrepancyCount++;
                    $details[] = [
                        'line_id' => $lineId,
                        'serial' => $serialNumber,
                        'status' => 'invalid',
                        'error' => $validation['error'],
                    ];

                    // Create discrepancy exception
                    $this->handleDiscrepancy([
                        'type' => 'invalid_serial',
                        'line_id' => $lineId,
                        'serial_number' => $serialNumber,
                        'expected_allocation' => $line->allocation_id,
                        'error' => $validation['error'],
                    ], $so);

                    continue;
                }

                // Execute binding
                try {
                    // Transition line to validated status if still pending
                    if ($line->status === ShippingOrderLineStatus::Pending) {
                        $line->status = ShippingOrderLineStatus::Validated;
                        $line->save();
                        $line->refresh();
                    }

                    $this->lateBindingService->bindVoucherToBottle($line, $serialNumber);
                    $boundCount++;
                    $details[] = [
                        'line_id' => $lineId,
                        'serial' => $serialNumber,
                        'status' => 'bound',
                    ];
                } catch (\Throwable $e) {
                    $discrepancyCount++;
                    $details[] = [
                        'line_id' => $lineId,
                        'serial' => $serialNumber,
                        'status' => 'binding_failed',
                        'error' => $e->getMessage(),
                    ];

                    $this->handleDiscrepancy([
                        'type' => 'binding_failed',
                        'line_id' => $lineId,
                        'serial_number' => $serialNumber,
                        'error' => $e->getMessage(),
                    ], $so);
                }
            }

            // Log the feedback reception
            $this->logEvent(
                $so,
                self::EVENT_WMS_FEEDBACK_RECEIVED,
                "WMS picking feedback received: {$boundCount} bound, {$discrepancyCount} discrepancies",
                null,
                [
                    'bound_count' => $boundCount,
                    'discrepancy_count' => $discrepancyCount,
                    'total_received' => count($pickedSerials),
                    'details' => $details,
                ]
            );

            return [
                'success' => $discrepancyCount === 0,
                'bound_count' => $boundCount,
                'discrepancy_count' => $discrepancyCount,
                'details' => $details,
            ];
        });
    }

    /**
     * Validate serials received from WMS against allocation lineage.
     *
     * Validates multiple serials in bulk without executing binding.
     * Useful for pre-validation before processing.
     *
     * @param  array<int, string>  $serials  Array of serial numbers to validate
     * @param  ShippingOrder  $so  The shipping order
     * @return array{valid: bool, results: array<string, array{valid: bool, allocation_id?: string, error?: string}>}
     */
    public function validateSerials(array $serials, ShippingOrder $so): array
    {
        $so->load('lines.allocation');

        $results = [];
        $allValid = true;

        foreach ($serials as $serial) {
            // Find the bottle
            $bottle = SerializedBottle::where('serial_number', $serial)->first();

            if ($bottle === null) {
                $results[$serial] = [
                    'valid' => false,
                    'error' => 'Serial number not found in inventory',
                ];
                $allValid = false;

                continue;
            }

            // Check if bottle allocation matches any line in the SO
            $matchingLine = $so->lines->firstWhere('allocation_id', $bottle->allocation_id);

            if ($matchingLine === null) {
                $results[$serial] = [
                    'valid' => false,
                    'allocation_id' => $bottle->allocation_id,
                    'error' => 'Serial allocation does not match any line in Shipping Order. Cross-allocation substitution not allowed.',
                ];
                $allValid = false;

                continue;
            }

            // Check bottle state
            if ($bottle->state !== BottleState::Stored && $bottle->state !== BottleState::ReservedForPicking) {
                $results[$serial] = [
                    'valid' => false,
                    'allocation_id' => $bottle->allocation_id,
                    'error' => "Bottle is in '{$bottle->state->label()}' state, expected Stored or Reserved for Picking",
                ];
                $allValid = false;

                continue;
            }

            // Check bottle is not destroyed/missing
            if ($bottle->state->isTerminal()) {
                $results[$serial] = [
                    'valid' => false,
                    'allocation_id' => $bottle->allocation_id,
                    'error' => "Bottle is in terminal state '{$bottle->state->label()}' and cannot be fulfilled",
                ];
                $allValid = false;

                continue;
            }

            // Valid
            $results[$serial] = [
                'valid' => true,
                'allocation_id' => $bottle->allocation_id,
            ];

            $this->logEvent(
                $so,
                self::EVENT_WMS_SERIAL_VALIDATED,
                "Serial {$serial} validated for allocation {$bottle->allocation_id}",
                null,
                [
                    'serial' => $serial,
                    'allocation_id' => $bottle->allocation_id,
                    'bottle_state' => $bottle->state->value,
                ]
            );
        }

        return [
            'valid' => $allValid,
            'results' => $results,
        ];
    }

    /**
     * Receive shipment confirmation from WMS.
     *
     * Processes the shipment confirmation, validates all expected serials are included,
     * and triggers the shipment flow (redemption, ownership transfer).
     *
     * @param  Shipment  $shipment  The shipment to confirm
     * @param  string  $trackingNumber  The carrier tracking number
     * @param  array<int, string>  $shippedSerials  The serials confirmed as shipped by WMS
     * @return array{success: bool, shipment: Shipment, warnings: list<string>}
     *
     * @throws \InvalidArgumentException If confirmation validation fails
     */
    public function confirmShipment(Shipment $shipment, string $trackingNumber, array $shippedSerials): array
    {
        $shipment->load('shippingOrder.lines');
        $so = $shipment->shippingOrder;

        if ($so === null) {
            throw new \InvalidArgumentException(
                'Cannot confirm shipment: Shipping Order not found for shipment.'
            );
        }

        // Get expected serials
        $expectedSerials = $so->lines
            ->map(fn ($line) => $line->getEffectiveSerial())
            ->filter()
            ->values()
            ->toArray();

        $warnings = [];

        // Check for missing serials (partial shipment not supported in MVP)
        $missingSerials = array_diff($expectedSerials, $shippedSerials);
        if (! empty($missingSerials)) {
            // Create exception for partial shipment
            ShippingOrderException::create([
                'shipping_order_id' => $so->id,
                'exception_type' => ShippingOrderExceptionType::WmsDiscrepancy,
                'description' => 'Partial shipment detected. Expected serials not included in WMS confirmation: '
                    .implode(', ', $missingSerials)
                    .'. Partial shipments are not supported in MVP.',
                'status' => ShippingOrderExceptionStatus::Active,
                'created_by' => Auth::id(),
            ]);

            throw new \InvalidArgumentException(
                'Cannot confirm shipment: partial shipment detected. Expected '
                .count($expectedSerials).' serials, received '.count($shippedSerials)
                .'. Missing: '.implode(', ', $missingSerials)
            );
        }

        // Check for extra serials (unexpected)
        $extraSerials = array_diff($shippedSerials, $expectedSerials);
        if (! empty($extraSerials)) {
            $warnings[] = 'Extra serials in confirmation not in expected list: '.implode(', ', $extraSerials);
        }

        // Validate all shipped serials belong to this SO
        $validationResult = $this->validateSerials($shippedSerials, $so);
        if (! $validationResult['valid']) {
            $invalidSerials = array_keys(array_filter(
                $validationResult['results'],
                fn ($r) => ! $r['valid']
            ));

            throw new \InvalidArgumentException(
                'Cannot confirm shipment: some shipped serials failed validation: '
                .implode(', ', $invalidSerials)
            );
        }

        // All validations passed - confirm the shipment
        // WMS confirmation implicitly confirms case breaking (bottles already picked)
        $confirmedShipment = $this->shipmentService->confirmShipment($shipment, $trackingNumber, caseBreakConfirmed: true);

        // Log the WMS confirmation
        $this->logEvent(
            $so,
            self::EVENT_WMS_SHIPMENT_CONFIRMED,
            "WMS shipment confirmation processed for tracking {$trackingNumber}",
            null,
            [
                'shipment_id' => $shipment->id,
                'tracking_number' => $trackingNumber,
                'shipped_serial_count' => count($shippedSerials),
                'expected_serial_count' => count($expectedSerials),
                'warnings' => $warnings,
            ]
        );

        return [
            'success' => true,
            'shipment' => $confirmedShipment,
            'warnings' => $warnings,
        ];
    }

    /**
     * Handle a discrepancy reported from WMS or during validation.
     *
     * Creates an exception record and logs the discrepancy for resolution.
     * No automatic resolution or acceptance of invalid picks is allowed.
     *
     * @param  array<string, mixed>  $discrepancy  The discrepancy details (expects type, optionally line_id, serial_number, expected_allocation, error)
     * @param  ShippingOrder  $so  The shipping order
     * @return ShippingOrderException The created exception
     */
    public function handleDiscrepancy(array $discrepancy, ShippingOrder $so): ShippingOrderException
    {
        $type = isset($discrepancy['type']) && is_string($discrepancy['type']) ? $discrepancy['type'] : 'unknown';
        $lineId = isset($discrepancy['line_id']) && is_string($discrepancy['line_id']) ? $discrepancy['line_id'] : null;
        $serialNumber = isset($discrepancy['serial_number']) && is_string($discrepancy['serial_number'])
            ? $discrepancy['serial_number']
            : 'unknown';
        $expectedAllocation = isset($discrepancy['expected_allocation']) && is_string($discrepancy['expected_allocation'])
            ? $discrepancy['expected_allocation']
            : null;
        $error = isset($discrepancy['error']) && is_string($discrepancy['error'])
            ? $discrepancy['error']
            : 'Unknown error';

        // Build description
        $description = "WMS Discrepancy ({$type}): Serial {$serialNumber}. ";
        if ($expectedAllocation !== null) {
            $description .= "Expected allocation: {$expectedAllocation}. ";
        }
        $description .= "Error: {$error}";

        // Resolution paths
        $resolutionPaths = [
            'Request WMS re-pick for correct bottle',
            'Cancel Shipping Order and create new SO',
            'Manual reconciliation by admin',
        ];

        // Create the exception
        $exception = ShippingOrderException::create([
            'shipping_order_id' => $so->id,
            'shipping_order_line_id' => $lineId,
            'exception_type' => ShippingOrderExceptionType::WmsDiscrepancy,
            'description' => $description,
            'resolution_path' => implode("\n", $resolutionPaths),
            'status' => ShippingOrderExceptionStatus::Active,
            'created_by' => Auth::id(),
        ]);

        // Log the discrepancy
        $this->logEvent(
            $so,
            self::EVENT_WMS_DISCREPANCY,
            $description,
            null,
            [
                'exception_id' => $exception->id,
                'discrepancy_type' => $type,
                'line_id' => $lineId,
                'serial_number' => $serialNumber,
                'expected_allocation' => $expectedAllocation,
                'error' => $error,
            ]
        );

        return $exception;
    }

    /**
     * Request a re-pick from WMS for a specific line.
     *
     * Used when a discrepancy is detected and operator requests WMS to pick again.
     *
     * @param  ShippingOrderLine  $line  The line to re-pick
     * @param  string  $reason  The reason for re-pick request
     * @return array{success: bool, message_id: string}
     */
    public function requestRePick(ShippingOrderLine $line, string $reason): array
    {
        $line->load('shippingOrder');
        $so = $line->shippingOrder;

        if ($so === null) {
            throw new \InvalidArgumentException('Cannot request re-pick: Shipping Order not found for line.');
        }

        // Unbind the line if currently bound
        if ($line->isBound()) {
            $this->lateBindingService->unbindLine($line);
            $line->refresh();
        }

        // Reset line status to validated
        if ($line->status !== ShippingOrderLineStatus::Validated) {
            $line->status = ShippingOrderLineStatus::Validated;
            $line->save();
        }

        // Generate re-pick message ID
        $messageId = 'WMS-REPICK-'.now()->format('YmdHis').'-'.$line->id;

        // Log the re-pick request
        $this->logEvent(
            $so,
            self::EVENT_WMS_RE_PICK_REQUESTED,
            "Re-pick requested for line {$line->id}: {$reason}",
            null,
            [
                'message_id' => $messageId,
                'line_id' => $line->id,
                'voucher_id' => $line->voucher_id,
                'allocation_id' => $line->allocation_id,
                'reason' => $reason,
            ]
        );

        // In a real implementation, this would send a re-pick request to WMS.
        // The payload would include the line details and allocation constraint.

        return [
            'success' => true,
            'message_id' => $messageId,
        ];
    }

    /**
     * Check if all expected serials have been received from WMS.
     *
     * @param  ShippingOrder  $so  The shipping order to check
     * @return array{all_received: bool, received_count: int, pending_count: int, pending_lines: list<string>}
     */
    public function checkPickingCompletion(ShippingOrder $so): array
    {
        $so->load('lines');

        $receivedCount = 0;
        $pendingCount = 0;
        $pendingLines = [];

        foreach ($so->lines as $line) {
            // Early binding counts as received
            if ($line->hasEarlyBinding()) {
                $receivedCount++;

                continue;
            }

            // Late binding - check if bound
            if ($line->isBound()) {
                $receivedCount++;
            } else {
                $pendingCount++;
                $pendingLines[] = $line->id;
            }
        }

        return [
            'all_received' => $pendingCount === 0,
            'received_count' => $receivedCount,
            'pending_count' => $pendingCount,
            'pending_lines' => $pendingLines,
        ];
    }

    /**
     * Validate a single serial against a shipping order line.
     *
     * @param  string  $serialNumber  The serial number to validate
     * @param  ShippingOrderLine  $line  The line to validate against
     * @return array{valid: bool, error?: string}
     */
    protected function validateSerial(string $serialNumber, ShippingOrderLine $line): array
    {
        // Find the bottle
        $bottle = SerializedBottle::where('serial_number', $serialNumber)->first();

        if ($bottle === null) {
            return [
                'valid' => false,
                'error' => "Serial '{$serialNumber}' not found in inventory",
            ];
        }

        // Validate allocation lineage match (HARD constraint)
        if ($bottle->allocation_id !== $line->allocation_id) {
            return [
                'valid' => false,
                'error' => 'Allocation lineage mismatch. Cross-allocation substitution not allowed. '
                    ."Expected allocation: {$line->allocation_id}, Bottle allocation: {$bottle->allocation_id}",
            ];
        }

        // Validate bottle state
        if ($bottle->state !== BottleState::Stored && $bottle->state !== BottleState::ReservedForPicking) {
            return [
                'valid' => false,
                'error' => "Bottle is in '{$bottle->state->label()}' state. Only Stored or Reserved bottles can be picked.",
            ];
        }

        // Validate bottle is not in terminal state
        if ($bottle->state->isTerminal()) {
            return [
                'valid' => false,
                'error' => "Bottle is in terminal state '{$bottle->state->label()}' and cannot be fulfilled",
            ];
        }

        // Check bottle is not already bound to another active line
        $existingBinding = ShippingOrderLine::where('bound_bottle_serial', $serialNumber)
            ->where('id', '!=', $line->id)
            ->whereHas('shippingOrder', function ($query) {
                $query->whereNotIn('status', [
                    ShippingOrderStatus::Cancelled->value,
                    ShippingOrderStatus::Completed->value,
                ]);
            })
            ->first();

        if ($existingBinding !== null) {
            return [
                'valid' => false,
                'error' => "Serial '{$serialNumber}' is already bound to another active shipping order line",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Build the picking payload for WMS.
     *
     * @param  ShippingOrder  $so  The shipping order
     * @return array<string, mixed>
     */
    protected function buildPickingPayload(ShippingOrder $so): array
    {
        $lines = [];

        foreach ($so->lines as $line) {
            // Get product info from allocation relationships
            $allocation = $line->allocation;
            $wineVariant = $allocation?->wineVariant;
            $format = $allocation?->format;

            $linePayload = [
                'line_id' => $line->id,
                'voucher_id' => $line->voucher_id,
                'allocation_id' => $line->allocation_id,
                'wine_variant_id' => $wineVariant?->id,
                'format_id' => $format?->id,
            ];

            // Determine binding type
            if ($line->hasEarlyBinding()) {
                $linePayload['binding_type'] = 'early';
                $linePayload['specific_serial'] = $line->early_binding_serial;
            } else {
                $linePayload['binding_type'] = 'late';
                $linePayload['allocation_constraint'] = $line->allocation_id;
                // WMS can select any serial from this allocation
            }

            $lines[] = $linePayload;
        }

        return [
            'so_id' => $so->id,
            'warehouse_id' => $so->source_warehouse_id,
            'customer_id' => $so->customer_id,
            'packaging_preference' => $so->packaging_preference->value,
            'special_instructions' => $so->special_instructions,
            'requested_ship_date' => $so->requested_ship_date?->toIso8601String(),
            'lines' => $lines,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Log an event to the shipping order audit log.
     *
     * @param  ShippingOrder  $shippingOrder  The shipping order
     * @param  string  $eventType  The event type
     * @param  string  $description  The event description
     * @param  array<string, mixed>|null  $oldValues  The old values (if applicable)
     * @param  array<string, mixed>|null  $newValues  The new values (if applicable)
     */
    protected function logEvent(
        ShippingOrder $shippingOrder,
        string $eventType,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
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
