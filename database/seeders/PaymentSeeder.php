<?php

namespace Database\Seeders;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Payment;
use Illuminate\Database\Seeder;

/**
 * PaymentSeeder - Creates payment records linked to paid invoices
 */
class PaymentSeeder extends Seeder
{
    private int $paymentCounter = 1;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get paid and partially paid invoices
        $paidInvoices = Invoice::whereIn('status', [
            InvoiceStatus::Paid,
            InvoiceStatus::PartiallyPaid,
        ])->with('customer')->get();

        foreach ($paidInvoices as $invoice) {
            if (! $invoice->customer) {
                continue;
            }

            $isPaidInFull = $invoice->status === InvoiceStatus::Paid;
            $isPartial = $invoice->status === InvoiceStatus::PartiallyPaid;

            // Determine payment source (70% Stripe, 30% Bank Transfer)
            $source = fake()->boolean(70) ? PaymentSource::Stripe : PaymentSource::BankTransfer;

            // Calculate payment amount
            $paymentAmount = $isPaidInFull ? $invoice->total_amount : $invoice->amount_paid;

            // Create payment
            $receivedAt = $invoice->issued_at
                ? (clone $invoice->issued_at)->modify('+'.fake()->numberBetween(1, 14).' days')
                : now()->subDays(fake()->numberBetween(1, 30));

            $payment = Payment::create([
                'payment_reference' => $this->generatePaymentReference($source),
                'source' => $source,
                'amount' => $paymentAmount,
                'currency' => $invoice->currency,
                'status' => PaymentStatus::Confirmed,
                'reconciliation_status' => ReconciliationStatus::Matched,
                'stripe_payment_intent_id' => $source === PaymentSource::Stripe
                    ? 'pi_'.fake()->regexify('[A-Za-z0-9]{24}')
                    : null,
                'stripe_charge_id' => $source === PaymentSource::Stripe
                    ? 'ch_'.fake()->regexify('[A-Za-z0-9]{24}')
                    : null,
                'bank_reference' => $source === PaymentSource::BankTransfer
                    ? 'BNK-'.fake()->numerify('#########')
                    : null,
                'received_at' => $receivedAt,
                'customer_id' => $invoice->customer_id,
                'metadata' => [
                    'invoice_number' => $invoice->invoice_number,
                    'auto_reconciled' => $source === PaymentSource::Stripe,
                ],
            ]);

            // Create invoice-payment link
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount_applied' => $paymentAmount,
                'applied_at' => $receivedAt,
            ]);
        }

        // Create some unreconciled/mismatched payments for testing
        $this->createMismatchedPayments();

        // Create some pending payments
        $this->createPendingPayments();
    }

    /**
     * Create mismatched payments for testing reconciliation flows
     */
    private function createMismatchedPayments(): void
    {
        // Get some customers with issued invoices
        $issuedInvoices = Invoice::where('status', InvoiceStatus::Issued)
            ->with('customer')
            ->take(3)
            ->get();

        foreach ($issuedInvoices as $index => $invoice) {
            if (! $invoice->customer) {
                continue;
            }

            $source = $index === 0 ? PaymentSource::BankTransfer : PaymentSource::Stripe;

            // Create payment with slightly different amount (mismatch)
            $mismatchAmount = bcadd($invoice->total_amount, fake()->randomElement(['-5.00', '10.00', '-2.50']), 2);

            $mismatchType = match ($index) {
                0 => Payment::MISMATCH_AMOUNT_DIFFERENCE,
                1 => Payment::MISMATCH_NO_MATCH,
                default => Payment::MISMATCH_CUSTOMER_MISMATCH,
            };

            $payment = Payment::create([
                'payment_reference' => $this->generatePaymentReference($source),
                'source' => $source,
                'amount' => $mismatchAmount,
                'currency' => $invoice->currency,
                'status' => PaymentStatus::Confirmed,
                'reconciliation_status' => ReconciliationStatus::Mismatched,
                'stripe_payment_intent_id' => $source === PaymentSource::Stripe
                    ? 'pi_'.fake()->regexify('[A-Za-z0-9]{24}')
                    : null,
                'bank_reference' => $source === PaymentSource::BankTransfer
                    ? 'BNK-'.fake()->numerify('#########')
                    : null,
                'received_at' => now()->subDays(fake()->numberBetween(1, 7)),
                'customer_id' => $invoice->customer_id,
                'metadata' => [
                    'mismatch_reason' => "Payment amount €{$mismatchAmount} does not match invoice total €{$invoice->total_amount}",
                    'mismatch_details' => [
                        'reason' => $mismatchType,
                        'expected_amount' => $invoice->total_amount,
                        'actual_amount' => $mismatchAmount,
                        'difference' => bcsub($mismatchAmount, $invoice->total_amount, 2),
                        'related_invoice' => $invoice->invoice_number,
                    ],
                    'requires_manual_review' => true,
                ],
            ]);
        }
    }

    /**
     * Create pending payments for testing
     */
    private function createPendingPayments(): void
    {
        // Create 2 pending Stripe payments
        for ($i = 0; $i < 2; $i++) {
            Payment::create([
                'payment_reference' => $this->generatePaymentReference(PaymentSource::Stripe),
                'source' => PaymentSource::Stripe,
                'amount' => fake()->randomFloat(2, 100, 1500),
                'currency' => 'EUR',
                'status' => PaymentStatus::Pending,
                'reconciliation_status' => ReconciliationStatus::Pending,
                'stripe_payment_intent_id' => 'pi_'.fake()->regexify('[A-Za-z0-9]{24}'),
                'received_at' => now()->subMinutes(fake()->numberBetween(5, 60)),
                'customer_id' => null, // Customer not yet identified
                'metadata' => [
                    'awaiting_webhook_confirmation' => true,
                ],
            ]);
        }

        // Create 1 failed payment
        Payment::create([
            'payment_reference' => $this->generatePaymentReference(PaymentSource::Stripe),
            'source' => PaymentSource::Stripe,
            'amount' => '450.00',
            'currency' => 'EUR',
            'status' => PaymentStatus::Failed,
            'reconciliation_status' => ReconciliationStatus::Pending,
            'stripe_payment_intent_id' => 'pi_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'received_at' => now()->subHours(2),
            'customer_id' => null,
            'metadata' => [
                'failure_reason' => 'card_declined',
                'failure_message' => 'Your card was declined.',
            ],
        ]);
    }

    /**
     * Generate payment reference
     */
    private function generatePaymentReference(PaymentSource $source): string
    {
        $prefix = $source === PaymentSource::Stripe ? 'STR' : 'BNK';
        $number = str_pad((string) $this->paymentCounter, 8, '0', STR_PAD_LEFT);
        $this->paymentCounter++;

        return $prefix.'-'.date('Ymd').'-'.$number;
    }
}
