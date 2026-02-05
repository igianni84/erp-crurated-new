<?php

namespace App\Listeners\Finance;

use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Events\Finance\StripePaymentFailed;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Finance\Payment;
use App\Models\Finance\StripeWebhook;
use App\Notifications\Finance\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Listener: HandleStripePaymentFailure
 *
 * Processes Stripe payment_intent.payment_failed webhooks.
 *
 * This listener:
 * - Creates or updates a Payment record with status = failed
 * - Logs the failure reason in metadata
 * - Ensures the associated invoice remains in its current state (issued/partially_paid)
 * - Dispatches internal notification for follow-up
 *
 * IMPORTANT: This listener does NOT change invoice status. Failed payments
 * leave invoices as-is for manual follow-up or retry.
 */
class HandleStripePaymentFailure implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the webhook for payment failures.
     *
     * Called directly by the webhook processor, not via event.
     */
    public function handleWebhook(StripeWebhook $webhook): void
    {
        // Validate event type
        if ($webhook->event_type !== 'payment_intent.payment_failed') {
            Log::channel('finance')->warning('HandleStripePaymentFailure received wrong event type', [
                'event_type' => $webhook->event_type,
                'event_id' => $webhook->event_id,
            ]);

            return;
        }

        // Skip if already processed
        if ($webhook->isProcessed()) {
            Log::channel('finance')->info('Webhook already processed, skipping', [
                'event_id' => $webhook->event_id,
            ]);

            return;
        }

        try {
            DB::transaction(function () use ($webhook): void {
                $this->processPaymentFailure($webhook);
            });

            $webhook->markProcessed();
        } catch (\Throwable $e) {
            Log::channel('finance')->error('Failed to process payment failure webhook', [
                'event_id' => $webhook->event_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $webhook->markFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Process the payment failure.
     */
    protected function processPaymentFailure(StripeWebhook $webhook): void
    {
        // Extract payment intent data
        $paymentIntentId = $webhook->getPaymentIntentId();
        $amount = $webhook->getAmount();
        $currency = $webhook->getCurrency() ?? 'EUR';
        $stripeCustomerId = $webhook->getStripeCustomerId();

        // Extract failure details from webhook payload
        $failureCode = $webhook->getPayloadValue('data.object.last_payment_error.code', 'unknown');
        $failureMessage = $webhook->getPayloadValue('data.object.last_payment_error.message', 'Payment failed');
        $chargeIdValue = $webhook->getPayloadValue('data.object.latest_charge');
        $chargeId = is_string($chargeIdValue) ? $chargeIdValue : null;
        $declineCode = $webhook->getPayloadValue('data.object.last_payment_error.decline_code');

        // Try to find customer by Stripe customer ID
        $customer = null;
        if ($stripeCustomerId !== null) {
            $customer = Customer::where('stripe_customer_id', $stripeCustomerId)->first();
        }

        // Build failure metadata
        $failureMetadata = [
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'decline_code' => $declineCode,
            'stripe_event_id' => $webhook->event_id,
            'failed_at' => now()->toIso8601String(),
        ];

        // Find existing payment by payment intent ID or create new one
        $payment = $this->findOrCreatePayment(
            paymentIntentId: $paymentIntentId,
            chargeId: $chargeId,
            amount: $amount,
            currency: $currency,
            customer: $customer,
            failureMetadata: $failureMetadata
        );

        // Update payment status to failed
        $payment->update([
            'status' => PaymentStatus::Failed,
            'metadata' => array_merge($payment->metadata ?? [], $failureMetadata),
        ]);

        // Log audit event
        $this->logPaymentFailure($payment, $failureCode, $failureMessage, $webhook);

        Log::channel('finance')->warning('Stripe payment failed', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'payment_intent_id' => $paymentIntentId,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'amount' => $amount,
            'currency' => $currency,
            'customer_id' => $customer?->id,
        ]);

        // Dispatch event for internal notification
        $failureCodeStr = is_scalar($failureCode) ? (string) $failureCode : 'unknown';
        $failureMessageStr = is_scalar($failureMessage) ? (string) $failureMessage : 'Payment failed';
        $event = new StripePaymentFailed(
            payment: $payment,
            webhook: $webhook,
            failureCode: $failureCodeStr,
            failureMessage: $failureMessageStr,
            paymentIntentId: $paymentIntentId,
            metadata: $failureMetadata
        );

        // Send internal notification for follow-up
        $this->sendInternalNotification($event);
    }

    /**
     * Find existing payment or create new one for failed payment.
     *
     * @param  array<string, mixed>  $failureMetadata
     */
    protected function findOrCreatePayment(
        ?string $paymentIntentId,
        ?string $chargeId,
        ?string $amount,
        string $currency,
        ?Customer $customer,
        array $failureMetadata
    ): Payment {
        // Try to find by payment intent ID first
        if ($paymentIntentId !== null) {
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($payment !== null) {
                return $payment;
            }
        }

        // Create new payment record for the failed attempt
        return Payment::create([
            'payment_reference' => $this->generatePaymentReference($paymentIntentId),
            'source' => PaymentSource::Stripe,
            'amount' => $amount ?? '0.00',
            'currency' => $currency,
            'status' => PaymentStatus::Failed,
            'reconciliation_status' => ReconciliationStatus::Pending,
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_charge_id' => $chargeId,
            'received_at' => now(),
            'customer_id' => $customer?->id,
            'metadata' => $failureMetadata,
        ]);
    }

    /**
     * Generate a payment reference for failed payments.
     */
    protected function generatePaymentReference(?string $paymentIntentId): string
    {
        if ($paymentIntentId !== null) {
            return 'FAIL-'.substr($paymentIntentId, 3, 12);
        }

        return 'FAIL-'.strtoupper(substr(md5((string) now()->timestamp.random_int(1000, 9999)), 0, 12));
    }

    /**
     * Log payment failure to audit trail.
     */
    protected function logPaymentFailure(
        Payment $payment,
        mixed $failureCode,
        mixed $failureMessage,
        StripeWebhook $webhook
    ): void {
        AuditLog::create([
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'event' => AuditLog::EVENT_PAYMENT_FAILED,
            'old_values' => null,
            'new_values' => [
                'status' => PaymentStatus::Failed->value,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'stripe_event_id' => $webhook->event_id,
            ],
            'user_id' => null, // System event
        ]);
    }

    /**
     * Send internal notification for follow-up.
     */
    protected function sendInternalNotification(StripePaymentFailed $event): void
    {
        try {
            // Send to configured finance notification recipients
            // This uses Laravel's on-demand notification to a configured email/slack
            // In production, this would use a configured notification channel
            /** @var array<string> $recipients */
            $recipients = config('finance.payment_failure_notification_recipients', []);

            if (count($recipients) > 0) {
                Notification::route('mail', $recipients)
                    ->notify(new PaymentFailedNotification($event));
            }

            Log::channel('finance')->info('Payment failure notification sent', [
                'payment_id' => $event->payment->id,
                'recipients_count' => count($recipients),
            ]);
        } catch (\Throwable $e) {
            // Don't fail the webhook processing if notification fails
            Log::channel('finance')->error('Failed to send payment failure notification', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
