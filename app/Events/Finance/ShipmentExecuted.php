<?php

namespace App\Events\Finance;

use App\Models\Customer\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: ShipmentExecuted
 *
 * Dispatched when a shipment is executed from Module C.
 * Triggers auto-generation of INV2 (Shipping Redemption) invoice.
 *
 * This event is typically dispatched by:
 * - Module C when a shipping order is executed and shipped
 * - The shipping execution process after carrier confirms pickup/dispatch
 *
 * Note: This event is defined in the Finance module as it represents
 * the financial consequence of a shipment. Module C should dispatch this
 * event when a shipment is executed and payment is expected.
 *
 * Shipping Cost Structure:
 * The items array should include separate line items for:
 * - Base shipping fee (carrier costs)
 * - Insurance (if applicable)
 * - Packaging (special handling)
 * - Duties and taxes (for cross-border shipments)
 * - Handling fees (warehouse handling)
 * - Redemption fees (if applicable - wine redemption from custody)
 *
 * Redemption vs Shipping-Only:
 * - Shipping-only: Customer shipping their own wine (no redemption fee)
 * - Redemption+shipping: Customer redeeming vouchers for wine delivery (redemption fee applies)
 * The redemption fee is determined by Module S pricing and added as a separate line item.
 *
 * Multi-Shipment Aggregation:
 * For aggregating multiple shipments into a single INV2 invoice, use the static
 * createForMultipleShipments() factory method. This creates an event with:
 * - shippingOrderIds array containing all shipping order IDs (JSON format in source_id)
 * - Items include separate lines per shipment (tagged with shipping_order_id in metadata)
 * - source_id stores JSON array of all shipping order IDs
 */
