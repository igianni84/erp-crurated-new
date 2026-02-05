<?php

namespace App\Listeners\Finance;

use App\Enums\Finance\InvoiceType;
use App\Events\Finance\ShipmentExecuted;
use App\Services\Finance\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener: GenerateShippingInvoice
 *
 * Generates an INV2 (Shipping Redemption) invoice when a shipment is executed
 * from Module C.
 *
 * This listener:
 * - Creates an invoice with type INV2
 * - Links the invoice to the shipping order (source_type='shipping_order')
 * - Creates invoice lines for shipping fees, handling, duties/taxes
 * - Sets status to issued immediately (INV2 expects immediate payment)
 *
 * Line Types:
 * - shipping: Base shipping/carrier costs
 * - insurance: Shipping insurance
 * - packaging: Special packaging fees
 * - handling: Warehouse handling fees
 * - duties: Customs duties for cross-border
 * - taxes: Import taxes for cross-border
 */
class GenerateShippingInvoice implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ShipmentExecuted $event): void
    {
        $customer = $event->customer;

        // Check for existing invoice (idempotency)
        $existingInvoice = $this->invoiceService->findBySource('shipping_order', $event->shippingOrderId);
        if ($existingInvoice !== null) {
            Log::channel('finance')->info('Invoice already exists for shipping order', [
                'shipping_order_id' => $event->shippingOrderId,
                'invoice_id' => $existingInvoice->id,
            ]);

            return;
        }

        // Validate items
        if (empty($event->items)) {
            Log::channel('finance')->error('Cannot generate INV2: no items in shipment', [
                'shipping_order_id' => $event->shippingOrderId,
                'customer_id' => $customer->id,
            ]);

            return;
        }

        // Build invoice lines from shipment items
        $lines = $this->buildInvoiceLines($event);

        // Create the draft invoice
        // Note: INV2 does not require a due date (immediate payment expected)
        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::ShippingRedemption,
            customer: $customer,
            lines: $lines,
            sourceType: 'shipping_order',
            sourceId: $event->shippingOrderId,
            currency: $event->currency,
            dueDate: null, // INV2 expects immediate payment
            notes: $this->buildInvoiceNotes($event)
        );

        Log::channel('finance')->info('Generated INV2 draft invoice for shipment', [
            'shipping_order_id' => $event->shippingOrderId,
            'invoice_id' => $invoice->id,
            'total_amount' => $invoice->total_amount,
            'items_count' => count($event->items),
            'is_cross_border' => $event->isCrossBorder(),
        ]);

        // Auto-issue immediately (INV2 is typically issued right away)
        if ($event->autoIssue) {
            try {
                $this->invoiceService->issue($invoice);
                Log::channel('finance')->info('Auto-issued INV2 invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'shipping_order_id' => $event->shippingOrderId,
                ]);
            } catch (\Throwable $e) {
                Log::channel('finance')->error('Failed to auto-issue INV2 invoice', [
                    'invoice_id' => $invoice->id,
                    'shipping_order_id' => $event->shippingOrderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build invoice lines from shipment items.
     *
     * @return array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, sellable_sku_id: int|null, metadata: array<string, mixed>}>
     */
    protected function buildInvoiceLines(ShipmentExecuted $event): array
    {
        $lines = [];

        foreach ($event->items as $item) {
            // Build metadata with shipment information
            $metadata = [
                'shipping_order_id' => $event->shippingOrderId,
                'line_type' => $item['line_type'] ?? 'shipping',
            ];

            // Add cross-border information if present
            if ($event->isCrossBorder()) {
                $metadata['origin_country'] = $event->getOriginCountry();
                $metadata['destination_country'] = $event->getDestinationCountry();
            }

            // Add carrier information
            if ($event->getCarrierName() !== null) {
                $metadata['carrier_name'] = $event->getCarrierName();
            }

            if ($event->getTrackingNumber() !== null) {
                $metadata['tracking_number'] = $event->getTrackingNumber();
            }

            // Merge item-level metadata if present
            if (isset($item['metadata'])) {
                $metadata = array_merge($metadata, $item['metadata']);
            }

            $lines[] = [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'],
                'sellable_sku_id' => null, // Shipping lines don't reference sellable SKUs
                'metadata' => $metadata,
            ];
        }

        return $lines;
    }

    /**
     * Build notes for the invoice.
     */
    protected function buildInvoiceNotes(ShipmentExecuted $event): string
    {
        $notes = "Shipping invoice for order {$event->shippingOrderId}.";

        if ($event->getCarrierName() !== null) {
            $notes .= " Carrier: {$event->getCarrierName()}.";
        }

        if ($event->getTrackingNumber() !== null) {
            $notes .= " Tracking: {$event->getTrackingNumber()}.";
        }

        if ($event->isCrossBorder()) {
            $notes .= " Cross-border shipment from {$event->getOriginCountry()} to {$event->getDestinationCountry()}.";

            if ($event->hasDuties()) {
                $notes .= ' Includes customs duties.';
            }
        }

        if ($event->metadata !== null && isset($event->metadata['shipping_notes'])) {
            $notes .= ' '.$event->metadata['shipping_notes'];
        }

        return $notes;
    }
}
