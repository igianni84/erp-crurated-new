<?php

namespace App\Jobs\Finance;

use App\Enums\Finance\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\StripeWebhook;
use App\Services\Finance\PaymentService;
use App\Services\Finance\RefundService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Job to process Stripe webhook events asynchronously.
 *
 * This job is dispatched by the StripeWebhookController after logging
 * the webhook. It handles the actual business logic for different event types.
 *
 * Supported event types:
 * - payment_intent.succeeded: Creates/updates Payment, triggers auto-reconciliation
 * - payment_intent.payment_failed: Logs failure, creates/updates Payment with failed status
 * - charge.refunded: Creates Refund record from Stripe webhook data
 * - charge.dispute.created: Flags related invoice as disputed
 */
class ProcessStripeWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public StripeWebhook $stripeWebhook
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService, RefundService $refundService): void
    {
        // Skip if already processed
        if ($this->stripeWebhook->isProcessed()) {
            Log::channel('finance')->info('Stripe webhook already processed, skipping', [
                'webhook_id' => $this->stripeWebhook->id,
                'event_id' => $this->stripeWebhook->event_id,
            ]);

            return;
        }

        Log::channel('finance')->info('Processing Stripe webhook', [
            'webhook_id' => $this->stripeWebhook->id,
            'event_id' => $this->stripeWebhook->event_id,
            'event_type' => $this->stripeWebhook->event_type,
        ]);

        try {
            // Dispatch to appropriate handler based on event type
            $this->processEventType($paymentService, $refundService);

            // Mark as processed on success
            $this->stripeWebhook->markProcessed();

            Log::channel('finance')->info('Stripe webhook processed successfully', [
                'webhook_id' => $this->stripeWebhook->id,
                'event_id' => $this->stripeWebhook->event_id,
                'event_type' => $this->stripeWebhook->event_type,
            ]);
        } catch (Exception $e) {
            // Mark as failed with error message
            $this->stripeWebhook->markFailed($e->getMessage());

            Log::channel('finance')->error('Stripe webhook processing failed', [
                'webhook_id' => $this->stripeWebhook->id,
                'event_id' => $this->stripeWebhook->event_id,
                'event_type' => $this->stripeWebhook->event_type,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Process the webhook based on event type.
     */
    protected function processEventType(PaymentService $paymentService, RefundService $refundService): void
    {
        $eventType = $this->stripeWebhook->event_type;

        match ($eventType) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($paymentService),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($paymentService),
            'charge.refunded' => $this->handleChargeRefunded($refundService),
            'charge.dispute.created' => $this->handleDisputeCreated(),
            default => $this->handleUnknownEvent(),
        };
    }

    /**
     * Handle payment_intent.succeeded event.
     *
     * Creates/updates Payment record and triggers auto-reconciliation.
     */
    protected function handlePaymentIntentSucceeded(PaymentService $paymentService): void
    {
        $paymentIntentId = $this->stripeWebhook->getPaymentIntentId();

        Log::channel('finance')->info('Handling payment_intent.succeeded', [
            'webhook_id' => $this->stripeWebhook->id,
            'payment_intent_id' => $paymentIntentId,
            'amount' => $this->stripeWebhook->getAmount(),
            'currency' => $this->stripeWebhook->getCurrency(),
        ]);

        if ($paymentIntentId === null) {
            throw new RuntimeException('Payment intent ID not found in webhook payload');
        }

        // Extract payment intent data from payload
        $paymentIntentData = $this->extractPaymentIntentData();

        // Create payment from Stripe (includes auto-reconciliation)
        $payment = $paymentService->createFromStripe($paymentIntentData, $this->stripeWebhook);

        Log::channel('finance')->info('Payment created/retrieved from Stripe webhook', [
            'webhook_id' => $this->stripeWebhook->id,
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'reconciliation_status' => $payment->reconciliation_status->value,
        ]);
    }

    /**
     * Handle payment_intent.payment_failed event.
     *
     * Logs failure and creates/updates Payment record with failed status.
     */
    protected function handlePaymentIntentFailed(PaymentService $paymentService): void
    {
        $paymentIntentId = $this->stripeWebhook->getPaymentIntentId();

        Log::channel('finance')->info('Handling payment_intent.payment_failed', [
            'webhook_id' => $this->stripeWebhook->id,
            'payment_intent_id' => $paymentIntentId,
        ]);

        if ($paymentIntentId === null) {
            throw new RuntimeException('Payment intent ID not found in webhook payload');
        }

        // Check if payment already exists
        $existingPayment = $paymentService->findByStripePaymentIntent($paymentIntentId);

        if ($existingPayment !== null) {
            // Update existing payment to failed status
            if ($existingPayment->status !== PaymentStatus::Failed) {
                $existingPayment->status = PaymentStatus::Failed;

                // Store failure details in metadata
                $metadata = $existingPayment->metadata ?? [];
                $metadata['failure_details'] = [
                    'webhook_event_id' => $this->stripeWebhook->event_id,
                    'failure_code' => $this->stripeWebhook->getPayloadValue('data.object.last_payment_error.code'),
                    'failure_message' => $this->stripeWebhook->getPayloadValue('data.object.last_payment_error.message'),
                    'decline_code' => $this->stripeWebhook->getPayloadValue('data.object.last_payment_error.decline_code'),
                    'failed_at' => now()->toIso8601String(),
                ];
                $existingPayment->metadata = $metadata;
                $existingPayment->save();

                // Log the failure
                $existingPayment->auditLogs()->create([
                    'event' => AuditLog::EVENT_STATUS_CHANGE,
                    'old_values' => ['status' => PaymentStatus::Pending->value],
                    'new_values' => [
                        'status' => PaymentStatus::Failed->value,
                        'failure_code' => $metadata['failure_details']['failure_code'] ?? null,
                    ],
                    'user_id' => null,
                ]);

                Log::channel('finance')->warning('Payment marked as failed from Stripe webhook', [
                    'webhook_id' => $this->stripeWebhook->id,
                    'payment_id' => $existingPayment->id,
                    'failure_code' => $metadata['failure_details']['failure_code'] ?? null,
                    'failure_message' => $metadata['failure_details']['failure_message'] ?? null,
                ]);
            }
        } else {
            // Log the failure without creating a payment (no successful charge)
            Log::channel('finance')->warning('Payment intent failed (no existing payment)', [
                'webhook_id' => $this->stripeWebhook->id,
                'payment_intent_id' => $paymentIntentId,
                'failure_code' => $this->stripeWebhook->getPayloadValue('data.object.last_payment_error.code'),
                'failure_message' => $this->stripeWebhook->getPayloadValue('data.object.last_payment_error.message'),
            ]);
        }

        // Note: Internal notification for follow-up would be sent here
        // This could be implemented as an event/notification in a future story
    }

    /**
     * Handle charge.refunded event.
     *
     * Creates Refund record for the refund.
     */
    protected function handleChargeRefunded(RefundService $refundService): void
    {
        $chargeId = $this->stripeWebhook->getChargeId();

        Log::channel('finance')->info('Handling charge.refunded', [
            'webhook_id' => $this->stripeWebhook->id,
            'charge_id' => $chargeId,
            'amount' => $this->stripeWebhook->getAmount(),
        ]);

        if ($chargeId === null) {
            throw new RuntimeException('Charge ID not found in webhook payload');
        }

        // Find the payment by charge ID
        $payment = Payment::where('stripe_charge_id', $chargeId)->first();

        if ($payment === null) {
            // Try to find by payment intent ID
            $paymentIntentId = $this->stripeWebhook->getPayloadValue('data.object.payment_intent');
            if ($paymentIntentId !== null) {
                $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
            }
        }

        if ($payment === null) {
            Log::channel('finance')->warning('No payment found for refunded charge', [
                'webhook_id' => $this->stripeWebhook->id,
                'charge_id' => $chargeId,
            ]);

            // Still mark as processed since we've handled it
            return;
        }

        // Extract refund data from the charge.refunded payload
        // The refunds are in data.object.refunds.data array
        $refundsData = $this->stripeWebhook->getPayloadValue('data.object.refunds.data', []);

        if (! is_array($refundsData) || empty($refundsData)) {
            Log::channel('finance')->warning('No refund data in charge.refunded event', [
                'webhook_id' => $this->stripeWebhook->id,
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
            $existingRefund = $refundService->findByStripeRefundId($stripeRefundId);
            if ($existingRefund !== null) {
                Log::channel('finance')->info('Refund already exists, skipping', [
                    'stripe_refund_id' => $stripeRefundId,
                    'refund_id' => $existingRefund->id,
                ]);

                continue;
            }

            try {
                // Create refund record from Stripe data
                $refund = $refundService->createFromStripeWebhook([
                    'id' => $stripeRefundId,
                    'amount' => $refundData['amount'] ?? 0,
                    'currency' => $refundData['currency'] ?? 'eur',
                    'charge' => $chargeId,
                    'status' => $refundData['status'] ?? 'pending',
                    'reason' => $refundData['reason'] ?? null,
                    'metadata' => $refundData['metadata'] ?? [],
                ], $payment);

                Log::channel('finance')->info('Refund created from Stripe webhook', [
                    'webhook_id' => $this->stripeWebhook->id,
                    'refund_id' => $refund->id,
                    'stripe_refund_id' => $stripeRefundId,
                    'amount' => $refund->amount,
                ]);
            } catch (InvalidArgumentException $e) {
                // Log but don't fail - payment may not be applied to invoice yet
                Log::channel('finance')->warning('Could not create refund record', [
                    'webhook_id' => $this->stripeWebhook->id,
                    'stripe_refund_id' => $stripeRefundId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle charge.dispute.created event.
     *
     * Flags the related invoice as disputed.
     */
    protected function handleDisputeCreated(): void
    {
        $chargeId = $this->stripeWebhook->getPayloadValue('data.object.charge');
        $disputeId = $this->stripeWebhook->getPayloadValue('data.object.id');
        $disputeReason = $this->stripeWebhook->getPayloadValue('data.object.reason', 'unspecified');
        $disputeStatus = $this->stripeWebhook->getPayloadValue('data.object.status', 'unknown');

        Log::channel('finance')->info('Handling charge.dispute.created', [
            'webhook_id' => $this->stripeWebhook->id,
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
                'webhook_id' => $this->stripeWebhook->id,
                'charge_id' => $chargeId,
            ]);

            return;
        }

        // Find invoices linked to this payment via InvoicePayment
        $invoicePayments = $payment->invoicePayments()->with('invoice')->get();

        if ($invoicePayments->isEmpty()) {
            Log::channel('finance')->warning('No invoices found for disputed payment', [
                'webhook_id' => $this->stripeWebhook->id,
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
                'webhook_id' => $this->stripeWebhook->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'dispute_id' => $disputeId,
                'dispute_reason' => $disputeReason,
            ]);
        }
    }

    /**
     * Handle unknown/unhandled event types.
     *
     * Logs the event but does not fail processing.
     */
    protected function handleUnknownEvent(): void
    {
        Log::channel('finance')->info('Received unhandled Stripe event type', [
            'webhook_id' => $this->stripeWebhook->id,
            'event_type' => $this->stripeWebhook->event_type,
        ]);

        // Unhandled events are logged but considered "processed"
        // to prevent unnecessary retries
    }

    /**
     * Extract payment intent data from the webhook payload.
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
    protected function extractPaymentIntentData(): array
    {
        $dataObject = $this->stripeWebhook->getPayloadValue('data.object', []);

        if (! is_array($dataObject)) {
            $dataObject = [];
        }

        return [
            'id' => $dataObject['id'] ?? $this->stripeWebhook->getPaymentIntentId() ?? '',
            'amount' => (int) ($dataObject['amount'] ?? $this->stripeWebhook->getAmountInCents() ?? 0),
            'currency' => (string) ($dataObject['currency'] ?? $this->stripeWebhook->getCurrency() ?? 'eur'),
            'customer' => $dataObject['customer'] ?? $this->stripeWebhook->getStripeCustomerId(),
            'metadata' => is_array($dataObject['metadata'] ?? null) ? $dataObject['metadata'] : [],
            'latest_charge' => $dataObject['latest_charge'] ?? $this->stripeWebhook->getChargeId(),
        ];
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::channel('finance')->error('Stripe webhook job failed permanently', [
            'webhook_id' => $this->stripeWebhook->id,
            'event_id' => $this->stripeWebhook->event_id,
            'event_type' => $this->stripeWebhook->event_type,
            'error' => $exception?->getMessage(),
        ]);

        // Ensure the webhook is marked as failed
        if (! $this->stripeWebhook->isProcessed()) {
            $this->stripeWebhook->markFailed(
                $exception?->getMessage() ?? 'Job failed after maximum retries'
            );
        }
    }
}