class ShipmentExecuted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Customer  $customer  The customer who owns the shipment
     * @param  string  $shippingOrderId  Primary shipping order ID (or first ID for multi-shipment)
     * @param  array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, line_type?: string, shipping_order_id?: string, metadata?: array<string, mixed>}>  $items  The shipping cost line items
     * @param  string  $currency  Currency code for the invoice (default: EUR)
     * @param  bool  $autoIssue  Whether to automatically issue the invoice after creation (default: true for immediate payment)
     * @param  array<string, mixed>|null  $metadata  Optional metadata about the shipment
     * @param  array{amount: string, tax_rate: string, description?: string, pricing_snapshot_id?: string, metadata?: array<string, mixed>}|null  $redemptionFee  Optional redemption fee from Module S
     * @param  bool  $isRedemption  Whether this is a redemption (voucher) shipment vs shipping-only
     * @param  array<string>|null  $shippingOrderIds  Optional array of additional shipping order IDs for multi-shipment aggregation
     */
    public function __construct(
        public Customer $customer,
        public string $shippingOrderId,
        public array $items,
        public string $currency = 'EUR',
        public bool $autoIssue = true,
        public ?array $metadata = null,
        public ?array $redemptionFee = null,
        public bool $isRedemption = false,
        public ?array $shippingOrderIds = null
    ) {}

    /**
     * Get the destination country code from metadata.
     */
    public function getDestinationCountry(): ?string
    {
        return $this->metadata['destination_country'] ?? null;
    }

    /**
     * Get the origin country code from metadata.
     */
    public function getOriginCountry(): ?string
    {
        return $this->metadata['origin_country'] ?? null;
    }

    /**
     * Check if this is a cross-border shipment.
     */
    public function isCrossBorder(): bool
    {
        $origin = $this->getOriginCountry();
        $destination = $this->getDestinationCountry();

        if ($origin === null || $destination === null) {
            return false;
        }

        return $origin !== $destination;
    }

    /**
     * Get the total amount for this shipment.
     */
    public function getTotalAmount(): string
    {
        $total = '0.00';

        foreach ($this->items as $item) {
            $lineTotal = bcmul($item['quantity'], $item['unit_price'], 2);
            $total = bcadd($total, $lineTotal, 2);
        }

        return $total;
    }

    /**
     * Get the total tax amount for this shipment.
     */
    public function getTotalTaxAmount(): string
    {
        $totalTax = '0.00';

        foreach ($this->items as $item) {
            $lineSubtotal = bcmul($item['quantity'], $item['unit_price'], 2);
            $lineTax = bcmul($lineSubtotal, bcdiv($item['tax_rate'], '100', 6), 2);
            $totalTax = bcadd($totalTax, $lineTax, 2);
        }

        return $totalTax;
    }

    /**
     * Check if this shipment has duties.
     */
    public function hasDuties(): bool
    {
        foreach ($this->items as $item) {
            $lineType = $item['line_type'] ?? null;
            if ($lineType === 'duties' || $lineType === 'customs_duties') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the carrier name from metadata.
     */
    public function getCarrierName(): ?string
    {
        return $this->metadata['carrier_name'] ?? null;
    }

    /**
     * Get the tracking number from metadata.
     */
    public function getTrackingNumber(): ?string
    {
        return $this->metadata['tracking_number'] ?? null;
    }

    /**
     * Get the shipped date from metadata.
     */
    public function getShippedAt(): ?string
    {
        return $this->metadata['shipped_at'] ?? null;
    }

    /**
     * Check if this shipment includes a redemption fee.
     *
     * A redemption fee applies when a customer is redeeming vouchers for wine
     * delivery, as opposed to simply shipping their own wine from custody.
     */
    public function hasRedemptionFee(): bool
    {
        return $this->redemptionFee !== null
            && isset($this->redemptionFee['amount'])
            && bccomp($this->redemptionFee['amount'], '0', 2) > 0;
    }

    /**
     * Get the redemption fee amount.
     */
    public function getRedemptionFeeAmount(): ?string
    {
        return $this->redemptionFee['amount'] ?? null;
    }

    /**
     * Get the redemption fee tax rate.
     */
    public function getRedemptionFeeTaxRate(): ?string
    {
        return $this->redemptionFee['tax_rate'] ?? null;
    }

    /**
     * Get the redemption fee description.
     *
     * Falls back to a default description if not provided.
     */
    public function getRedemptionFeeDescription(): string
    {
        return $this->redemptionFee['description'] ?? 'Wine Redemption Fee';
    }

    /**
     * Get the redemption fee pricing snapshot ID (from Module S).
     */
    public function getRedemptionFeePricingSnapshotId(): ?string
    {
        return $this->redemptionFee['pricing_snapshot_id'] ?? null;
    }

    /**
     * Get the redemption fee metadata.
     *
     * @return array<string, mixed>
     */
    public function getRedemptionFeeMetadata(): array
    {
        return $this->redemptionFee['metadata'] ?? [];
    }

    /**
     * Check if this is a redemption shipment (vs shipping-only).
     *
     * Redemption shipments involve voucher redemption for wine delivery,
     * while shipping-only is when a customer ships their own wine from custody.
     */
    public function isRedemptionShipment(): bool
    {
        return $this->isRedemption || $this->hasRedemptionFee();
    }

    /**
     * Check if this is a shipping-only shipment (no redemption).
     */
    public function isShippingOnly(): bool
    {
        return ! $this->isRedemptionShipment();
    }

    /**
     * Get the shipment type as a string for display/logging.
     */
    public function getShipmentType(): string
    {
        return $this->isRedemptionShipment() ? 'redemption' : 'shipping_only';
    }

    // =========================================================================
    // Multi-Shipment Aggregation Methods
    // =========================================================================

    /**
     * Create an event for aggregating multiple shipments into a single INV2.
     *
     * This factory method simplifies creating multi-shipment aggregation events.
     * All items should include 'shipping_order_id' in their metadata to indicate
     * which shipment they belong to.
     *
     * @param  Customer  $customer  The customer who owns the shipments
     * @param  array<string>  $shippingOrderIds  Array of shipping order IDs to aggregate
     * @param  array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, line_type?: string, shipping_order_id?: string, metadata?: array<string, mixed>}>  $items  All line items from all shipments
     * @param  string  $currency  Currency code (default: EUR)
     * @param  bool  $autoIssue  Whether to auto-issue (default: true)
     * @param  array<string, mixed>|null  $metadata  Optional aggregated metadata
     * @param  array{amount: string, tax_rate: string, description?: string, pricing_snapshot_id?: string, metadata?: array<string, mixed>}|null  $redemptionFee  Optional redemption fee
     * @param  bool  $isRedemption  Whether any shipment is a redemption
     */
    public static function createForMultipleShipments(
        Customer $customer,
        array $shippingOrderIds,
        array $items,
        string $currency = 'EUR',
        bool $autoIssue = true,
        ?array $metadata = null,
        ?array $redemptionFee = null,
        bool $isRedemption = false
    ): self {
        // Use first shipping order ID as primary, but store all IDs
        $primaryOrderId = $shippingOrderIds[0] ?? '';

        // Ensure metadata includes multi-shipment info
        $metadata = $metadata ?? [];
        $metadata['is_multi_shipment'] = true;
        $metadata['shipping_order_count'] = count($shippingOrderIds);
        $metadata['all_shipping_order_ids'] = $shippingOrderIds;

        return new self(
            customer: $customer,
            shippingOrderId: $primaryOrderId,
            items: $items,
            currency: $currency,
            autoIssue: $autoIssue,
            metadata: $metadata,
            redemptionFee: $redemptionFee,
            isRedemption: $isRedemption,
            shippingOrderIds: $shippingOrderIds
        );
    }

    /**
     * Check if this is a multi-shipment aggregation event.
     */
    public function isMultiShipment(): bool
    {
        return $this->shippingOrderIds !== null && count($this->shippingOrderIds) > 1;
    }

    /**
     * Get all shipping order IDs for this event.
     *
     * Returns an array with the primary shippingOrderId if not a multi-shipment,
     * or the full shippingOrderIds array if it is.
     *
     * @return array<string>
     */
    public function getAllShippingOrderIds(): array
    {
        if ($this->shippingOrderIds !== null && count($this->shippingOrderIds) > 0) {
            return $this->shippingOrderIds;
        }

        return [$this->shippingOrderId];
    }

    /**
     * Get the number of shipments in this event.
     */
    public function getShipmentCount(): int
    {
        return count($this->getAllShippingOrderIds());
    }

    /**
     * Get the source ID for storing in the invoice.
     *
     * For single shipments, returns the shipping order ID.
     * For multi-shipments, returns a JSON array of all IDs.
     */
    public function getSourceIdForInvoice(): string
    {
        if ($this->isMultiShipment()) {
            return json_encode($this->getAllShippingOrderIds()) ?: $this->shippingOrderId;
        }

        return $this->shippingOrderId;
    }

    /**
     * Get items grouped by shipping order ID.
     *
     * For multi-shipment invoices, items should have shipping_order_id in metadata.
     * This method groups items by their shipping order for display purposes.
     *
     * @return array<string, array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, line_type?: string, metadata?: array<string, mixed>}>>
     */
    public function getItemsByShippingOrder(): array
    {
        $grouped = [];

        foreach ($this->items as $item) {
            $orderId = $item['shipping_order_id'] ?? ($item['metadata']['shipping_order_id'] ?? $this->shippingOrderId);
            if (! isset($grouped[$orderId])) {
                $grouped[$orderId] = [];
            }
            $grouped[$orderId][] = $item;
        }

        return $grouped;
    }

    /**
     * Get the total amount for a specific shipping order.
     */
    public function getTotalAmountForShippingOrder(string $shippingOrderId): string
    {
        $total = '0.00';
        $grouped = $this->getItemsByShippingOrder();

        $items = $grouped[$shippingOrderId] ?? [];
        foreach ($items as $item) {
            $lineTotal = bcmul($item['quantity'], $item['unit_price'], 2);
            $total = bcadd($total, $lineTotal, 2);
        }

        return $total;
    }
}
