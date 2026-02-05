<?php

namespace App\Services\Finance;

use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Jobs\Finance\ProcessStripeWebhookJob;
use App\Models\AuditLog;
use App\Models\Finance\Payment;
use App\Models\Finance\StripeWebhook;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for Stripe integration management.
 *
 * Centralizes all Stripe integration logic including:
 * - Webhook processing and dispatching
 * - Payment intent handling (succeeded/failed)
 * - Refund processing from Stripe events
 * - Integration health monitoring
 *
 * This service provides a unified API for Stripe operations,
 * delegating to PaymentService and RefundService for domain-specific logic.
 */
class StripeIntegrationService
{
    public function __construct(
        protected PaymentService $paymentService,
        protected RefundService $refundService
    ) {}

    // =========================================================================
    // Webhook Processing
    // =========================================================================

    /**
     * Process a Stripe webhook and dispatch to appropriate handler.
     *
     * This is the main entry point for webhook processing.
     * It determines the event type and routes to the correct handler.
     *
     * @throws RuntimeException If required data is missing from the webhook
     */
    public function processWebhook(StripeWebhook $webhook): void
    {
        // Skip if already processed
        if ($webhook->isProcessed()) {
            Log::channel('finance')->info('Stripe webhook already processed, skipping', [
                'webhook_id' => $webhook->id,
                'event_id' => $webhook->event_id,
            ]);

            return;
        }

        Log::channel('finance')->info('Processing Stripe webhook via StripeIntegrationService', [
            'webhook_id' => $webhook->id,
            'event_id' => $webhook->event_id,
            'event_type' => $webhook->event_type,
        ]);

        try {
            // Dispatch to appropriate handler based on event type
            match ($webhook->event_type) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($webhook),
                'payment_intent.payment_failed' => $this->handlePaymentFailed($webhook),
                'charge.refunded' => $this->processRefund($webhook),
                'charge.dispute.created' => $this->handleDisputeCreated($webhook),
                default => $this->handleUnknownEvent($webhook),
            };

            // Mark as processed on success
            $webhook->markProcessed();

            Log::channel('finance')->info('Stripe webhook processed successfully', [
                'webhook_id' => $webhook->id,
                'event_id' => $webhook->event_id,
                'event_type' => $webhook->event_type,
            ]);
        } catch (\Exception $e) {
            // Mark as failed with error message
            $webhook->markFailed($e->getMessage());

            Log::channel('finance')->error('Stripe webhook processing failed', [
                'webhook_id' => $webhook->id,
                'event_id' => $webhook->event_id,
                'event_type' => $webhook->event_type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch a webhook for async processing.
     *
     * Creates a queued job to process the webhook asynchronously.
     */
    public function dispatchWebhookJob(StripeWebhook $webhook): void
    {
        ProcessStripeWebhookJob::dispatch($webhook);

        Log::channel('finance')->info('Stripe webhook job dispatched', [
            'webhook_id' => $webhook->id,
            'event_id' => $webhook->event_id,
            'event_type' => $webhook->event_type,
        ]);
    }

    // =========================================================================
    // Payment Intent Handling
    // =========================================================================

    /**
     * Handle payment_intent.succeeded event.
     *
     * Creates a Payment record and triggers auto-reconciliation.
     *
     * @throws RuntimeException If payment intent ID is missing
     */
    public function handlePaymentSucceeded(StripeWebhook $webhook): Payment
    {
        $paymentIntentId = $webhook->getPaymentIntentId();

        Log::channel('finance')->info('Handling payment_intent.succeeded', [
            'webhook_id' => $webhook->id,
            'payment_intent_id' => $paymentIntentId,
            'amount' => $webhook->getAmount(),
            'currency' => $webhook->getCurrency(),
        ]);

        if ($paymentIntentId === null) {
            throw new RuntimeException('Payment intent ID not found in webhook payload');
        }

        // Extract payment intent data from payload
        $paymentIntentData = $this->extractPaymentIntentData($webhook);

        // Create payment from Stripe (includes auto-reconciliation)
        $payment = $this->paymentService->createFromStripe($paymentIntentData, $webhook);

        Log::channel('finance')->info('Payment created/retrieved from Stripe webhook', [
            'webhook_id' => $webhook->id,
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'reconciliation_status' => $payment->reconciliation_status->value,
        ]);

        return $payment;
    }

    /**
     * Handle payment_intent.payment_failed event.
     *
     * Logs the failure and updates any existing Payment record.
     */
    public function handlePaymentFailed(StripeWebhook $webhook): void
    {
        $paymentIntentId = $webhook->getPaymentIntentId();

        Log::channel('finance')->info('Handling payment_intent.payment_failed', [
            'webhook_id' => $webhook->id,
            'payment_intent_id' => $paymentIntentId,
        ]);

        if ($paymentIntentId === null) {
            throw new RuntimeException('Payment intent ID not found in webhook payload');
        }

        // Check if payment already exists
        $existingPayment = $this->paymentService->findByStripePaymentIntent($paymentIntentId);

        if ($existingPayment !== null) {
            // Update existing payment to failed status
            if ($existingPayment->status !== PaymentStatus::Failed) {
                $existingPayment->status = PaymentStatus::Failed;

                // Store failure details in metadata
                $failureDetails = $this->extractFailureDetails($webhook);
                $metadata = $existingPayment->metadata ?? [];
                $metadata['failure_details'] = $failureDetails;
                $existingPayment->metadata = $metadata;
                $existingPayment->save();

                // Log the failure in audit trail
                $existingPayment->auditLogs()->create([
                    'event' => AuditLog::EVENT_STATUS_CHANGE,
                    'old_values' => ['status' => PaymentStatus::Pending->value],
                    'new_values' => [
                        'status' => PaymentStatus::Failed->value,
                        'failure_code' => $failureDetails['failure_code'] ?? null,
                    ],
                    'user_id' => null,
                ]);

                Log::channel('finance')->warning('Payment marked as failed from Stripe webhook', [
                    'webhook_id' => $webhook->id,
                    'payment_id' => $existingPayment->id,
                    'failure_code' => $failureDetails['failure_code'] ?? null,
                    'failure_message' => $failureDetails['failure_message'] ?? null,
                ]);
            }
        } else {
            // Log the failure without creating a payment (no successful charge)
            Log::channel('finance')->warning('Payment intent failed (no existing payment)', [
                'webhook_id' => $webhook->id,
                'payment_intent_id' => $paymentIntentId,
                'failure_code' => $webhook->getPayloadValue('data.object.last_payment_error.code'),
                'failure_message' => $webhook->getPayloadValue('data.object.last_payment_error.message'),
            ]);
        }
    }

    // =========================================================================
    // Refund Processing
    // =========================================================================

    /**
     * Process charge.refunded event.
     *
     * Creates Refund records for refunds in the webhook payload.
     */
    public function processRefund(StripeWebhook $webhook): void
    {
        $chargeId = $webhook->getChargeId();

        Log::channel('finance')->info('Processing charge.refunded', [
            'webhook_id' => $webhook->id,
            'charge_id' => $chargeId,
            'amount' => $webhook->getAmount(),
        ]);

        if ($chargeId === null) {
            throw new RuntimeException('Charge ID not found in webhook payload');
        }

        // Find the payment by charge ID
        $payment = Payment::where('stripe_charge_id', $chargeId)->first();

        if ($payment === null) {
            // Try to find by payment intent ID
            $paymentIntentId = $webhook->getPayloadValue('data.object.payment_intent');
            if ($paymentIntentId !== null) {
                $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
            }
        }

        if ($payment === null) {
            Log::channel('finance')->warning('No payment found for refunded charge', [
                'webhook_id' => $webhook->id,
                'charge_id' => $chargeId,
            ]);

            // Still consider as processed since we've handled it
            return;
        }

        // Extract refund data from the charge.refunded payload
        $refundsData = $webhook->getPayloadValue('data.object.refunds.data', []);

        if (! is_array($refundsData) || empty($refundsData)) {
            Log::channel('finance')->warning('No refund data in charge.refunded event', [
                'webhook_id' => $webhook->id,
                'charge_id' => $chargeId,
            ]);

            return;
        }

        // Process each refund in the list (typically just one)
        foreach ($refundsData as $refundData) {
            if (! is_array($refundData)) {
                continue;
            }

            $stripeRefundId = $refundData['id'] ?? null;
            if ($stripeRefundId === null) {
                continue;
            }

            // Check if we already processed this refund
            $existingRefund = $this->refundService->findByStripeRefundId($stripeRefundId);
            if ($existingRefund !== null) {
                Log::channel('finance')->info('Refund already exists, skipping', [
                    'stripe_refund_id' => $stripeRefundId,
                    'refund_id' => $existingRefund->id,
                ]);

                continue;
            }

            try {
                // Create refund record from Stripe data
                $refund = $this->refundService->createFromStripeWebhook([
                    'id' => $stripeRefundId,
                    'amount' => $refundData['amount'] ?? 0,
                    'currency' => $refundData['currency'] ?? 'eur',
                    'charge' => $chargeId,
                    'status' => $refundData['status'] ?? 'pending',
                    'reason' => $refundData['reason'] ?? null,
                    'metadata' => $refundData['metadata'] ?? [],
                ], $payment);

                Log::channel('finance')->info('Refund created from Stripe webhook', [
                    'webhook_id' => $webhook->id,
                    'refund_id' => $refund->id,
                    'stripe_refund_id' => $stripeRefundId,
                    'amount' => $refund->amount,
                ]);
            } catch (\InvalidArgumentException $e) {
                // Log but don't fail - payment may not be applied to invoice yet
                Log::channel('finance')->warning('Could not create refund record', [
                    'webhook_id' => $webhook->id,
                    'stripe_refund_id' => $stripeRefundId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // =========================================================================
    // Dispute Handling
    // =========================================================================

    /**
     * Handle charge.dispute.created event.
     *
     * Flags the related invoice as disputed.
     */
    protected function handleDisputeCreated(StripeWebhook $webhook): void
    {
        $chargeId = $webhook->getPayloadValue('data.object.charge');
        $disputeId = $webhook->getPayloadValue('data.object.id');
        $disputeReason = $webhook->getPayloadValue('data.object.reason', 'unspecified');
        $disputeStatus = $webhook->getPayloadValue('data.object.status', 'unknown');

        Log::channel('finance')->info('Handling charge.dispute.created', [
            'webhook_id' => $webhook->id,
            'dispute_id' => $disputeId,
            'charge_id' => $chargeId,
            'reason' => $disputeReason,
            'status' => $disputeStatus,
        ]);

        if ($chargeId === null) {
            throw new RuntimeException('Charge ID not found in dispute webhook payload');
        }

        // Find the payment by charge ID
        $payment = Payment::where('stripe_charge_id', $chargeId)->first();

        if ($payment === null) {
            Log::channel('finance')->warning('No payment found for disputed charge', [
                'webhook_id' => $webhook->id,
                'charge_id' => $chargeId,
            ]);

            return;
        }

        // Find invoices linked to this payment via InvoicePayment
        $invoicePayments = $payment->invoicePayments()->with('invoice')->get();

        if ($invoicePayments->isEmpty()) {
            Log::channel('finance')->warning('No invoices found for disputed payment', [
                'webhook_id' => $webhook->id,
                'payment_id' => $payment->id,
                'charge_id' => $chargeId,
            ]);

            return;
        }

        // Build dispute reason text
        $disputeReasonText = "Stripe dispute ({$disputeReason})";
        if ($disputeStatus !== 'unknown') {
            $disputeReasonText .= " - Status: {$disputeStatus}";
        }

        // Flag each related invoice as disputed
        foreach ($invoicePayments as $invoicePayment) {
            $invoice = $invoicePayment->invoice;

            if ($invoice === null) {
                continue;
            }

            // Skip if already disputed with the same dispute
            if ($invoice->isDisputed() && str_contains($invoice->dispute_reason ?? '', (string) $disputeId)) {
                Log::channel('finance')->info('Invoice already flagged for this dispute', [
                    'invoice_id' => $invoice->id,
                    'dispute_id' => $disputeId,
                ]);

                continue;
            }

            // Mark invoice as disputed
            $invoice->markDisputed($disputeReasonText, $disputeId);

            // Log the dispute in audit trail
            $invoice->auditLogs()->create([
                'event' => 'dispute_created',
                'old_values' => ['is_disputed' => false],
                'new_values' => [
                    'is_disputed' => true,
                    'dispute_id' => $disputeId,
                    'dispute_reason' => $disputeReason,
                    'charge_id' => $chargeId,
                    'payment_id' => $payment->id,
                ],
                'user_id' => null,
            ]);

            Log::channel('finance')->warning('Invoice flagged as disputed', [
                'webhook_id' => $webhook->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'dispute_id' => $disputeId,
                'dispute_reason' => $disputeReason,
            ]);
        }
    }

    /**
     * Handle unknown/unhandled event types.
     */
    protected function handleUnknownEvent(StripeWebhook $webhook): void
    {
        Log::channel('finance')->info('Received unhandled Stripe event type', [
            'webhook_id' => $webhook->id,
            'event_type' => $webhook->event_type,
        ]);

        // Unhandled events are logged but considered "processed"
    }

    // =========================================================================
    // Integration Health
    // =========================================================================

    /**
     * Get Stripe integration health metrics.
     *
     * Returns a comprehensive health summary including:
     * - Overall status (healthy, warning, critical)
     * - Last webhook received
     * - Failed/pending webhook counts
     * - Reconciliation status counts
     * - Today's activity
     * - Active alerts
     *
     * @return array{
     *     status: string,
     *     status_color: string,
     *     last_webhook: string|null,
     *     last_webhook_time: \Carbon\Carbon|null,
     *     failed_count: int,
     *     pending_count: int,
     *     pending_reconciliations: int,
     *     mismatched_reconciliations: int,
     *     today_received: int,
     *     today_processed: int,
     *     alerts: array<string>,
     *     is_healthy: bool,
     *     has_recent_activity: bool
     * }
     */
    public function getIntegrationHealth(): array
    {
        $lastWebhook = StripeWebhook::query()
            ->orderBy('created_at', 'desc')
            ->first();

        $failedCount = StripeWebhook::failed()->count();
        $pendingCount = StripeWebhook::pending()
            ->whereNull('error_message')
            ->count();

        $oneHourAgo = now()->subHour();
        $hasRecentActivity = StripeWebhook::query()
            ->where('created_at', '>=', $oneHourAgo)
            ->exists();

        $pendingReconciliations = Payment::query()
            ->where('reconciliation_status', ReconciliationStatus::Pending)
            ->count();

        $mismatchedReconciliations = Payment::query()
            ->where('reconciliation_status', ReconciliationStatus::Mismatched)
            ->count();

        $todayReceived = StripeWebhook::query()
            ->whereDate('created_at', today())
            ->count();

        $todayProcessed = StripeWebhook::processed()
            ->whereDate('created_at', today())
            ->count();

        // Determine status and alerts
        $alerts = [];
        $status = 'healthy';
        $statusColor = 'success';

        if ($lastWebhook === null) {
            $status = 'unknown';
            $statusColor = 'gray';
            $alerts[] = 'No webhooks have ever been received. Verify Stripe webhook configuration.';
        } elseif (! $hasRecentActivity) {
            $status = 'warning';
            $statusColor = 'warning';
            $alerts[] = 'No webhooks received in the last hour. Check Stripe connectivity.';
        }

        if ($failedCount > 0) {
            $status = $status === 'healthy' ? 'warning' : $status;
            $statusColor = $statusColor === 'success' ? 'warning' : $statusColor;
            $alerts[] = "{$failedCount} webhook(s) failed to process. Review and retry.";
        }

        if ($failedCount > 10) {
            $status = 'critical';
            $statusColor = 'danger';
        }

        if ($mismatchedReconciliations > 0) {
            $alerts[] = "{$mismatchedReconciliations} payment(s) with reconciliation mismatches require attention.";
        }

        return [
            'status' => $status,
            'status_color' => $statusColor,
            'last_webhook' => $lastWebhook?->event_type,
            'last_webhook_time' => $lastWebhook?->created_at,
            'failed_count' => $failedCount,
            'pending_count' => $pendingCount,
            'pending_reconciliations' => $pendingReconciliations,
            'mismatched_reconciliations' => $mismatchedReconciliations,
            'today_received' => $todayReceived,
            'today_processed' => $todayProcessed,
            'alerts' => $alerts,
            'is_healthy' => $status === 'healthy',
            'has_recent_activity' => $hasRecentActivity,
        ];
    }

    /**
     * Get failed webhooks for review/retry.
     *
     * @param  int  $limit  Maximum number of webhooks to return
     * @return \Illuminate\Database\Eloquent\Collection<int, StripeWebhook>
     */
    public function getFailedWebhooks(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return StripeWebhook::failed()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Retry a failed webhook.
     *
     * Marks the webhook for retry and dispatches a processing job.
     *
     * @return bool True if retry was initiated
     */
    public function retryFailedWebhook(StripeWebhook $webhook): bool
    {
        if (! $webhook->canRetry()) {
            Log::channel('finance')->warning('Webhook cannot be retried', [
                'webhook_id' => $webhook->id,
                'processed' => $webhook->isProcessed(),
                'has_error' => $webhook->hasFailed(),
            ]);

            return false;
        }

        // Mark for retry
        $webhook->markForRetry();

        Log::channel('finance')->info('Stripe webhook retry initiated', [
            'webhook_id' => $webhook->id,
            'event_id' => $webhook->event_id,
            'event_type' => $webhook->event_type,
            'retry_count' => $webhook->retry_count,
        ]);

        // Dispatch job for reprocessing
        ProcessStripeWebhookJob::dispatch($webhook);

        return true;
    }

    /**
     * Retry all failed webhooks.
     *
     * @return int Number of webhooks queued for retry
     */
    public function retryAllFailedWebhooks(): int
    {
        $failedWebhooks = StripeWebhook::failed()->get();
        $count = 0;

        foreach ($failedWebhooks as $webhook) {
            if ($this->retryFailedWebhook($webhook)) {
                $count++;
            }
        }

        Log::channel('finance')->info('Bulk retry initiated for failed webhooks', [
            'total_failed' => $failedWebhooks->count(),
            'retried' => $count,
        ]);

        return $count;
    }

    // =========================================================================
    // Data Extraction Helpers
    // =========================================================================

    /**
     * Extract payment intent data from webhook payload.
     *
     * @return array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     customer: string|null,
     *     metadata: array<string, mixed>,
     *     latest_charge: string|null
     * }
     */
    protected function extractPaymentIntentData(StripeWebhook $webhook): array
    {
        $dataObject = $webhook->getPayloadValue('data.object', []);

        if (! is_array($dataObject)) {
            $dataObject = [];
        }

        return [
            'id' => $dataObject['id'] ?? $webhook->getPaymentIntentId() ?? '',
            'amount' => (int) ($dataObject['amount'] ?? $webhook->getAmountInCents() ?? 0),
            'currency' => (string) ($dataObject['currency'] ?? $webhook->getCurrency() ?? 'eur'),
            'customer' => $dataObject['customer'] ?? $webhook->getStripeCustomerId(),
            'metadata' => is_array($dataObject['metadata'] ?? null) ? $dataObject['metadata'] : [],
            'latest_charge' => $dataObject['latest_charge'] ?? $webhook->getChargeId(),
        ];
    }

    /**
     * Extract failure details from a failed payment intent webhook.
     *
     * @return array{
     *     webhook_event_id: string,
     *     failure_code: string|null,
     *     failure_message: string|null,
     *     decline_code: string|null,
     *     failed_at: string
     * }
     */
    protected function extractFailureDetails(StripeWebhook $webhook): array
    {
        return [
            'webhook_event_id' => $webhook->event_id,
            'failure_code' => $webhook->getPayloadValue('data.object.last_payment_error.code'),
            'failure_message' => $webhook->getPayloadValue('data.object.last_payment_error.message'),
            'decline_code' => $webhook->getPayloadValue('data.object.last_payment_error.decline_code'),
            'failed_at' => now()->toIso8601String(),
        ];
    }
}
