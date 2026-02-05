<?php

namespace App\Jobs\Finance;

use App\Models\Finance\StripeWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to process Stripe webhook events asynchronously.
 *
 * This job is dispatched by the StripeWebhookController after logging
 * the webhook. It handles the actual business logic for different event types.
 *
 * Event types are processed by dedicated handler methods that can be
 * implemented in US-E094 (StripeIntegrationService).
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
    public function handle(): void
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
            $this->processEventType();

            // Mark as processed on success
            $this->stripeWebhook->markProcessed();

            Log::channel('finance')->info('Stripe webhook processed successfully', [
                'webhook_id' => $this->stripeWebhook->id,
                'event_id' => $this->stripeWebhook->event_id,
                'event_type' => $this->stripeWebhook->event_type,
            ]);
        } catch (\Exception $e) {
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
     *
     * Note: Detailed event type handlers will be implemented in US-E094
     * via StripeIntegrationService. This method provides the dispatch logic.
     */
    protected function processEventType(): void
    {
        $eventType = $this->stripeWebhook->event_type;

        // Map event types to handler methods
        // These handlers will delegate to StripeIntegrationService (US-E097)
        // once that service is implemented
        match ($eventType) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded(),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed(),
            'charge.refunded' => $this->handleChargeRefunded(),
            'charge.dispute.created' => $this->handleDisputeCreated(),
            default => $this->handleUnknownEvent(),
        };
    }

    /**
     * Handle payment_intent.succeeded event.
     *
     * Creates/updates Payment record and triggers auto-reconciliation.
     * Full implementation in US-E094.
     */
    protected function handlePaymentIntentSucceeded(): void
    {
        Log::channel('finance')->info('Handling payment_intent.succeeded', [
            'webhook_id' => $this->stripeWebhook->id,
            'payment_intent_id' => $this->stripeWebhook->getPaymentIntentId(),
            'amount' => $this->stripeWebhook->getAmount(),
            'currency' => $this->stripeWebhook->getCurrency(),
        ]);

        // TODO: Implement via StripeIntegrationService (US-E094/US-E097)
        // - Create Payment record with status = confirmed
        // - Call PaymentService::autoReconcile()
    }

    /**
     * Handle payment_intent.payment_failed event.
     *
     * Logs failure and updates any existing Payment record.
     * Full implementation in US-E094.
     */
    protected function handlePaymentIntentFailed(): void
    {
        Log::channel('finance')->info('Handling payment_intent.payment_failed', [
            'webhook_id' => $this->stripeWebhook->id,
            'payment_intent_id' => $this->stripeWebhook->getPaymentIntentId(),
        ]);

        // TODO: Implement via StripeIntegrationService (US-E094/US-E097)
        // - Create/update Payment record with status = failed
        // - Log failure reason from metadata
        // - Emit internal notification
    }

    /**
     * Handle charge.refunded event.
     *
     * Creates Refund record for the refund.
     * Full implementation in US-E094.
     */
    protected function handleChargeRefunded(): void
    {
        Log::channel('finance')->info('Handling charge.refunded', [
            'webhook_id' => $this->stripeWebhook->id,
            'charge_id' => $this->stripeWebhook->getChargeId(),
            'amount' => $this->stripeWebhook->getAmount(),
        ]);

        // TODO: Implement via StripeIntegrationService (US-E094/US-E097)
        // - Find related Payment by stripe_charge_id
        // - Create Refund record
    }

    /**
     * Handle charge.dispute.created event.
     *
     * Flags the related invoice as disputed.
     * Full implementation in US-E094.
     */
    protected function handleDisputeCreated(): void
    {
        Log::channel('finance')->info('Handling charge.dispute.created', [
            'webhook_id' => $this->stripeWebhook->id,
            'charge_id' => $this->stripeWebhook->getChargeId(),
        ]);

        // TODO: Implement via StripeIntegrationService (US-E094/US-E097)
        // - Find related Payment by stripe_charge_id
        // - Find related Invoice via InvoicePayment
        // - Flag invoice as disputed
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
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?\Throwable $exception): void
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
