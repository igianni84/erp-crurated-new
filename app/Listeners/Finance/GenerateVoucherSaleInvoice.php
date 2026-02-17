<?php

namespace App\Listeners\Finance;

use App\Enums\Finance\InvoiceType;
use App\Events\Finance\VoucherSaleConfirmed;
use App\Services\Finance\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listener: GenerateVoucherSaleInvoice
 *
 * Generates an INV1 (Voucher Sale) invoice when a sale is confirmed
 * from Module A.
 *
 * This listener:
 * - Creates an invoice with type INV1
 * - Links the invoice to the sale (source_type='voucher_sale')
 * - Creates invoice lines from sellable_sku + quantity + price
 * - Stores pricing_snapshot_id and pricing metadata from Module S (US-E032)
 * - Sets status to issued immediately (INV1 expects immediate payment)
 *
 * Pricing Integration (US-E032):
 * Invoice lines include pricing metadata for audit trail:
 * - pricing_snapshot_id: References the Module S pricing snapshot used
 * - pricing.price_book_id: The Price Book that provided the price
 * - pricing.offer_id: The Offer that was applied (if any)
 * - tax_jurisdiction: The country used for tax calculation
 */
class GenerateVoucherSaleInvoice implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(VoucherSaleConfirmed $event): void
    {
        $customer = $event->customer;

        // Check for existing invoice (idempotency)
        $existingInvoice = $this->invoiceService->findBySource('voucher_sale', $event->saleReference);
        if ($existingInvoice !== null) {
            Log::channel('finance')->info('Invoice already exists for voucher sale', [
                'sale_reference' => $event->saleReference,
                'invoice_id' => $existingInvoice->id,
            ]);

            return;
        }

        // Validate items
        if (empty($event->items)) {
            Log::channel('finance')->error('Cannot generate INV1: no items in sale', [
                'sale_reference' => $event->saleReference,
                'customer_id' => $customer->id,
            ]);

            return;
        }

        // Build invoice lines from sale items
        $lines = $this->buildInvoiceLines($event);

        // Create the draft invoice
        // Note: INV1 does not require a due date (immediate payment expected)
        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: $event->saleReference,
            currency: $event->currency,
            dueDate: null, // INV1 expects immediate payment
            notes: $this->buildInvoiceNotes($event)
        );

        Log::channel('finance')->info('Generated INV1 draft invoice for voucher sale', [
            'sale_reference' => $event->saleReference,
            'invoice_id' => $invoice->id,
            'total_amount' => $invoice->total_amount,
            'items_count' => count($event->items),
        ]);

        // Auto-issue immediately (INV1 is typically issued right away)
        if ($event->autoIssue) {
            try {
                $this->invoiceService->issue($invoice);
                Log::channel('finance')->info('Auto-issued INV1 invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'sale_reference' => $event->saleReference,
                ]);
            } catch (Throwable $e) {
                Log::channel('finance')->error('Failed to auto-issue INV1 invoice', [
                    'invoice_id' => $invoice->id,
                    'sale_reference' => $event->saleReference,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build invoice lines from sale items.
     *
     * Each line includes pricing metadata from Module S for audit trail:
     * - pricing_snapshot_id: References the pricing snapshot used
     * - pricing_metadata: Additional pricing details (price_book_id, offer_id, etc.)
     *
     * @return array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, sellable_sku_id: int|null, metadata: array<string, mixed>}>
     */
    protected function buildInvoiceLines(VoucherSaleConfirmed $event): array
    {
        $lines = [];

        // Get batch-level pricing snapshot ID if available
        $batchPricingSnapshotId = $event->getPricingSnapshotId();

        foreach ($event->items as $item) {
            // Build metadata with pricing information
            $metadata = [
                'sale_reference' => $event->saleReference,
                'sku_code' => $item['sku_code'],
                'original_quantity' => $item['quantity'],
            ];

            // Add pricing snapshot ID (item-level takes precedence over batch-level)
            $pricingSnapshotId = $item['pricing_snapshot_id'] ?? $batchPricingSnapshotId;
            if ($pricingSnapshotId !== null) {
                $metadata['pricing_snapshot_id'] = $pricingSnapshotId;
            }

            // Add pricing metadata if available (from Module S)
            if (isset($item['pricing_metadata'])) {
                $metadata['pricing'] = $item['pricing_metadata'];
            }

            // Add tax calculation details if available (from extended item data)
            /** @var array<string, mixed> $itemData */
            $itemData = $item;
            if (array_key_exists('tax_jurisdiction', $itemData) && isset($itemData['tax_jurisdiction'])) {
                $metadata['tax_jurisdiction'] = $itemData['tax_jurisdiction'];
            }

            $lines[] = [
                'description' => $item['description'],
                'quantity' => (string) $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'],
                'sellable_sku_id' => $item['sellable_sku_id'] ?? null,
                'metadata' => $metadata,
            ];
        }

        return $lines;
    }

    /**
     * Build notes for the invoice.
     */
    protected function buildInvoiceNotes(VoucherSaleConfirmed $event): string
    {
        $itemCount = count($event->items);
        $totalQuantity = 0;

        foreach ($event->items as $item) {
            $totalQuantity += $item['quantity'];
        }

        $notes = "Voucher sale ({$itemCount} item(s), {$totalQuantity} unit(s)). Sale reference: {$event->saleReference}.";

        if ($event->metadata !== null && isset($event->metadata['order_notes'])) {
            $notes .= ' '.$event->metadata['order_notes'];
        }

        return $notes;
    }
}
