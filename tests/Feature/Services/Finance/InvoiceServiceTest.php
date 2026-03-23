<?php

namespace Tests\Feature\Services\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\OverpaymentHandling;
use App\Events\Finance\InvoicePaid;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceService::class);
        $this->customer = Customer::factory()->create();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Standard invoice lines for testing.
     *
     * @return list<array{description: string, quantity: string, unit_price: string, tax_rate: string, tax_amount: string}>
     */
    private function sampleLines(int $count = 1): array
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = [
                'description' => "Test item {$i}",
                'quantity' => '1',
                'unit_price' => '100.00',
                'tax_rate' => '22.00',
                'tax_amount' => '22.00',
            ];
        }

        return $lines;
    }

    // --- createDraft ---

    public function test_create_draft_happy_path(): void
    {
        $invoice = $this->service->createDraft(
            InvoiceType::VoucherSale,
            $this->customer,
            $this->sampleLines(2),
            'voucher_sale',
            'source-123',
        );

        $this->assertEquals(InvoiceStatus::Draft, $invoice->status);
        $this->assertEquals(InvoiceType::VoucherSale, $invoice->invoice_type);
        $this->assertEquals($this->customer->id, $invoice->customer_id);
        $this->assertEquals('EUR', $invoice->currency);
        $this->assertEquals(2, $invoice->invoiceLines()->count());
    }

    public function test_create_draft_idempotent(): void
    {
        $first = $this->service->createDraft(
            InvoiceType::VoucherSale,
            $this->customer,
            $this->sampleLines(),
            'voucher_sale',
            'source-dup',
        );

        $second = $this->service->createDraft(
            InvoiceType::VoucherSale,
            $this->customer,
            $this->sampleLines(),
            'voucher_sale',
            'source-dup',
        );

        $this->assertEquals($first->id, $second->id);
    }

    public function test_create_draft_validates_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->createDraft(
            InvoiceType::ServiceEvents,
            $this->customer,
            $this->sampleLines(),
            null,
            null,
            'XYZ',
        );
    }

    // --- issue ---

    public function test_issue_draft_to_issued(): void
    {
        $invoice = $this->service->createDraft(
            InvoiceType::ServiceEvents,
            $this->customer,
            $this->sampleLines(),
        );

        $issued = $this->service->issue($invoice);

        $this->assertEquals(InvoiceStatus::Issued, $issued->status);
        $this->assertNotNull($issued->invoice_number);
        $this->assertNotNull($issued->issued_at);
    }

    public function test_issue_sequential_number(): void
    {
        $inv1 = $this->service->createDraft(
            InvoiceType::ServiceEvents,
            $this->customer,
            $this->sampleLines(),
        );
        $inv2 = $this->service->createDraft(
            InvoiceType::ServiceEvents,
            $this->customer,
            $this->sampleLines(),
        );

        $issued1 = $this->service->issue($inv1);
        $issued2 = $this->service->issue($inv2);

        $this->assertNotEquals($issued1->invoice_number, $issued2->invoice_number);
        $this->assertStringStartsWith('INV-', (string) $issued1->invoice_number);
    }

    public function test_issue_rejects_non_draft(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not in draft status');

        $this->service->issue($invoice);
    }

    public function test_issue_rejects_no_lines(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one line');

        $this->service->issue($invoice);
    }

    // --- applyPayment ---

    public function test_apply_payment_happy_path(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '500.00',
            'currency' => 'EUR',
        ]);

        $payment = Payment::factory()->confirmed()->create([
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'currency' => 'EUR',
        ]);

        $invoicePayment = $this->service->applyPayment($invoice, $payment, '500.00');

        $this->assertEquals('500.00', $invoicePayment->amount_applied);
        $invoice->refresh();
        $this->assertEquals('500.00', $invoice->amount_paid);
    }

    public function test_apply_payment_rejects_non_payable(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft,
        ]);
        $payment = Payment::factory()->confirmed()->create([
            'currency' => 'EUR',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not accept payments');

        $this->service->applyPayment($invoice, $payment, '100.00');
    }

    public function test_apply_payment_rejects_currency_mismatch(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '500.00',
            'currency' => 'GBP',
        ]);
        $payment = Payment::factory()->confirmed()->create([
            'amount' => '500.00',
            'currency' => 'EUR',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('currency mismatch');

        $this->service->applyPayment($invoice, $payment, '500.00');
    }

    public function test_apply_payment_auto_paid(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '200.00',
            'currency' => 'EUR',
        ]);
        $payment = Payment::factory()->confirmed()->create([
            'customer_id' => $this->customer->id,
            'amount' => '200.00',
            'currency' => 'EUR',
        ]);

        $this->service->applyPayment($invoice, $payment, '200.00');

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_apply_payment_partial(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '1000.00',
            'currency' => 'EUR',
        ]);
        $payment = Payment::factory()->confirmed()->create([
            'customer_id' => $this->customer->id,
            'amount' => '400.00',
            'currency' => 'EUR',
        ]);

        $this->service->applyPayment($invoice, $payment, '400.00');

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertEquals('400.00', $invoice->amount_paid);
    }

    // --- applyPaymentWithOverpaymentHandling ---

    public function test_overpayment_apply_partial(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '300.00',
            'currency' => 'EUR',
        ]);
        $payment = Payment::factory()->confirmed()->create([
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'currency' => 'EUR',
        ]);

        $result = $this->service->applyPaymentWithOverpaymentHandling(
            $invoice,
            $payment,
            '500.00',
            OverpaymentHandling::ApplyPartial
        );

        $this->assertEquals('300.00', $result['amount_applied']);
        $this->assertNull($result['customer_credit']);
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_overpayment_detection(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '300.00',
            'currency' => 'EUR',
        ]);

        $this->assertTrue($this->service->isOverpayment($invoice, '500.00'));
        $this->assertFalse($this->service->isOverpayment($invoice, '200.00'));
        $this->assertEquals('200.00', $this->service->calculateOverpaymentAmount($invoice, '500.00'));
        $this->assertEquals('0.00', $this->service->calculateOverpaymentAmount($invoice, '200.00'));
    }

    // --- markPaid ---

    public function test_mark_paid_emits_event(): void
    {
        Event::fake([InvoicePaid::class]);

        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '100.00',
            'amount_paid' => '100.00',
        ]);

        $this->service->markPaid($invoice);

        Event::assertDispatched(InvoicePaid::class);
    }

    public function test_mark_paid_rejects_unpaid(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '500.00',
            'amount_paid' => '200.00',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outstanding balance');

        $this->service->markPaid($invoice);
    }

    // --- cancel ---

    public function test_cancel_draft_only(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only draft invoices');

        $this->service->cancel($invoice);
    }

    public function test_cancel_draft_succeeds(): void
    {
        $invoice = $this->service->createDraft(
            InvoiceType::ServiceEvents,
            $this->customer,
            $this->sampleLines(),
        );

        $cancelled = $this->service->cancel($invoice);

        $this->assertEquals(InvoiceStatus::Cancelled, $cancelled->status);
    }

    // --- addLines / recalculateTotals ---

    public function test_add_lines_recalculates(): void
    {
        $invoice = $this->service->createDraft(
            InvoiceType::ServiceEvents,
            $this->customer,
            [
                [
                    'description' => 'Item A',
                    'quantity' => '2',
                    'unit_price' => '50.00',
                    'tax_rate' => '22.00',
                    'tax_amount' => '22.00',
                ],
            ],
        );

        // subtotal = 2 * 50 = 100, tax = 22, total = 122
        $this->assertEquals('100.00', $invoice->subtotal);
        $this->assertEquals('22.00', $invoice->tax_amount);
        $this->assertEquals('122.00', $invoice->total_amount);
    }

    // --- getOutstandingAmount ---

    public function test_outstanding_amount(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'total_amount' => '750.00',
            'amount_paid' => '250.00',
        ]);

        $outstanding = $this->service->getOutstandingAmount($invoice);

        $this->assertEquals('500.00', $outstanding);
    }

    // --- findBySource ---

    public function test_find_by_source(): void
    {
        $invoice = $this->service->createDraft(
            InvoiceType::VoucherSale,
            $this->customer,
            $this->sampleLines(),
            'voucher_sale',
            'unique-source-id',
        );

        $found = $this->service->findBySource('voucher_sale', 'unique-source-id');

        $this->assertNotNull($found);
        $this->assertEquals($invoice->id, $found->id);
    }

    public function test_find_by_source_returns_null(): void
    {
        $found = $this->service->findBySource('nonexistent', 'missing-id');

        $this->assertNull($found);
    }

    // --- validateCurrency ---

    public function test_validate_currency_rejects_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->validateCurrency('INVALID');
    }

    public function test_validate_currency_accepts_supported(): void
    {
        // Should not throw
        $this->service->validateCurrency('EUR');
        $this->service->validateCurrency('GBP');
        $this->service->validateCurrency('USD');
        $this->addToAssertionCount(1);
    }
}
