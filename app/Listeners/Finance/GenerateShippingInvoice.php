<?php

namespace App\Listeners\Finance;

use App\Enums\Finance\InvoiceType;
use App\Events\Finance\ShipmentExecuted;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\ShippingTaxService;
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
 * - Determines tax rate based on destination country
 * - Sets status to issued immediately (INV2 expects immediate payment)
 *
 * Line Types:
 * - shipping: Base shipping/carrier costs
 * - insurance: Shipping insurance
 * - packaging: Special packaging fees
 * - handling: Warehouse handling fees
 * - duties: Customs duties for cross-border
 * - taxes: Import taxes for cross-border
 * - redemption: Redemption fee for voucher redemption shipments
 *
 * Shipping-only vs Redemption+Shipping:
 * - Shipping-only: Customer shipping their own wine (no redemption fee)
 * - Redemption: Customer redeeming vouchers for wine delivery (redemption fee applies)
 */
class GenerateShippingInvoice implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected InvoiceService $invoiceService,
        protected ShippingTaxService $shippingTaxService
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

        // Add redemption fee line if applicable
        if ($event->hasRedemptionFee()) {
            $lines[] = $this->buildRedemptionFeeLine($event);
        }

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
            'shipment_type' => $event->getShipmentType(),
            'has_redemption_fee' => $event->hasRedemptionFee(),
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
     * Uses ShippingTaxService to determine appropriate tax rates based on
     * destination country when not explicitly provided in the item.
     *
     * @return array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, sellable_sku_id: int|null, metadata: array<string, mixed>}>
     */
    protected function buildInvoiceLines(ShipmentExecuted $event): array
    {
        $lines = [];

        // Determine tax rate based on destination country
        $destinationCountry = $event->getDestinationCountry();
        $originCountry = $event->getOriginCountry();
        $determinedTaxInfo = null;

        if ($destinationCountry !== null) {
            $determinedTaxInfo = $this->shippingTaxService->determineTaxRate(
                destinationCountry: $destinationCountry,
                originCountry: $originCountry
            );

            Log::channel('finance')->debug('Determined tax rate for shipping invoice', [
                'shipping_order_id' => $event->shippingOrderId,
                'destination_country' => $destinationCountry,
                'origin_country' => $originCountry,
                'tax_rate' => $determinedTaxInfo['tax_rate'],
                'tax_type' => $determinedTaxInfo['tax_type'],
                'is_export' => $determinedTaxInfo['is_export'],
            ]);
        }

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
                $metadata['is_cross_border'] = true;
            }

            // Add carrier information
            if ($event->getCarrierName() !== null) {
                $metadata['carrier_name'] = $event->getCarrierName();
            }

            if ($event->getTrackingNumber() !== null) {
                $metadata['tracking_number'] = $event->getTrackingNumber();
            }

            // Determine tax rate for this line
            // Use provided tax rate, or fall back to destination-based rate
            $lineType = $item['line_type'] ?? 'shipping';
            $taxRate = $item['tax_rate'];

            // For duties and import taxes, always use 0% (they ARE the tax)
            if (in_array($lineType, ['duties', 'taxes', 'customs_duties', 'import_taxes'], true)) {
                $taxRate = '0.00';
                $metadata['tax_note'] = 'Duties/taxes are not subject to additional VAT';
            } elseif ($determinedTaxInfo !== null) {
                // Use destination-based tax rate for shipping costs
                // Item metadata can contain 'use_provided_rate' => true to keep the original rate
                $itemMetadata = $item['metadata'] ?? [];
                $useProvidedRate = isset($itemMetadata['use_provided_rate']) && $itemMetadata['use_provided_rate'] === true;

                if (! $useProvidedRate) {
                    $taxRate = $determinedTaxInfo['tax_rate'];
                    $metadata['tax_jurisdiction'] = $determinedTaxInfo['tax_jurisdiction'];
                    $metadata['tax_type'] = $determinedTaxInfo['tax_type'];

                    if ($determinedTaxInfo['zero_rated_reason'] !== null) {
                        $metadata['zero_rated_reason'] = $determinedTaxInfo['zero_rated_reason'];
                    }
                }
            }

            // Merge item-level metadata if present
            if (isset($item['metadata'])) {
                $metadata = array_merge($metadata, $item['metadata']);
            }

            $lines[] = [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $taxRate,
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
        // Distinguish shipping-only vs redemption+shipping
        $shipmentType = $event->isRedemptionShipment() ? 'Redemption' : 'Shipping';
        $notes = "{$shipmentType} invoice for order {$event->shippingOrderId}.";

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

        if ($event->hasRedemptionFee()) {
            $notes .= ' Includes redemption fee.';
        }

        if ($event->metadata !== null && isset($event->metadata['shipping_notes'])) {
            $notes .= ' '.$event->metadata['shipping_notes'];
        }

        return $notes;
    }

    /**
     * Build the redemption fee invoice line.
     *
     * The redemption fee is charged when a customer redeems vouchers for wine
     * delivery, covering the cost of releasing wine from custody.
     *
     * Fee amount comes from Module S pricing.
     *
     * @return array{description: string, quantity: string, unit_price: string, tax_rate: string, sellable_sku_id: int|null, metadata: array<string, mixed>}
     */
    protected function buildRedemptionFeeLine(ShipmentExecuted $event): array
    {
        $metadata = [
            'shipping_order_id' => $event->shippingOrderId,
            'line_type' => 'redemption',
            'shipment_type' => 'redemption',
        ];

        // Add pricing snapshot ID from Module S if available
        $pricingSnapshotId = $event->getRedemptionFeePricingSnapshotId();
        if ($pricingSnapshotId !== null) {
            $metadata['pricing_snapshot_id'] = $pricingSnapshotId;
        }

        // Add any additional metadata from the redemption fee
        $feeMetadata = $event->getRedemptionFeeMetadata();
        if (! empty($feeMetadata)) {
            $metadata = array_merge($metadata, $feeMetadata);
        }

        // Add cross-border info if applicable
        if ($event->isCrossBorder()) {
            $metadata['origin_country'] = $event->getOriginCountry();
            $metadata['destination_country'] = $event->getDestinationCountry();
        }

        return [
            'description' => $event->getRedemptionFeeDescription(),
            'quantity' => '1.00',
            'unit_price' => $event->getRedemptionFeeAmount() ?? '0.00',
            'tax_rate' => $event->getRedemptionFeeTaxRate() ?? '0.00',
            'sellable_sku_id' => null, // Redemption fee is a service, not a sellable SKU
            'metadata' => $metadata,
        ];
    }
}
