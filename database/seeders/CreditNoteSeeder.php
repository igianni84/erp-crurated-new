<?php

namespace Database\Seeders;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\RefundMethod;
use App\Enums\Finance\RefundStatus;
use App\Enums\Finance\RefundType;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Refund;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * CreditNoteSeeder - Creates credit notes and refunds
 *
 * Credit notes are formal corrections to invoices with mandatory reasons.
 * Refunds are reimbursements linked to both invoice and payment.
 */
class CreditNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get paid invoices for credit notes
        $paidInvoices = Invoice::where('status', InvoiceStatus::Paid)
            ->with(['customer', 'lines', 'payments'])
            ->get();

        if ($paidInvoices->isEmpty()) {
            $this->command->warn('No paid invoices found. Run InvoiceSeeder first.');

            return;
        }

        // Get admin user
        $admin = User::first();

        // Create credit notes for ~10% of paid invoices
        $invoicesForCreditNotes = $paidInvoices->random(max(1, (int) ($paidInvoices->count() * 0.1)));

        foreach ($invoicesForCreditNotes as $invoice) {
            $this->createCreditNote($invoice, $admin);
        }

        // Note: Refund creation is skipped because the Refund model has a
        // migration/model inconsistency (uses HasUuid trait but table has integer ID).
        // Credit notes are created successfully and refunds can be added manually
        // or after fixing the Refund model.
        //
        // The Refund table uses $table->id() (integer) + $table->uuid('uuid')
        // but the model uses HasUuid trait which expects $table->uuid('id')->primary()
        $this->command->info('Credit notes created. Refund seeding skipped due to model/migration inconsistency.');
    }

    /**
     * Create a credit note for an invoice.
     */
    private function createCreditNote(Invoice $invoice, $admin): void
    {
        // Determine credit note reason
        $reasons = [
            'Price adjustment due to pricing error',
            'Partial order cancellation by customer',
            'Wine quality issue - replacement provided',
            'Duplicate charge correction',
            'Promotional discount applied retrospectively',
            'Shipping damage - partial refund',
            'Customer goodwill gesture',
            'Incorrect quantity billed',
            'Return of unopened merchandise',
        ];

        $reason = fake()->randomElement($reasons);

        // Determine credit amount (10-100% of invoice total)
        $creditPercentage = fake()->randomFloat(2, 0.10, 1.00);
        $creditAmount = bcmul($invoice->total_amount, (string) $creditPercentage, 2);

        // Determine status: 20% draft, 40% issued, 40% applied
        // CreditNoteStatus: Draft, Issued, Applied
        $statusRandom = fake()->numberBetween(1, 100);
        $status = match (true) {
            $statusRandom <= 20 => CreditNoteStatus::Draft,
            $statusRandom <= 60 => CreditNoteStatus::Issued,
            default => CreditNoteStatus::Applied,
        };

        // Generate credit note number
        $creditNoteNumber = 'CN-'.date('Y').'-'.str_pad(CreditNote::count() + 1, 6, '0', STR_PAD_LEFT);

        CreditNote::create([
            'credit_note_number' => $creditNoteNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $creditAmount,
            'currency' => $invoice->currency ?? 'EUR',
            'reason' => $reason,
            'status' => $status,
            'issued_at' => in_array($status, [CreditNoteStatus::Issued, CreditNoteStatus::Applied])
                ? now()->subDays(fake()->numberBetween(7, 30))
                : null,
            'issued_by' => in_array($status, [CreditNoteStatus::Issued, CreditNoteStatus::Applied])
                ? $admin?->id
                : null,
            'applied_at' => $status === CreditNoteStatus::Applied
                ? now()->subDays(fake()->numberBetween(1, 7))
                : null,
            'xero_credit_note_id' => $status !== CreditNoteStatus::Draft && fake()->boolean(40)
                ? 'XCN-'.fake()->regexify('[A-F0-9]{8}')
                : null,
            'xero_synced_at' => $status !== CreditNoteStatus::Draft && fake()->boolean(30)
                ? now()->subDays(fake()->numberBetween(1, 7))
                : null,
        ]);
    }

    /**
     * Create a refund for a credit note.
     *
     * Note: Refund model requires both invoice_id and payment_id,
     * and validates that the payment is applied to the invoice via InvoicePayment.
     */
    private function createRefund(CreditNote $creditNote, $admin): void
    {
        $invoice = $creditNote->invoice;

        if (! $invoice) {
            return;
        }

        // Get the payment associated with this invoice via InvoicePayment
        $invoicePayment = InvoicePayment::where('invoice_id', $invoice->id)->first();

        if (! $invoicePayment) {
            // Cannot create refund without a valid invoice-payment link
            return;
        }

        $paymentId = $invoicePayment->payment_id;

        // Determine refund type
        $refundType = fake()->randomElement([
            RefundType::Full,
            RefundType::Partial,
        ]);

        // Determine refund amount - cannot exceed the amount applied
        $maxAmount = min($creditNote->amount, $invoicePayment->amount_applied);
        $refundAmount = $refundType === RefundType::Full
            ? $maxAmount
            : bcmul($maxAmount, (string) fake()->randomFloat(2, 0.3, 0.9), 2);

        // Determine refund method
        // RefundMethod: Stripe, BankTransfer
        $refundMethod = fake()->randomElement([
            RefundMethod::Stripe,
            RefundMethod::BankTransfer,
        ]);

        // Determine status: 60% processed, 30% pending, 10% failed
        // RefundStatus: Pending, Processed, Failed
        $statusRandom = fake()->numberBetween(1, 100);
        $status = match (true) {
            $statusRandom <= 60 => RefundStatus::Processed,
            $statusRandom <= 90 => RefundStatus::Pending,
            default => RefundStatus::Failed,
        };

        Refund::create([
            'invoice_id' => $invoice->id,
            'payment_id' => $paymentId,
            'credit_note_id' => $creditNote->id,
            'refund_type' => $refundType,
            'method' => $refundMethod,
            'amount' => $refundAmount,
            'currency' => $creditNote->currency ?? 'EUR',
            'reason' => $creditNote->reason,
            'status' => $status,
            'stripe_refund_id' => $refundMethod === RefundMethod::Stripe && $status === RefundStatus::Processed
                ? 're_'.fake()->regexify('[A-Za-z0-9]{24}')
                : null,
            'bank_reference' => $refundMethod === RefundMethod::BankTransfer && $status === RefundStatus::Processed
                ? 'BT-'.fake()->regexify('[A-Z0-9]{12}')
                : null,
            'processed_at' => in_array($status, [RefundStatus::Processed, RefundStatus::Failed])
                ? now()->subDays(fake()->numberBetween(1, 7))
                : null,
            'processed_by' => in_array($status, [RefundStatus::Processed, RefundStatus::Failed])
                ? $admin?->id
                : null,
        ]);
    }
}
