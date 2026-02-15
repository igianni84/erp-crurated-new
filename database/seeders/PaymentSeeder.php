<?php

namespace Database\Seeders;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * PaymentSeeder - Creates payments via PaymentService and applies them to invoices.
 *
 * Uses PaymentService::createFromStripe() and createBankPayment().
 * Uses InvoiceService::applyPayment() to link payments to invoices.
 * createFromStripe() auto-reconciles if matching invoice found.
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $paymentService = app(PaymentService::class);
        $invoiceService = app(InvoiceService::class);

        // Get issued invoices (ready for payment)
        $issuedInvoices = Invoice::where('status', InvoiceStatus::Issued)
            ->with('customer')
            ->get();

        if ($issuedInvoices->isEmpty()) {
            $this->command->warn('No issued invoices found. Run InvoiceSeeder first.');

            return;
        }

        $totalPaid = 0;

        // Pay ~70% of issued invoices
        $invoicesToPay = $issuedInvoices->random(max(1, (int) ($issuedInvoices->count() * 0.70)));

        foreach ($invoicesToPay as $invoice) {
            if (! $invoice->customer) {
                continue;
            }

            // 70% Stripe, 30% Bank
            $useStripe = fake()->boolean(70);

            try {
                if ($useStripe) {
                    // Create Stripe payment — amount in CENTS
                    $amountCents = (int) bcmul($invoice->total_amount, '100', 0);
                    $paymentIntent = [
                        'id' => 'pi_'.Str::random(24),
                        'amount' => $amountCents,
                        'currency' => strtolower($invoice->currency),
                        'customer' => $invoice->customer->stripe_customer_id,
                        'metadata' => ['invoice_id' => $invoice->id],
                        'latest_charge' => 'ch_'.Str::random(24),
                    ];
                    $payment = $paymentService->createFromStripe($paymentIntent);
                } else {
                    // Create Bank payment — amount as string
                    $bankReference = 'BNK-'.fake()->numerify('#########');
                    $payment = $paymentService->createBankPayment(
                        $invoice->total_amount,
                        $bankReference,
                        $invoice->customer,
                        $invoice->currency,
                        now()->subDays(fake()->numberBetween(1, 14)),
                    );
                }

                // Apply payment to invoice
                $invoiceService->applyPayment($invoice, $payment, $invoice->total_amount);
                $totalPaid++;
            } catch (\Throwable $e) {
                $this->command->warn("Payment failed for invoice {$invoice->id}: {$e->getMessage()}");
            }
        }

        // Create mismatched payments for testing reconciliation
        $this->createMismatchedPayments($paymentService, $issuedInvoices);

        $this->command->info("Created {$totalPaid} payments applied to invoices.");
    }

    private function createMismatchedPayments(PaymentService $paymentService, $issuedInvoices): void
    {
        $unmatchedInvoices = $issuedInvoices->filter(fn ($inv) => $inv->status === InvoiceStatus::Issued)->take(2);

        foreach ($unmatchedInvoices as $invoice) {
            if (! $invoice->customer) {
                continue;
            }

            $bankRef = 'BNK-MISMATCH-'.fake()->numerify('######');
            // Payment with slightly wrong amount
            $wrongAmount = bcadd($invoice->total_amount, fake()->randomElement(['-5.00', '10.00']), 2);

            try {
                $payment = $paymentService->createBankPayment(
                    $wrongAmount,
                    $bankRef,
                    $invoice->customer,
                    $invoice->currency,
                    now()->subDays(fake()->numberBetween(1, 5)),
                );

                $paymentService->markAsMismatched(
                    $payment,
                    Payment::MISMATCH_AMOUNT_DIFFERENCE,
                    "Amount {$wrongAmount} does not match invoice total {$invoice->total_amount}",
                    [
                        'expected_amount' => $invoice->total_amount,
                        'actual_amount' => $wrongAmount,
                        'related_invoice' => $invoice->invoice_number,
                    ],
                );
            } catch (\Throwable $e) {
                $this->command->warn("Mismatch payment failed: {$e->getMessage()}");
            }
        }
    }
}
