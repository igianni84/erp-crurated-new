<?php

namespace App\Services\Finance;

use App\Enums\Finance\RefundMethod;
use App\Enums\Finance\RefundStatus;
use App\Enums\Finance\RefundType;
use App\Models\AuditLog;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service for managing Refund lifecycle and operations.
 *
 * Centralizes all refund business logic including creation,
 * Stripe refund processing, and bank refund tracking.
 *
 * Refunds require both an invoice and payment to be linked via InvoicePayment.
 * The refund amount cannot exceed the payment's applied amount to the invoice.
 */
class RefundService
{
    /**
     * Maximum number of retries for Stripe API calls.
     */
    protected const MAX_RETRIES = 3;

    /**
     * Delay between retries in milliseconds.
     */
    protected const RETRY_DELAY_MS = 1000;

    /**
     * Create a refund for an invoice/payment.
     *
     * @param  Invoice  $invoice  The invoice to refund
     * @param  Payment  $payment  The payment to refund from
     * @param  RefundType  $type  Full or partial refund
     * @param  string  $amount  The refund amount
     * @param  RefundMethod  $method  Refund method (stripe or bank_transfer)
     * @param  string  $reason  The reason for the refund (required)
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function create(
        Invoice $invoice,
        Payment $payment,
        RefundType $type,
        string $amount,
        RefundMethod $method,
        string $reason
    ): Refund {
        // Validate invoice and payment are linked
        $invoicePayment = InvoicePayment::where('invoice_id', $invoice->id)
            ->where('payment_id', $payment->id)
            ->first();

        if ($invoicePayment === null) {
            throw new InvalidArgumentException(
                'Cannot create refund: payment is not applied to this invoice.'
            );
        }

        // Validate amount is positive
        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException(
                'Cannot create refund: amount must be greater than zero.'
            );
        }

        // Validate amount doesn't exceed payment applied amount
        if (bccomp($amount, $invoicePayment->amount_applied, 2) > 0) {
            throw new InvalidArgumentException(
                "Cannot create refund: amount ({$amount}) exceeds payment applied amount ({$invoicePayment->amount_applied})."
            );
        }

        // Validate reason is provided
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(
                'Cannot create refund: reason is required.'
            );
        }

        // Validate Stripe refund requirements
        if ($method === RefundMethod::Stripe) {
            if ($payment->stripe_charge_id === null) {
                throw new InvalidArgumentException(
                    'Cannot create Stripe refund: payment does not have a Stripe charge ID.'
                );
            }
        }

        return DB::transaction(function () use ($invoice, $payment, $type, $amount, $method, $reason): Refund {
            $refund = Refund::create([
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'refund_type' => $type,
                'method' => $method,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'status' => RefundStatus::Pending,
                'reason' => $reason,
            ]);

            // Log creation
            $this->logRefundEvent(
                $refund,
                AuditLog::EVENT_CREATED,
                [],
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'refund_type' => $type->value,
                    'method' => $method->value,
                    'amount' => $amount,
                    'currency' => $invoice->currency,
                    'reason' => $reason,
                ]
            );

            Log::channel('finance')->info('Refund created', [
                'refund_id' => $refund->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
                'refund_type' => $type->value,
                'method' => $method->value,
                'amount' => $amount,
                'currency' => $invoice->currency,
            ]);

            return $refund;
        });
    }

    /**
     * Process a refund via Stripe API.
     *
     * Calls the Stripe Refund API to create a refund for the charge.
     * Saves the stripe_refund_id and updates status to processed.
     * Includes retry logic for transient failures.
     *
     * @throws InvalidArgumentException If refund is not a Stripe refund or not pending
     * @throws RuntimeException If Stripe API call fails after all retries
     */
    public function processStripeRefund(Refund $refund): Refund
    {
        // Validate refund can be processed
        if ($refund->method !== RefundMethod::Stripe) {
            throw new InvalidArgumentException(
                'Cannot process Stripe refund: refund method is not Stripe.'
            );
        }

        if (! $refund->isPending() && ! $refund->isFailed()) {
            throw new InvalidArgumentException(
                "Cannot process Stripe refund: refund is not in pending or failed status. Current status: {$refund->status->label()}"
            );
        }

        $payment = $refund->payment;
        if ($payment === null || $payment->stripe_charge_id === null) {
            throw new InvalidArgumentException(
                'Cannot process Stripe refund: payment does not have a Stripe charge ID.'
            );
        }

        // Idempotency check - if already has a stripe_refund_id, verify it
        if ($refund->stripe_refund_id !== null) {
            return $this->verifyStripeRefund($refund);
        }

        // Process the refund with retry logic
        return $this->executeStripeRefundWithRetry($refund, $payment);
    }

