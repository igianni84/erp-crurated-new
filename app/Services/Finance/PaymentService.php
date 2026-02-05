<?php

namespace App\Services\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Payment;
use App\Models\Finance\StripeWebhook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for managing Payment lifecycle and operations.
 *
 * Centralizes all payment business logic including creation from Stripe,
 * manual bank payment entry, reconciliation, and application to invoices.
 *
 * Auto-reconciliation for Stripe payments:
 * - On payment_intent.succeeded webhook, creates Payment with status=confirmed
 * - Attempts to find a matching invoice by amount and customer
 * - If unique match found: applies payment, sets reconciliation_status=matched
 * - If no match or multiple matches: sets reconciliation_status=pending
 */
class PaymentService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Create a payment from Stripe PaymentIntent data.
     *
     * Called when processing payment_intent.succeeded webhook.
     * Creates Payment with status=confirmed and attempts auto-reconciliation.
     *
     * @param  array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     customer?: string|null,
     *     metadata?: array<string, mixed>,
     *     latest_charge?: string|null
     * }  $paymentIntent  The Stripe PaymentIntent data
     * @param  StripeWebhook|null  $webhook  Optional webhook record for logging
     *
     * @throws InvalidArgumentException If payment intent ID is already recorded
     */
    public function createFromStripe(array $paymentIntent, ?StripeWebhook $webhook = null): Payment
    {
        $paymentIntentId = $paymentIntent['id'];

        // Idempotency check - return existing payment if already processed
        $existing = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if ($existing !== null) {
            Log::channel('finance')->info('Payment already exists for PaymentIntent', [
                'payment_intent_id' => $paymentIntentId,
                'payment_id' => $existing->id,
            ]);

            return $existing;
        }

        return DB::transaction(function () use ($paymentIntent, $webhook): Payment {
            $paymentIntentId = $paymentIntent['id'];
            $amountCents = $paymentIntent['amount'];
            $currency = strtoupper($paymentIntent['currency']);
            $stripeCustomerId = $paymentIntent['customer'] ?? null;
            $chargeId = $paymentIntent['latest_charge'] ?? null;
            $metadata = $paymentIntent['metadata'] ?? [];

            // Convert amount from cents to currency units
            $amount = bcdiv((string) $amountCents, '100', 2);

            // Try to find customer by Stripe customer ID
            $customer = null;
            if ($stripeCustomerId !== null) {
                $customer = $this->findCustomerByStripeId($stripeCustomerId);
            }

            // Generate payment reference
            $paymentReference = $this->generatePaymentReference('STRIPE');

            // Create the payment
            $payment = Payment::create([
                'payment_reference' => $paymentReference,
                'source' => PaymentSource::Stripe,
                'amount' => $amount,
                'currency' => $currency,
                'status' => PaymentStatus::Confirmed,
                'reconciliation_status' => ReconciliationStatus::Pending,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_charge_id' => $chargeId,
                'received_at' => now(),
                'customer_id' => $customer?->id,
                'metadata' => array_merge($metadata, [
                    'stripe_customer_id' => $stripeCustomerId,
                    'webhook_event_id' => $webhook?->event_id,
                ]),
            ]);

            // Log creation
            $this->logPaymentEvent(
                $payment,
                AuditLog::EVENT_CREATED,
                [],
                [
                    'source' => PaymentSource::Stripe->value,
                    'amount' => $amount,
                    'currency' => $currency,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'customer_id' => $customer?->id,
                ]
            );

            Log::channel('finance')->info('Payment created from Stripe', [
                'payment_id' => $payment->id,
                'payment_reference' => $paymentReference,
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amount,
                'currency' => $currency,
                'customer_id' => $customer?->id,
            ]);

            // Attempt auto-reconciliation
            $this->autoReconcile($payment);

            return $payment->fresh();
        });
    }

    /**
     * Create a bank transfer payment manually.
     *
     * Used by finance operators to record bank payments.
     *
     * @param  string  $amount  The payment amount
     * @param  string  $bankReference  The bank reference number
     * @param  Customer|null  $customer  The customer (optional for unreconciled)
     * @param  string  $currency  Currency code (default: EUR)
     * @param  \Carbon\Carbon|null  $receivedAt  When the payment was received
     *
     * @throws InvalidArgumentException If amount is not positive
     */
    public function createBankPayment(
        string $amount,
        string $bankReference,
        ?Customer $customer = null,
        string $currency = 'EUR',
        ?\Carbon\Carbon $receivedAt = null
    ): Payment {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException(
                'Payment amount must be greater than zero.'
            );
        }

        return DB::transaction(function () use ($amount, $bankReference, $customer, $currency, $receivedAt): Payment {
            $paymentReference = $this->generatePaymentReference('BANK');

            $payment = Payment::create([
                'payment_reference' => $paymentReference,
                'source' => PaymentSource::BankTransfer,
                'amount' => $amount,
                'currency' => $currency,
                'status' => PaymentStatus::Confirmed,
                'reconciliation_status' => ReconciliationStatus::Pending,
                'bank_reference' => $bankReference,
                'received_at' => $receivedAt ?? now(),
                'customer_id' => $customer?->id,
                'metadata' => [
                    'entered_by' => Auth::id(),
                    'entered_at' => now()->toIso8601String(),
                ],
            ]);

            // Log creation
            $this->logPaymentEvent(
                $payment,
                AuditLog::EVENT_CREATED,
                [],
                [
                    'source' => PaymentSource::BankTransfer->value,
                    'amount' => $amount,
                    'currency' => $currency,
                    'bank_reference' => $bankReference,
                    'customer_id' => $customer?->id,
                ]
            );

            Log::channel('finance')->info('Bank payment created', [
                'payment_id' => $payment->id,
                'payment_reference' => $paymentReference,
                'bank_reference' => $bankReference,
                'amount' => $amount,
                'currency' => $currency,
                'customer_id' => $customer?->id,
            ]);

            return $payment;
        });
    }

    /**
     * Apply a payment to an invoice.
     *
     * Creates an InvoicePayment record linking the payment to the invoice.
     * Updates both payment and invoice states accordingly.
     *
     * @param  string  $amount  The amount to apply from the payment
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function applyToInvoice(Payment $payment, Invoice $invoice, string $amount): InvoicePayment
    {
        // Validate amount is positive
        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException(
                'Amount to apply must be greater than zero.'
            );
        }

        // Validate payment can be applied
        if (! $payment->canBeAppliedToInvoice()) {
            throw new InvalidArgumentException(
                "Payment with status '{$payment->status->label()}' cannot be applied to invoices."
            );
        }

        // Validate currencies match
        if ($payment->currency !== $invoice->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: payment is in {$payment->currency}, invoice is in {$invoice->currency}."
            );
        }

        // Validate amount doesn't exceed unapplied payment amount
        $unapplied = $payment->getUnappliedAmount();
        if (bccomp($amount, $unapplied, 2) > 0) {
            throw new InvalidArgumentException(
                "Amount ({$amount}) exceeds unapplied payment balance ({$unapplied})."
            );
        }

        // Use InvoiceService to apply the payment (handles invoice-side validation)
        $invoicePayment = $this->invoiceService->applyPayment($invoice, $payment, $amount);

        // Log the application on payment side
        $this->logPaymentEvent(
            $payment,
            'applied_to_invoice',
            [],
            [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount_applied' => $amount,
            ]
        );

        return $invoicePayment;
    }

    /**
     * Attempt automatic reconciliation of a payment.
     *
     * Auto-reconciliation logic:
     * 1. Payment must have a customer associated
     * 2. Find open invoices for customer with matching amount
     * 3. If exactly one match found: apply payment, set reconciliation_status=matched
     * 4. If no match or multiple matches: leave reconciliation_status=pending
     *
     * @return bool True if auto-reconciliation was successful
     */
    public function autoReconcile(Payment $payment): bool
    {
        // Can only auto-reconcile Stripe payments that are pending reconciliation
        if (! $payment->isFromStripe()) {
            Log::channel('finance')->debug('Auto-reconcile skipped: not a Stripe payment', [
                'payment_id' => $payment->id,
                'source' => $payment->source->value,
            ]);

            return false;
        }

        if ($payment->reconciliation_status !== ReconciliationStatus::Pending) {
            Log::channel('finance')->debug('Auto-reconcile skipped: not pending reconciliation', [
                'payment_id' => $payment->id,
                'reconciliation_status' => $payment->reconciliation_status->value,
            ]);

            return false;
        }

        // Must have a customer to auto-reconcile
        if ($payment->customer_id === null) {
            Log::channel('finance')->info('Auto-reconcile: no customer, leaving as pending', [
                'payment_id' => $payment->id,
            ]);

            $payment->setMismatchInfo('Customer not identified from Stripe', [
                'reason' => 'no_customer',
                'stripe_customer_id' => $payment->metadata['stripe_customer_id'] ?? null,
            ]);
            $payment->save();

            return false;
        }

        // Find matching invoices: same customer, same amount, same currency, issued/partially_paid status
        $matchingInvoices = $this->findMatchingInvoices($payment);

        $matchCount = $matchingInvoices->count();

        if ($matchCount === 0) {
            // No matching invoice found
            Log::channel('finance')->info('Auto-reconcile: no matching invoice found', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);

            $payment->setMismatchInfo('No invoice found matching amount and customer', [
                'reason' => 'no_match',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);
            $payment->save();

            return false;
        }

        if ($matchCount > 1) {
            // Multiple matching invoices - needs manual reconciliation
            Log::channel('finance')->info('Auto-reconcile: multiple matching invoices', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount' => $payment->amount,
                'match_count' => $matchCount,
                'invoice_ids' => $matchingInvoices->pluck('id')->toArray(),
            ]);

            $payment->setMismatchInfo('Multiple invoices match - manual selection required', [
                'reason' => 'multiple_matches',
                'match_count' => $matchCount,
                'invoice_ids' => $matchingInvoices->pluck('id')->toArray(),
            ]);
            $payment->save();

            return false;
        }

        // Exactly one match - apply payment and mark as matched
        $invoice = $matchingInvoices->first();

        return DB::transaction(function () use ($payment, $invoice): bool {
            try {
                // Apply the payment to the invoice
                $this->applyToInvoice($payment, $invoice, $payment->amount);

                // Mark as reconciled
                $this->markReconciled($payment, ReconciliationStatus::Matched);

                Log::channel('finance')->info('Auto-reconcile: payment matched and applied', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $payment->amount,
                ]);

                return true;
            } catch (InvalidArgumentException $e) {
                // Application failed (e.g., amount exceeded outstanding)
                Log::channel('finance')->warning('Auto-reconcile: application failed', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);

                $payment->setMismatchInfo('Auto-application failed: '.$e->getMessage(), [
                    'reason' => 'application_failed',
                    'invoice_id' => $invoice->id,
                ]);
                $payment->save();

                return false;
            }
        });
    }

    /**
     * Update payment reconciliation status.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function markReconciled(Payment $payment, ReconciliationStatus $status): Payment
    {
        $oldStatus = $payment->reconciliation_status;

        $payment->reconciliation_status = $status;

        // Clear mismatch info if marking as matched
        if ($status === ReconciliationStatus::Matched) {
            $payment->clearMismatchInfo();
        }

        $payment->save();

        $this->logPaymentEvent(
            $payment,
            'reconciliation_status_change',
            ['reconciliation_status' => $oldStatus->value],
            ['reconciliation_status' => $status->value]
        );

        return $payment;
    }

    /**
     * Find invoices that match the payment for auto-reconciliation.
     *
     * Matches by:
     * - Same customer
     * - Same currency
     * - Outstanding amount equals payment amount
     * - Status is issued or partially_paid
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    protected function findMatchingInvoices(Payment $payment): \Illuminate\Database\Eloquent\Collection
    {
        if ($payment->customer_id === null) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Get all open invoices for this customer with matching currency
        $openInvoices = Invoice::where('customer_id', $payment->customer_id)
            ->where('currency', $payment->currency)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->get();

        // Filter to those with outstanding amount matching payment amount
        $matching = $openInvoices->filter(function (Invoice $invoice) use ($payment): bool {
            $outstanding = $invoice->getOutstandingAmount();

            return bccomp($outstanding, $payment->amount, 2) === 0;
        });

        return new \Illuminate\Database\Eloquent\Collection($matching->values()->all());
    }

    /**
     * Find customer by Stripe customer ID.
     *
     * Searches for customer with matching stripe_customer_id field.
     */
    protected function findCustomerByStripeId(?string $stripeCustomerId): ?Customer
    {
        if ($stripeCustomerId === null) {
            return null;
        }

        return Customer::where('stripe_customer_id', $stripeCustomerId)->first();
    }

    /**
     * Generate a unique payment reference.
     *
     * Format: {prefix}-{YYYYMMDD}-{random}
     */
    protected function generatePaymentReference(string $prefix): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Log a payment event to the audit log.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logPaymentEvent(
        Payment $payment,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $payment->auditLogs()->create([
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }

    // =========================================================================
    // Query Helpers
    // =========================================================================

    /**
     * Find payment by Stripe Payment Intent ID.
     */
    public function findByStripePaymentIntent(string $paymentIntentId): ?Payment
    {
        return Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
    }

    /**
     * Find payment by bank reference.
     */
    public function findByBankReference(string $bankReference): ?Payment
    {
        return Payment::where('bank_reference', $bankReference)->first();
    }

    /**
     * Get payments pending reconciliation.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Payment>
     */
    public function getPendingReconciliation(): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::where('reconciliation_status', ReconciliationStatus::Pending)
            ->where('status', PaymentStatus::Confirmed)
            ->orderBy('received_at', 'desc')
            ->get();
    }

    /**
     * Get mismatched payments requiring attention.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Payment>
     */
    public function getMismatchedPayments(): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::where('reconciliation_status', ReconciliationStatus::Mismatched)
            ->orderBy('received_at', 'desc')
            ->get();
    }
}
