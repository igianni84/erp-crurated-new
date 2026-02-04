<?php

namespace Tests\Unit\Models\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tests for Invoice immutability enforcement.
 *
 * These tests verify that:
 * 1. invoice_type cannot be modified EVER (not even in draft)
 * 2. invoice_lines cannot be modified after status != draft
 * 3. subtotal, tax_amount, total_amount cannot be modified after issuance
 * 4. Attempts to modify throw explicit exceptions
 */
class InvoiceImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'status' => 'active',
        ]);
    }

    /**
     * Test that invoice_type cannot be modified after creation (even in draft).
     */
    public function test_invoice_type_is_immutable_in_draft(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Draft,
        ]);

        // Attempt to change invoice_type should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invoice_type cannot be modified after creation');

        $invoice->invoice_type = InvoiceType::StorageFee;
        $invoice->save();
    }

    /**
     * Test that invoice_type cannot be modified after issuance.
     */
    public function test_invoice_type_is_immutable_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        // Attempt to change invoice_type should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invoice_type cannot be modified');

        $invoice->invoice_type = InvoiceType::StorageFee;
        $invoice->save();
    }

    /**
     * Test that subtotal cannot be modified after issuance.
     */
    public function test_subtotal_is_immutable_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        // Attempt to change subtotal should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'subtotal' cannot be modified after invoice is issued");

        $invoice->subtotal = '150.00';
        $invoice->save();
    }

    /**
     * Test that tax_amount cannot be modified after issuance.
     */
    public function test_tax_amount_is_immutable_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        // Attempt to change tax_amount should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'tax_amount' cannot be modified after invoice is issued");

        $invoice->tax_amount = '30.00';
        $invoice->save();
    }

    /**
     * Test that total_amount cannot be modified after issuance.
     */
    public function test_total_amount_is_immutable_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        // Attempt to change total_amount should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'total_amount' cannot be modified after invoice is issued");

        $invoice->total_amount = '180.00';
        $invoice->save();
    }

    /**
     * Test that currency cannot be modified after issuance.
     */
    public function test_currency_is_immutable_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        // Attempt to change currency should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'currency' cannot be modified after invoice is issued");

        $invoice->currency = 'GBP';
        $invoice->save();
    }

    /**
     * Test that amounts CAN be modified in draft status.
     */
    public function test_amounts_can_be_modified_in_draft(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Draft,
        ]);

        // Should not throw exception
        $invoice->subtotal = '150.00';
        $invoice->tax_amount = '30.00';
        $invoice->total_amount = '180.00';
        $invoice->currency = 'GBP';
        $invoice->save();

        $invoice->refresh();

        $this->assertEquals('150.00', $invoice->subtotal);
        $this->assertEquals('30.00', $invoice->tax_amount);
        $this->assertEquals('180.00', $invoice->total_amount);
        $this->assertEquals('GBP', $invoice->currency);
    }

    /**
     * Test that other fields (like amount_paid) can be modified after issuance.
     */
    public function test_non_immutable_fields_can_be_modified_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        // These fields should be modifiable after issuance
        $invoice->amount_paid = '60.00';
        $invoice->status = InvoiceStatus::PartiallyPaid;
        $invoice->notes = 'Updated notes';
        $invoice->save();

        $invoice->refresh();

        $this->assertEquals('60.00', $invoice->amount_paid);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertEquals('Updated notes', $invoice->notes);
    }

    /**
     * Test that invoice lines cannot be modified after invoice issuance.
     */
    public function test_invoice_lines_immutable_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'tax_rate' => '20.00',
            'tax_amount' => '20.00',
            'line_total' => '120.00',
        ]);

        // Attempt to modify line should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice lines cannot be modified after invoice is issued');

        $line->description = 'Modified Item';
        $line->save();
    }

    /**
     * Test that invoice lines cannot be deleted after invoice issuance.
     */
    public function test_invoice_lines_cannot_be_deleted_after_issuance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'tax_rate' => '20.00',
            'tax_amount' => '20.00',
            'line_total' => '120.00',
        ]);

        // Attempt to delete line should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice lines cannot be deleted after invoice is issued');

        $line->delete();
    }

    /**
     * Test that invoice lines CAN be modified in draft status.
     */
    public function test_invoice_lines_can_be_modified_in_draft(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Draft,
        ]);

        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'tax_rate' => '20.00',
            'tax_amount' => '20.00',
            'line_total' => '120.00',
        ]);

        // Should not throw exception - draft invoices can have lines modified
        $line->description = 'Modified Item';
        $line->quantity = '2.00';
        $line->save();

        $line->refresh();

        $this->assertEquals('Modified Item', $line->description);
        $this->assertEquals('2.00', $line->quantity);
    }

    /**
     * Test that invoice lines CAN be deleted in draft status.
     */
    public function test_invoice_lines_can_be_deleted_in_draft(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Draft,
        ]);

        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'tax_rate' => '20.00',
            'tax_amount' => '20.00',
            'line_total' => '120.00',
        ]);

        $lineId = $line->id;

        // Should not throw exception - draft invoice lines can be deleted
        $line->delete();

        $this->assertNull(InvoiceLine::find($lineId));
    }

    /**
     * Test that exception message for invoice type includes guidance.
     */
    public function test_invoice_type_exception_message_is_explicit(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Draft,
        ]);

        try {
            $invoice->invoice_type = InvoiceType::StorageFee;
            $invoice->save();
            $this->fail('Expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('invoice_type cannot be modified', $e->getMessage());
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    /**
     * Test that exception message for amounts includes credit note guidance.
     */
    public function test_amount_exception_message_includes_credit_note_guidance(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000001',
            'issued_at' => now(),
        ]);

        try {
            $invoice->subtotal = '200.00';
            $invoice->save();
            $this->fail('Expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be modified after invoice is issued', $e->getMessage());
            $this->assertStringContainsString('credit notes', $e->getMessage());
        }
    }

    /**
     * Test canBeEdited method reflects immutability status.
     */
    public function test_can_be_edited_returns_correct_status(): void
    {
        $draftInvoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Draft,
        ]);

        $issuedInvoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000002',
            'issued_at' => now(),
        ]);

        $this->assertTrue($draftInvoice->canBeEdited());
        $this->assertFalse($issuedInvoice->canBeEdited());
    }

    /**
     * Test InvoiceLine canBeEdited method reflects parent invoice status.
     */
    public function test_invoice_line_can_be_edited_reflects_parent_status(): void
    {
        $draftInvoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Draft,
        ]);

        $issuedInvoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-2026-000003',
            'issued_at' => now(),
        ]);

        $draftLine = InvoiceLine::create([
            'invoice_id' => $draftInvoice->id,
            'description' => 'Draft Line',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'tax_rate' => '20.00',
            'tax_amount' => '20.00',
            'line_total' => '120.00',
        ]);

        $issuedLine = InvoiceLine::create([
            'invoice_id' => $issuedInvoice->id,
            'description' => 'Issued Line',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'tax_rate' => '20.00',
            'tax_amount' => '20.00',
            'line_total' => '120.00',
        ]);

        $this->assertTrue($draftLine->canBeEdited());
        $this->assertFalse($issuedLine->canBeEdited());
    }
}