    /**
     * Execute Stripe refund API call with retry logic.
     *
     * @throws RuntimeException If all retries fail
     */
    protected function executeStripeRefundWithRetry(Refund $refund, Payment $payment): Refund
    {
        $lastException = null;
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                return $this->callStripeRefundApi($refund, $payment);
            } catch (RuntimeException $e) {
                $lastException = $e;

                // Check if error is retryable
                if (! $this->isRetryableError($e)) {
                    // Non-retryable error, mark as failed immediately
                    return $this->markRefundFailed($refund, $e->getMessage());
                }

                Log::channel('finance')->warning('Stripe refund attempt failed, will retry', [
                    'refund_id' => $refund->id,
                    'attempt' => $attempt,
                    'max_retries' => self::MAX_RETRIES,
                    'error' => $e->getMessage(),
                ]);

                // Wait before retry (except on last attempt)
                if ($attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt); // Exponential backoff
                }
            }
        }

        // All retries exhausted - $lastException is guaranteed to be set here
        // because the loop only exits without returning if an exception was caught
        /** @var RuntimeException $lastException */
        Log::channel('finance')->error('Stripe refund failed after all retries', [
            'refund_id' => $refund->id,
            'attempts' => $attempt,
            'last_error' => $lastException->getMessage(),
        ]);

        $errorMessage = 'Stripe refund failed after '.$attempt.' attempts: '.$lastException->getMessage();

        return $this->markRefundFailed($refund, $errorMessage);
    }

    /**
     * Call Stripe Refund API to create a refund.
     *
     * Uses HTTP client to call Stripe API directly.
     * This allows integration without requiring the Stripe PHP SDK.
     *
     * @throws RuntimeException If API call fails
     */
    protected function callStripeRefundApi(Refund $refund, Payment $payment): Refund
    {
        $stripeSecretKey = config('services.stripe.secret');

        if (empty($stripeSecretKey)) {
            throw new RuntimeException(
                'Stripe secret key is not configured. Set STRIPE_SECRET in environment.'
            );
        }

        // Convert amount to cents for Stripe
        $amountCents = (int) bcmul($refund->amount, '100', 0);

        $payload = [
            'charge' => $payment->stripe_charge_id,
            'amount' => $amountCents,
            'reason' => 'requested_by_customer',
            'metadata' => [
                'refund_id' => (string) $refund->id,
                'invoice_id' => (string) $refund->invoice_id,
                'erp_reason' => substr($refund->reason, 0, 500), // Stripe metadata limit
            ],
        ];

        Log::channel('finance')->info('Calling Stripe Refund API', [
            'refund_id' => $refund->id,
            'charge_id' => $payment->stripe_charge_id,
            'amount_cents' => $amountCents,
        ]);

        $response = Http::withBasicAuth($stripeSecretKey, '')
            ->asForm()
            ->timeout(30)
            ->post('https://api.stripe.com/v1/refunds', $payload);

        if (! $response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? 'Unknown Stripe error';
            $errorType = $errorBody['error']['type'] ?? 'unknown';
            $errorCode = $errorBody['error']['code'] ?? null;

            Log::channel('finance')->error('Stripe Refund API error', [
                'refund_id' => $refund->id,
                'status_code' => $response->status(),
                'error_type' => $errorType,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            throw new RuntimeException(
                "Stripe API error ({$errorType}): {$errorMessage}",
                $response->status()
            );
        }

        $responseData = $response->json();
        $stripeRefundId = $responseData['id'] ?? null;
        $stripeStatus = $responseData['status'] ?? 'unknown';

        if ($stripeRefundId === null) {
            throw new RuntimeException('Stripe response missing refund ID');
        }

        Log::channel('finance')->info('Stripe Refund API success', [
            'refund_id' => $refund->id,
            'stripe_refund_id' => $stripeRefundId,
            'stripe_status' => $stripeStatus,
        ]);

        // Update refund with Stripe response
        return $this->markRefundProcessed($refund, $stripeRefundId, $responseData);
    }

    /**
     * Verify an existing Stripe refund status.
     *
     * Used for idempotency when a refund already has a stripe_refund_id.
     */
    protected function verifyStripeRefund(Refund $refund): Refund
    {
        $stripeSecretKey = config('services.stripe.secret');

        if (empty($stripeSecretKey)) {
            throw new RuntimeException(
                'Stripe secret key is not configured. Set STRIPE_SECRET in environment.'
            );
        }

        Log::channel('finance')->info('Verifying existing Stripe refund', [
            'refund_id' => $refund->id,
            'stripe_refund_id' => $refund->stripe_refund_id,
        ]);

        $response = Http::withBasicAuth($stripeSecretKey, '')
            ->timeout(30)
            ->get('https://api.stripe.com/v1/refunds/'.$refund->stripe_refund_id);

        if (! $response->successful()) {
            Log::channel('finance')->warning('Could not verify Stripe refund', [
                'refund_id' => $refund->id,
                'stripe_refund_id' => $refund->stripe_refund_id,
                'status_code' => $response->status(),
            ]);

            // Return the refund as-is if verification fails
            return $refund;
        }

        $responseData = $response->json();
        $stripeStatus = $responseData['status'] ?? 'unknown';

        // Update status based on Stripe status
        if ($stripeStatus === 'succeeded' && $refund->isPending()) {
            return $this->markRefundProcessed($refund, $refund->stripe_refund_id, $responseData);
        }

        if ($stripeStatus === 'failed' && ! $refund->isFailed()) {
            $failureReason = $responseData['failure_reason'] ?? 'Unknown failure reason';

            return $this->markRefundFailed($refund, "Stripe refund failed: {$failureReason}");
        }

        return $refund;
    }

    /**
     * Mark a refund as processed (bank transfer).
     *
     * For bank refunds, the operator marks it processed after manually
     * executing the bank transfer and providing the reference.
     *
     * @param  Refund  $refund  The refund to mark as processed
     * @param  string  $bankReference  The bank transfer reference
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function markProcessed(Refund $refund, string $bankReference): Refund
    {
        // Validate refund method
        if ($refund->method !== RefundMethod::BankTransfer) {
            throw new InvalidArgumentException(
                'Cannot mark as processed: this method is only for bank transfer refunds. Use processStripeRefund() for Stripe refunds.'
            );
        }

        // Validate refund status
        if (! $refund->isPending() && ! $refund->isFailed()) {
            throw new InvalidArgumentException(
                "Cannot mark as processed: refund is not in pending or failed status. Current status: {$refund->status->label()}"
            );
        }

        // Validate bank reference is provided
        $bankReference = trim($bankReference);
        if ($bankReference === '') {
            throw new InvalidArgumentException(
                'Cannot mark as processed: bank reference is required.'
            );
        }

        return DB::transaction(function () use ($refund, $bankReference): Refund {
            $oldStatus = $refund->status;

            $refund->status = RefundStatus::Processed;
            $refund->bank_reference = $bankReference;
            $refund->processed_at = now();
            $refund->processed_by = Auth::id();
            $refund->save();

            // Log the status change
            $this->logRefundEvent(
                $refund,
                AuditLog::EVENT_STATUS_CHANGE,
                [
                    'status' => $oldStatus->value,
                    'bank_reference' => null,
                    'processed_at' => null,
                ],
                [
                    'status' => RefundStatus::Processed->value,
                    'bank_reference' => $bankReference,
                    'processed_at' => $refund->processed_at->toIso8601String(),
                ]
            );

            Log::channel('finance')->info('Bank refund marked as processed', [
                'refund_id' => $refund->id,
                'bank_reference' => $bankReference,
                'processed_by' => Auth::id(),
            ]);

            return $refund;
        });
    }

    /**
     * Mark a Stripe refund as processed.
     *
     * @param  array<string, mixed>|null  $stripeResponse  The Stripe API response data
     */
    protected function markRefundProcessed(Refund $refund, ?string $stripeRefundId, ?array $stripeResponse = null): Refund
    {
        return DB::transaction(function () use ($refund, $stripeRefundId, $stripeResponse): Refund {
            $oldStatus = $refund->status;

            $refund->status = RefundStatus::Processed;
            $refund->stripe_refund_id = $stripeRefundId;
            $refund->processed_at = now();
            $refund->processed_by = Auth::id();
            $refund->save();

            // Log the status change
            $this->logRefundEvent(
                $refund,
                AuditLog::EVENT_STATUS_CHANGE,
                [
                    'status' => $oldStatus->value,
                    'stripe_refund_id' => null,
                    'processed_at' => null,
                ],
                [
                    'status' => RefundStatus::Processed->value,
                    'stripe_refund_id' => $stripeRefundId,
                    'processed_at' => $refund->processed_at->toIso8601String(),
                    'stripe_response' => $stripeResponse,
                ]
            );

            Log::channel('finance')->info('Stripe refund processed successfully', [
                'refund_id' => $refund->id,
                'stripe_refund_id' => $stripeRefundId,
                'processed_by' => Auth::id(),
            ]);

            // Update payment status to refunded if fully refunded
            $this->updatePaymentStatusIfFullyRefunded($refund);

            return $refund;
        });
    }

    /**
     * Mark a refund as failed.
     */
    protected function markRefundFailed(Refund $refund, string $errorMessage): Refund
    {
        return DB::transaction(function () use ($refund, $errorMessage): Refund {
            $oldStatus = $refund->status;

            $refund->status = RefundStatus::Failed;
            $refund->save();

            // Log the failure
            $this->logRefundEvent(
                $refund,
                AuditLog::EVENT_STATUS_CHANGE,
                [
                    'status' => $oldStatus->value,
                ],
                [
                    'status' => RefundStatus::Failed->value,
                    'error_message' => $errorMessage,
                ]
            );

            Log::channel('finance')->error('Refund marked as failed', [
                'refund_id' => $refund->id,
                'error_message' => $errorMessage,
            ]);

            return $refund;
        });
    }

    /**
     * Retry a failed refund.
     *
     * Resets a failed refund to pending status so it can be processed again.
     *
     * @throws InvalidArgumentException If refund is not in failed status
     */
    public function retryRefund(Refund $refund): Refund
    {
        if (! $refund->isFailed()) {
            throw new InvalidArgumentException(
                "Cannot retry refund: refund is not in failed status. Current status: {$refund->status->label()}"
            );
        }

        return DB::transaction(function () use ($refund): Refund {
            $oldStatus = $refund->status;

            $refund->status = RefundStatus::Pending;
            $refund->save();

            // Log the retry
            $this->logRefundEvent(
                $refund,
                'retry_initiated',
                [
                    'status' => $oldStatus->value,
                ],
                [
                    'status' => RefundStatus::Pending->value,
                ]
            );

            Log::channel('finance')->info('Refund retry initiated', [
                'refund_id' => $refund->id,
                'initiated_by' => Auth::id(),
            ]);

            // If it's a Stripe refund, process it immediately
            if ($refund->method === RefundMethod::Stripe) {
                return $this->processStripeRefund($refund);
            }

            return $refund;
        });
    }

    /**
     * Check if an error is retryable.
     *
     * Some Stripe errors are transient and should be retried.
     */
    protected function isRetryableError(RuntimeException $e): bool
    {
        $message = strtolower($e->getMessage());

        // Network/timeout errors
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'network')) {
            return true;
        }

        // Stripe rate limiting
        if (str_contains($message, 'rate_limit') ||
            str_contains($message, 'too many requests')) {
            return true;
        }

        // Stripe API temporarily unavailable
        if ($e->getCode() >= 500 && $e->getCode() < 600) {
            return true;
        }

        // 429 Too Many Requests
        if ($e->getCode() === 429) {
            return true;
        }

        return false;
    }

    /**
     * Update payment status if fully refunded.
     *
     * When all applied amounts from a payment have been refunded,
     * the payment status transitions to 'refunded'.
     */
    protected function updatePaymentStatusIfFullyRefunded(Refund $refund): void
    {
        $payment = $refund->payment;

        if ($payment === null) {
            return;
        }

        // Calculate total refunded amount for this payment
        $totalRefunded = Refund::where('payment_id', $payment->id)
            ->where('status', RefundStatus::Processed)
            ->sum('amount');

        // Get total applied from payment
        $totalApplied = $payment->getTotalAppliedAmount();

        // If fully refunded, update payment status
        if (bccomp($totalRefunded, $totalApplied, 2) >= 0) {
            $payment->status = \App\Enums\Finance\PaymentStatus::Refunded;
            $payment->save();

            Log::channel('finance')->info('Payment marked as fully refunded', [
                'payment_id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'total_applied' => $totalApplied,
                'total_refunded' => $totalRefunded,
            ]);
        }
    }

    /**
     * Log a refund event to the audit log.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logRefundEvent(
        Refund $refund,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $refund->auditLogs()->create([
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
     * Get pending refunds.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Refund>
     */
    public function getPendingRefunds(): \Illuminate\Database\Eloquent\Collection
    {
        return Refund::where('status', RefundStatus::Pending)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get failed refunds that can be retried.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Refund>
     */
    public function getFailedRefunds(): \Illuminate\Database\Eloquent\Collection
    {
        return Refund::where('status', RefundStatus::Failed)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get refunds for an invoice.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Refund>
     */
    public function getRefundsForInvoice(Invoice $invoice): \Illuminate\Database\Eloquent\Collection
    {
        return Refund::where('invoice_id', $invoice->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get refunds for a payment.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Refund>
     */
    public function getRefundsForPayment(Payment $payment): \Illuminate\Database\Eloquent\Collection
    {
        return Refund::where('payment_id', $payment->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get total refunded amount for an invoice.
     */
    public function getTotalRefundedForInvoice(Invoice $invoice): string
    {
        return Refund::where('invoice_id', $invoice->id)
            ->where('status', RefundStatus::Processed)
            ->sum('amount');
    }

    /**
     * Get total refunded amount for a payment.
     */
    public function getTotalRefundedForPayment(Payment $payment): string
    {
        return Refund::where('payment_id', $payment->id)
            ->where('status', RefundStatus::Processed)
            ->sum('amount');
    }

    /**
     * Check if a payment has any pending or processed refunds.
     */
    public function hasRefunds(Payment $payment): bool
    {
        return Refund::where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::Pending, RefundStatus::Processed])
            ->exists();
    }

    /**
     * Find refund by Stripe refund ID.
     */
    public function findByStripeRefundId(string $stripeRefundId): ?Refund
    {
        return Refund::where('stripe_refund_id', $stripeRefundId)->first();
    }
}
