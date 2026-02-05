<?php

namespace App\Events\Finance;

use App\Models\Customer\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: VoucherSaleConfirmed
 *
 * Dispatched when a voucher sale is confirmed from Module A.
 * Triggers auto-generation of INV1 (Voucher Sale) invoice.
 *
 * This event is typically dispatched by:
 * - Module A when a voucher batch or sale order is confirmed
 * - Manual sale confirmation processes
 *
 * Note: This event is defined in the Finance module as it represents
 * the financial consequence of a sale. Module A should dispatch this
 * event when a sale is confirmed and payment is expected.
 *
 * Pricing Integration (US-E032):
 * Module A should obtain pricing from PricingService before dispatching this event.
 * Each item should include:
 * - unit_price: Price from Module S (via PricingService::getPricingForSale)
 * - tax_rate: Tax rate based on customer geography and product type
 *
 * The metadata should include pricing snapshot for audit:
 * - pricing_snapshot_id: Unique ID for the pricing snapshot
 * - pricing_metadata: Additional pricing details (price_book_id, offer_id, etc.)
 */
class VoucherSaleConfirmed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Customer  $customer  The customer who made the purchase
     * @param  array<int, array{sellable_sku_id: int|null, sku_code: string, description: string, quantity: int, unit_price: string, tax_rate: string, pricing_snapshot_id?: string, pricing_metadata?: array<string, mixed>}>  $items  The items purchased with pricing from Module S
     * @param  string  $saleReference  Unique reference for this sale (voucher_batch_id or sale_order_id)
     * @param  string  $currency  Currency code for the sale (default: EUR)
     * @param  bool  $autoIssue  Whether to automatically issue the invoice after creation (default: true for immediate payment)
     * @param  array<string, mixed>|null  $metadata  Optional metadata about the sale (may include pricing_snapshot_id for batch-level snapshot)
     */
    public function __construct(
        public Customer $customer,
        public array $items,
        public string $saleReference,
        public string $currency = 'EUR',
        public bool $autoIssue = true,
        public ?array $metadata = null
    ) {}

    /**
     * Get the pricing snapshot ID for this sale.
     *
     * Returns the first available pricing snapshot ID from items or metadata.
     * This is used for audit trail to track which pricing was used.
     */
    public function getPricingSnapshotId(): ?string
    {
        // Check metadata first (batch-level snapshot)
        if ($this->metadata !== null && isset($this->metadata['pricing_snapshot_id'])) {
            return $this->metadata['pricing_snapshot_id'];
        }

        // Check items for individual snapshots
        foreach ($this->items as $item) {
            if (isset($item['pricing_snapshot_id'])) {
                return $item['pricing_snapshot_id'];
            }
        }

        return null;
    }

    /**
     * Check if this sale has pricing metadata from Module S.
     */
    public function hasPricingMetadata(): bool
    {
        return $this->getPricingSnapshotId() !== null;
    }

    /**
     * Get the total amount for this sale.
     */
    public function getTotalAmount(): string
    {
        $total = '0.00';

        foreach ($this->items as $item) {
            $lineTotal = bcmul((string) $item['quantity'], $item['unit_price'], 2);
            $total = bcadd($total, $lineTotal, 2);
        }

        return $total;
    }

    /**
     * Get the total tax amount for this sale.
     */
    public function getTotalTaxAmount(): string
    {
        $totalTax = '0.00';

        foreach ($this->items as $item) {
            $lineSubtotal = bcmul((string) $item['quantity'], $item['unit_price'], 2);
            $lineTax = bcmul($lineSubtotal, bcdiv($item['tax_rate'], '100', 6), 2);
            $totalTax = bcadd($totalTax, $lineTax, 2);
        }

        return $totalTax;
    }
}
