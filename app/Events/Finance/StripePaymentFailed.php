<?php

namespace App\Events\Finance;

use App\Models\Finance\Payment;
use App\Models\Finance\StripeWebhook;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: StripePaymentFailed
 *
 * Dispatched when a Stripe payment_intent.payment_failed webhook is received.
 * This event is used to notify internal staff for follow-up.
 *
 * The invoice associated with this payment (if any) remains in its current
 * state (issued/partially_paid) - it is NOT automatically paid or cancelled.
 */
class StripePaymentFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Payment  $payment  The Payment model with status = failed
     * @param  StripeWebhook  $webhook  The original webhook record
     * @param  string  $failureCode  The Stripe failure code
     * @param  string  $failureMessage  The human-readable failure message
     * @param  string|null  $paymentIntentId  The Stripe payment intent ID
     * @param  array<string, mixed>|null  $metadata  Additional failure details
     */
    public function __construct(
        public Payment $payment,
        public StripeWebhook $webhook,
        public string $failureCode,
        public string $failureMessage,
        public ?string $paymentIntentId = null,
        public ?array $metadata = null
    ) {}

    /**
     * Get a description of the failure for logging.
     */
    public function getDescription(): string
    {
        return "Payment failed: {$this->failureMessage} (Code: {$this->failureCode})";
    }

    /**
     * Get the failure details as an array.
     *
     * @return array<string, mixed>
     */
    public function getFailureDetails(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'payment_reference' => $this->payment->payment_reference,
            'stripe_payment_intent_id' => $this->paymentIntentId,
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'customer_id' => $this->payment->customer_id,
            'webhook_id' => $this->webhook->id,
            'webhook_event_id' => $this->webhook->event_id,
        ];
    }
}
