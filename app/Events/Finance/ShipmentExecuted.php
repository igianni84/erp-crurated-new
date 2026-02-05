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
     * @param  string  $shippingOrderId  Unique ID of the shipping order from Module C
     * @param  array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, line_type?: string, metadata?: array<string, mixed>}>  $items  The shipping cost line items
     * @param  string  $currency  Currency code for the invoice (default: EUR)
     * @param  bool  $autoIssue  Whether to automatically issue the invoice after creation (default: true for immediate payment)
     * @param  array<string, mixed>|null  $metadata  Optional metadata about the shipment
     */
    public function __construct(
        public Customer $customer,
        public string $shippingOrderId,
        public array $items,
        public string $currency = 'EUR',
        public bool $autoIssue = true,
        public ?array $metadata = null
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
}
