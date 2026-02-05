<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\Finance\InvoiceType;
use App\Events\Finance\VoucherSaleConfirmed;
use App\Listeners\Finance\GenerateVoucherSaleInvoice;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for INV1 multi-item support (US-E034).
 *
 * These tests verify that:
 * 1. Multiple invoice lines per INV1 are supported
 * 2. Each line is linkable to different sellable_sku
 * 3. Totals aggregate all lines correctly
 */
class InvoiceMultiItemTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $this->invoiceService = app(InvoiceService::class);
    }

    /**
     * Test that INV1 can have multiple invoice lines.
     */
    public function test_inv1_supports_multiple_invoice_lines(): void
    {
        $lines = [
            [
                'description' => 'Premium Wine Voucher - 2020 Vintage',
                'quantity' => '2',
                'unit_price' => '150.00',
                'tax_rate' => '22.00',
            ],
            [
                'description' => 'Standard Wine Voucher - 2021 Vintage',
                'quantity' => '3',
                'unit_price' => '75.00',
                'tax_rate' => '22.00',
            ],
            [
                'description' => 'Collector Edition Wine Voucher',
                'quantity' => '1',
                'unit_price' => '500.00',
                'tax_rate' => '22.00',
            ],
        ];

        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $this->customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: 'SALE-001'
        );

        // Verify 3 lines were created
        $this->assertEquals(3, $invoice->invoiceLines()->count());

        // Verify each line was created correctly
        $createdLines = $invoice->invoiceLines()->orderBy('id')->get();

        $this->assertEquals('Premium Wine Voucher - 2020 Vintage', $createdLines[0]->description);
        $this->assertEquals('2.00', $createdLines[0]->quantity);
        $this->assertEquals('150.00', $createdLines[0]->unit_price);

        $this->assertEquals('Standard Wine Voucher - 2021 Vintage', $createdLines[1]->description);
        $this->assertEquals('3.00', $createdLines[1]->quantity);
        $this->assertEquals('75.00', $createdLines[1]->unit_price);

        $this->assertEquals('Collector Edition Wine Voucher', $createdLines[2]->description);
        $this->assertEquals('1.00', $createdLines[2]->quantity);
        $this->assertEquals('500.00', $createdLines[2]->unit_price);
    }

    /**
     * Test that each invoice line can be linked to a different sellable_sku.
     */
    public function test_each_line_can_link_to_different_sellable_sku(): void
    {
        // Create mock sellable SKUs (if the model exists and table is available)
        // For this test, we'll test with sellable_sku_id references
        $lines = [
            [
                'description' => 'Premium Wine SKU-001',
                'quantity' => '2',
                'unit_price' => '150.00',
                'tax_rate' => '22.00',
                'sellable_sku_id' => 101,
                'metadata' => ['sku_code' => 'SKU-001'],
            ],
            [
                'description' => 'Standard Wine SKU-002',
                'quantity' => '3',
                'unit_price' => '75.00',
                'tax_rate' => '22.00',
                'sellable_sku_id' => 102,
                'metadata' => ['sku_code' => 'SKU-002'],
            ],
            [
                'description' => 'Collector Edition SKU-003',
                'quantity' => '1',
                'unit_price' => '500.00',
                'tax_rate' => '22.00',
                'sellable_sku_id' => 103,
                'metadata' => ['sku_code' => 'SKU-003'],
            ],
        ];

        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $this->customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: 'SALE-002'
        );

        // Verify each line has a different sellable_sku_id
        $createdLines = $invoice->invoiceLines()->orderBy('id')->get();

        $this->assertEquals(101, $createdLines[0]->sellable_sku_id);
        $this->assertEquals(102, $createdLines[1]->sellable_sku_id);
        $this->assertEquals(103, $createdLines[2]->sellable_sku_id);

        // Verify hasSellableSku method works
        $this->assertTrue($createdLines[0]->hasSellableSku());
        $this->assertTrue($createdLines[1]->hasSellableSku());
        $this->assertTrue($createdLines[2]->hasSellableSku());
    }

    /**
     * Test that totals correctly aggregate all lines.
     */
    public function test_totals_aggregate_all_lines_correctly(): void
    {
        $lines = [
            [
                'description' => 'Product A',
                'quantity' => '2',
                'unit_price' => '100.00',
                'tax_rate' => '22.00',
                // Line subtotal: 2 * 100 = 200.00
                // Line tax: 200 * 0.22 = 44.00
                // Line total: 244.00
            ],
            [
                'description' => 'Product B',
                'quantity' => '3',
                'unit_price' => '50.00',
                'tax_rate' => '22.00',
                // Line subtotal: 3 * 50 = 150.00
                // Line tax: 150 * 0.22 = 33.00
                // Line total: 183.00
            ],
            [
                'description' => 'Product C',
                'quantity' => '1',
                'unit_price' => '250.00',
                'tax_rate' => '10.00',
                // Line subtotal: 1 * 250 = 250.00
                // Line tax: 250 * 0.10 = 25.00
                // Line total: 275.00
            ],
        ];

        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $this->customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: 'SALE-003'
        );

        // Expected totals:
        // Subtotal: 200 + 150 + 250 = 600.00
        // Tax: 44 + 33 + 25 = 102.00
        // Total: 600 + 102 = 702.00

        $this->assertEquals('600.00', $invoice->subtotal);
        $this->assertEquals('102.00', $invoice->tax_amount);
        $this->assertEquals('702.00', $invoice->total_amount);
    }

    /**
     * Test that VoucherSaleConfirmed event with multiple items creates multi-line invoice.
     */
    public function test_voucher_sale_event_creates_multi_line_invoice(): void
    {
        $items = [
            [
                'sellable_sku_id' => 201,
                'sku_code' => 'WINE-2020-RESERVE',
                'description' => '2020 Reserve Wine Voucher',
                'quantity' => 2,
                'unit_price' => '120.00',
                'tax_rate' => '22.00',
            ],
            [
                'sellable_sku_id' => 202,
                'sku_code' => 'WINE-2021-CLASSIC',
                'description' => '2021 Classic Wine Voucher',
                'quantity' => 5,
                'unit_price' => '45.00',
                'tax_rate' => '22.00',
            ],
            [
                'sellable_sku_id' => 203,
                'sku_code' => 'WINE-GIFT-BOX',
                'description' => 'Premium Gift Box',
                'quantity' => 1,
                'unit_price' => '35.00',
                'tax_rate' => '22.00',
            ],
        ];

        $event = new VoucherSaleConfirmed(
            customer: $this->customer,
            items: $items,
            saleReference: 'BATCH-2026-001',
            currency: 'EUR',
            autoIssue: false  // Keep as draft for testing
        );

        // Execute the listener
        $listener = new GenerateVoucherSaleInvoice($this->invoiceService);
        $listener->handle($event);

        // Find the created invoice
        $invoice = Invoice::where('source_id', 'BATCH-2026-001')->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(InvoiceType::VoucherSale, $invoice->invoice_type);
        $this->assertEquals(3, $invoice->invoiceLines()->count());

        // Verify totals
        // Line 1: 2 * 120 = 240, tax = 52.80
        // Line 2: 5 * 45 = 225, tax = 49.50
        // Line 3: 1 * 35 = 35, tax = 7.70
        // Subtotal: 500.00
        // Tax: 110.00
        // Total: 610.00
        $this->assertEquals('500.00', $invoice->subtotal);
        $this->assertEquals('110.00', $invoice->tax_amount);
        $this->assertEquals('610.00', $invoice->total_amount);

        // Verify each line has correct sellable_sku_id
        $lines = $invoice->invoiceLines()->orderBy('id')->get();
        $this->assertEquals(201, $lines[0]->sellable_sku_id);
        $this->assertEquals(202, $lines[1]->sellable_sku_id);
        $this->assertEquals(203, $lines[2]->sellable_sku_id);
    }

    /**
     * Test that individual line totals are calculated correctly.
     */
    public function test_individual_line_totals_are_correct(): void
    {
        $lines = [
            [
                'description' => 'Product A',
                'quantity' => '3',
                'unit_price' => '100.00',
                'tax_rate' => '20.00',
            ],
        ];

        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $this->customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: 'SALE-004'
        );

        $line = $invoice->invoiceLines()->first();

        // Subtotal: 3 * 100 = 300.00
        $this->assertEquals('300.00', $line->getSubtotal());

        // Tax: 300 * 0.20 = 60.00
        $this->assertEquals('60.00', $line->tax_amount);

        // Line total: 300 + 60 = 360.00
        $this->assertEquals('360.00', $line->line_total);
    }

    /**
     * Test that lines with different tax rates aggregate correctly.
     */
    public function test_mixed_tax_rates_aggregate_correctly(): void
    {
        $lines = [
            [
                'description' => 'Standard VAT Item',
                'quantity' => '1',
                'unit_price' => '100.00',
                'tax_rate' => '22.00',  // Italian VAT
            ],
            [
                'description' => 'Reduced VAT Item',
                'quantity' => '1',
                'unit_price' => '100.00',
                'tax_rate' => '10.00',  // Reduced rate
            ],
            [
                'description' => 'Super Reduced VAT Item',
                'quantity' => '1',
                'unit_price' => '100.00',
                'tax_rate' => '4.00',   // Super reduced rate
            ],
            [
                'description' => 'Zero VAT Item',
                'quantity' => '1',
                'unit_price' => '100.00',
                'tax_rate' => '0.00',   // Zero rate
            ],
        ];

        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $this->customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: 'SALE-005'
        );

        // Subtotal: 4 * 100 = 400.00
        $this->assertEquals('400.00', $invoice->subtotal);

        // Tax: 22 + 10 + 4 + 0 = 36.00
        $this->assertEquals('36.00', $invoice->tax_amount);

        // Total: 400 + 36 = 436.00
        $this->assertEquals('436.00', $invoice->total_amount);
    }

    /**
     * Test that invoice can have lines with and without sellable_sku.
     */
    public function test_lines_with_and_without_sellable_sku(): void
    {
        $lines = [
            [
                'description' => 'Wine with SKU',
                'quantity' => '1',
                'unit_price' => '100.00',
                'tax_rate' => '22.00',
                'sellable_sku_id' => 301,
            ],
            [
                'description' => 'Handling Fee (no SKU)',
                'quantity' => '1',
                'unit_price' => '15.00',
                'tax_rate' => '22.00',
                'sellable_sku_id' => null,
            ],
            [
                'description' => 'Gift Wrapping (no SKU)',
                'quantity' => '1',
                'unit_price' => '10.00',
                'tax_rate' => '22.00',
                // sellable_sku_id not provided
            ],
        ];

        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $this->customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: 'SALE-006'
        );

        $createdLines = $invoice->invoiceLines()->orderBy('id')->get();

        // First line has SKU
        $this->assertEquals(301, $createdLines[0]->sellable_sku_id);
        $this->assertTrue($createdLines[0]->hasSellableSku());

        // Second line explicitly null
        $this->assertNull($createdLines[1]->sellable_sku_id);
        $this->assertFalse($createdLines[1]->hasSellableSku());

        // Third line omitted (should be null)
        $this->assertNull($createdLines[2]->sellable_sku_id);
        $this->assertFalse($createdLines[2]->hasSellableSku());
    }

    /**
     * Test metadata is preserved for each line independently.
     */
    public function test_metadata_preserved_for_each_line(): void
    {
        $lines = [
            [
                'description' => 'Product A',
                'quantity' => '1',
                'unit_price' => '100.00',
                'tax_rate' => '22.00',
                'metadata' => [
                    'sku_code' => 'SKU-A',
                    'pricing_snapshot_id' => 'SNAP-001',
                ],
            ],
            [
                'description' => 'Product B',
                'quantity' => '1',
                'unit_price' => '200.00',
                'tax_rate' => '22.00',
                'metadata' => [
                    'sku_code' => 'SKU-B',
                    'pricing_snapshot_id' => 'SNAP-002',
                    'discount_applied' => '10.00',
                ],
            ],
        ];

        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::VoucherSale,
            customer: $this->customer,
            lines: $lines,
            sourceType: 'voucher_sale',
            sourceId: 'SALE-007'
        );

        $createdLines = $invoice->invoiceLines()->orderBy('id')->get();

        // First line metadata
        $this->assertEquals('SKU-A', $createdLines[0]->getMetadataValue('sku_code'));
        $this->assertEquals('SNAP-001', $createdLines[0]->getPricingSnapshotId());

        // Second line metadata
        $this->assertEquals('SKU-B', $createdLines[1]->getMetadataValue('sku_code'));
        $this->assertEquals('SNAP-002', $createdLines[1]->getPricingSnapshotId());
        $this->assertEquals('10.00', $createdLines[1]->getMetadataValue('discount_applied'));
    }
}
