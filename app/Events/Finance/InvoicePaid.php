<?php

namespace App\Events\Finance;

use App\Enums\Finance\InvoiceType;
use App\Models\Finance\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: InvoicePaid
 *
 * Dispatched when an invoice transitions to paid status.
 * This event is emitted AFTER the payment is reconciled and applied.
 *
 * Downstream modules listen to this event to trigger operational effects:
 * - INV1 (Voucher Sale): Module A creates/activates vouchers
 * - INV2 (Shipping): Module C confirms shipment execution
 * - INV0 (Membership): Module K updates membership status
 * - INV3 (Storage): Module B unlocks custody operations
 * - INV4 (Service Events): Module handles event confirmation
 *
 * IMPORTANT: Finance is consequence, not cause. This event signals
 * payment confirmation but does not directly create operational entities.
 * Operational modules are responsible for their own business logic.
 */
class InvoicePaid
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Invoice  $invoice  The invoice that has been fully paid
     * @param  string  $totalPaid  The total amount paid on this invoice
     * @param  string|null  $correlationId  Optional correlation ID for tracing across modules
     */
    public function __construct(
        public Invoice $invoice,
        public string $totalPaid,
        public ?string $correlationId = null
    ) {}

    /**
     * Get the invoice type for downstream filtering.
     */
    public function getInvoiceType(): InvoiceType
    {
        return $this->invoice->invoice_type;
    }

    /**
     * Check if this is an INV1 (Voucher Sale) invoice.
     * Module A should listen for these to create/activate vouchers.
     */
    public function isVoucherSaleInvoice(): bool
    {
        return $this->invoice->invoice_type === InvoiceType::VoucherSale;
    }

    /**
     * Check if this is an INV0 (Membership Service) invoice.
     * Module K should listen for these to update membership status.
     */
    public function isMembershipInvoice(): bool
    {
        return $this->invoice->invoice_type === InvoiceType::MembershipService;
    }

    /**
     * Check if this is an INV2 (Shipping Redemption) invoice.
     * Module C should listen for these to confirm shipment.
     */
    public function isShippingInvoice(): bool
    {
        return $this->invoice->invoice_type === InvoiceType::ShippingRedemption;
    }

    /**
     * Check if this is an INV3 (Storage Fee) invoice.
     * Module B should listen for these to unlock custody operations.
     */
    public function isStorageInvoice(): bool
    {
        return $this->invoice->invoice_type === InvoiceType::StorageFee;
    }

    /**
     * Check if this is an INV4 (Service Events) invoice.
     */
    public function isServiceEventInvoice(): bool
    {
        return $this->invoice->invoice_type === InvoiceType::ServiceEvents;
    }

    /**
     * Get the source type (e.g., 'voucher_sale', 'subscription', etc.).
     */
    public function getSourceType(): ?string
    {
        return $this->invoice->source_type;
    }

    /**
     * Get the source ID (e.g., voucher_batch_id, subscription_id, etc.).
     */
    public function getSourceId(): string|int|null
    {
        return $this->invoice->source_id;
    }

    /**
     * Get the customer ID who paid.
     */
    public function getCustomerId(): string
    {
        return $this->invoice->customer_id;
    }

    /**
     * Get the invoice number for logging/correlation.
     */
    public function getInvoiceNumber(): ?string
    {
        return $this->invoice->invoice_number;
    }

    /**
     * Get invoice metadata for downstream processing.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'invoice_type' => $this->invoice->invoice_type->value,
            'customer_id' => $this->invoice->customer_id,
            'source_type' => $this->invoice->source_type,
            'source_id' => $this->invoice->source_id,
            'total_amount' => $this->invoice->total_amount,
            'total_paid' => $this->totalPaid,
            'currency' => $this->invoice->currency,
            'paid_at' => now()->toIso8601String(),
            'correlation_id' => $this->correlationId,
        ];
    }
}
