<?php

namespace Tests\Feature\Services\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
        $this->customer = Customer::factory()->create(['stripe_customer_id' => 'cus_test123']);
    }

    // --- createBankPayment ---

    public function test_create_bank_payment_happy_path(): void
    {
        $payment = $this->service->createBankPayment(
            '750.00',
            'BNK-REF-001',
            $this->customer,
        );

        $this->assertEquals('750.00', $payment->amount);
        $this->assertEquals(PaymentSource::BankTransfer, $payment->source);
        $this->assertEquals(PaymentStatus::Confirmed, $payment->status);
        $this->assertEquals(ReconciliationStatus::Pending, $payment->reconciliation_status);
        $this->assertEquals('BNK-REF-001', $payment->bank_reference);
        $this->assertEquals($this->customer->id, $payment->customer_id);
        $this->assertStringStartsWith('BANK-', $payment->payment_reference);
    }

    public function test_create_bank_payment_without_customer(): void
    {
        $payment = $this->service->createBankPayment(
            '500.00',
            'BNK-UNMATCHED',
        );

        $this->assertEquals('500.00', $payment->amount);
        $this->assertNull($payment->customer_id);
    }

    public function test_create_bank_payment_rejects_zero_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        $this->service->createBankPayment('0.00', 'BNK-ZERO');
    }

    public function test_create_bank_payment_rejects_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->createBankPayment('-100.00', 'BNK-NEG');
    }

    // --- createFromStripe ---

    public function test_create_from_stripe_happy_path(): void
    {
        $paymentIntent = [
            'id' => 'pi_'.Str::random(24),
            'amount' => 15000,
            'currency' => 'eur',
            'customer' => 'cus_test123',
            'metadata' => [],
            'latest_charge' => 'ch_'.Str::random(24),
        ];

        $payment = $this->service->createFromStripe($paymentIntent);

        $this->assertEquals('150.00', $payment->amount);
        $this->assertEquals('EUR', $payment->currency);
        $this->assertEquals(PaymentSource::Stripe, $payment->source);
        $this->assertEquals(PaymentStatus::Confirmed, $payment->status);
        $this->assertEquals($paymentIntent['id'], $payment->stripe_payment_intent_id);
        $this->assertEquals($this->customer->id, $payment->customer_id);
    }

    public function test_create_from_stripe_idempotency(): void
    {
        $paymentIntent = [
            'id' => 'pi_idempotent_test',
            'amount' => 10000,
            'currency' => 'eur',
            'customer' => null,
            'metadata' => [],
            'latest_charge' => null,
        ];

        $first = $this->service->createFromStripe($paymentIntent);
        $second = $this->service->createFromStripe($paymentIntent);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_create_from_stripe_auto_reconciles_matching_invoice(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '250.00',
            'currency' => 'EUR',
        ]);

        $paymentIntent = [
            'id' => 'pi_'.Str::random(24),
            'amount' => 25000,
            'currency' => 'eur',
            'customer' => 'cus_test123',
            'metadata' => [],
            'latest_charge' => 'ch_'.Str::random(24),
        ];

        $payment = $this->service->createFromStripe($paymentIntent);

        $payment->refresh();
        $this->assertEquals(ReconciliationStatus::Matched, $payment->reconciliation_status);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    // --- applyToInvoice ---

    public function test_apply_to_invoice_happy_path(): void
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

        $invoicePayment = $this->service->applyToInvoice($payment, $invoice, '500.00');

        $this->assertEquals('500.00', $invoicePayment->amount_applied);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_apply_to_invoice_partial_payment(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '1000.00',
            'currency' => 'EUR',
        ]);

        $payment = Payment::factory()->confirmed()->create([
            'customer_id' => $this->customer->id,
            'amount' => '600.00',
            'currency' => 'EUR',
        ]);

        $this->service->applyToInvoice($payment, $invoice, '600.00');

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertEquals('600.00', $invoice->amount_paid);
    }

    public function test_apply_to_invoice_rejects_currency_mismatch(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '500.00',
            'currency' => 'GBP',
        ]);

        $payment = Payment::factory()->confirmed()->create([
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'currency' => 'EUR',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch');

        $this->service->applyToInvoice($payment, $invoice, '500.00');
    }

    public function test_apply_to_invoice_rejects_overpayment(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '200.00',
            'currency' => 'EUR',
        ]);

        $payment = Payment::factory()->confirmed()->create([
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'currency' => 'EUR',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds outstanding');

        $this->service->applyToInvoice($payment, $invoice, '300.00');
    }

    public function test_apply_to_invoice_rejects_pending_payment(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '500.00',
            'currency' => 'EUR',
        ]);

        $payment = Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'amount' => '500.00',
            'currency' => 'EUR',
            'status' => PaymentStatus::Pending,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be applied');

        $this->service->applyToInvoice($payment, $invoice, '500.00');
    }
}
