<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * StripeWebhook Model
 *
 * Immutable log of all received Stripe webhooks.
 * Used for idempotency checking and debugging webhook processing.
 *
 * IMPORTANT: This model does NOT use soft deletes - logs are immutable.
 *
 * @property int $id
 * @property string $event_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property bool $processed
 * @property \Carbon\Carbon|null $processed_at
 * @property string|null $error_message
 * @property int $retry_count
 * @property \Carbon\Carbon|null $last_retry_at
 * @property \Carbon\Carbon|null $created_at
 */
class StripeWebhook extends Model
{
    use HasFactory;

    /**
     * Disable updated_at timestamp since logs are immutable.
     */
    public const UPDATED_AT = null;

    protected $table = 'stripe_webhooks';

    protected $fillable = [
        'event_id',
        'event_type',
        'payload',
        'processed',
        'processed_at',
        'error_message',
        'retry_count',
        'last_retry_at',
    ];

    protected $attributes = [
        'processed' => false,
        'retry_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
            'retry_count' => 'integer',
            'last_retry_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Boot Methods - Immutability Enforcement
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        // Prevent deletion of webhook logs - they must be retained
        static::deleting(function (StripeWebhook $webhook): void {
            throw new InvalidArgumentException(
                'Stripe webhook logs cannot be deleted. They are immutable for audit purposes.'
            );
        });

        // Allow limited updates only for processing status
        static::updating(function (StripeWebhook $webhook): void {
            // Only allow updates to processing-related fields
            $allowedFields = ['processed', 'processed_at', 'error_message', 'retry_count', 'last_retry_at'];
            $changedFields = array_keys($webhook->getDirty());

            foreach ($changedFields as $field) {
                if (! in_array($field, $allowedFields)) {
                    throw new InvalidArgumentException(
                        "Cannot modify field '{$field}' on Stripe webhook logs. Only processing status can be updated."
                    );
                }
            }

            // Once processed successfully, cannot be changed back
            if ($webhook->getOriginal('processed') && $webhook->isDirty('processed') && ! $webhook->processed) {
                throw new InvalidArgumentException(
                    'Cannot mark a processed webhook as unprocessed.'
                );
            }
        });
    }

    // =========================================================================
    // Status Methods
    // =========================================================================

    /**
     * Check if the webhook has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->processed;
    }

    /**
     * Check if the webhook is pending processing.
     */
    public function isPending(): bool
    {
        return ! $this->processed;
    }

    /**
     * Check if the webhook processing failed.
     */
    public function hasFailed(): bool
    {
        return ! $this->processed && $this->error_message !== null;
    }

    /**
     * Check if the webhook can be retried.
     */
    public function canRetry(): bool
    {
        return $this->hasFailed();
    }

    // =========================================================================
    // Processing Methods
    // =========================================================================

    /**
     * Mark the webhook as successfully processed.
     */
    public function markProcessed(): void
    {
        $this->update([
            'processed' => true,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark the webhook as failed with an error message.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'processed' => false,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark the webhook for retry (clears error, increments retry count).
     */
    public function markForRetry(): void
    {
        $this->update([
            'error_message' => null,
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
        ]);
    }

    /**
     * Get the number of times this webhook has been retried.
     */
    public function getRetryCount(): int
    {
        return $this->retry_count;
    }

    /**
     * Check if the webhook has been retried.
     */
    public function hasBeenRetried(): bool
    {
        return $this->retry_count > 0;
    }

    // =========================================================================
    // Query Scopes
    // =========================================================================

    /**
     * Scope to get only processed webhooks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StripeWebhook>  $query
     * @return \Illuminate\Database\Eloquent\Builder<StripeWebhook>
     */
    public function scopeProcessed($query)
    {
        return $query->where('processed', true);
    }

    /**
     * Scope to get only pending webhooks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StripeWebhook>  $query
     * @return \Illuminate\Database\Eloquent\Builder<StripeWebhook>
     */
    public function scopePending($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope to get only failed webhooks (has error message but not processed).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StripeWebhook>  $query
     * @return \Illuminate\Database\Eloquent\Builder<StripeWebhook>
     */
    public function scopeFailed($query)
    {
        return $query->where('processed', false)
            ->whereNotNull('error_message');
    }

    /**
     * Scope to filter by event type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StripeWebhook>  $query
     * @return \Illuminate\Database\Eloquent\Builder<StripeWebhook>
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    // =========================================================================
    // Static Methods
    // =========================================================================

    /**
     * Check if an event has already been received (for idempotency).
     */
    public static function hasEvent(string $eventId): bool
    {
        return self::where('event_id', $eventId)->exists();
    }

    /**
     * Get an existing webhook by event ID.
     */
    public static function findByEventId(string $eventId): ?self
    {
        return self::where('event_id', $eventId)->first();
    }

    /**
     * Create a webhook log entry from a Stripe event.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function createFromStripeEvent(string $eventId, string $eventType, array $payload): self
    {
        return self::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    // =========================================================================
    // Payload Helper Methods
    // =========================================================================

    /**
     * Get a value from the payload using dot notation.
     */
    public function getPayloadValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Get the payment intent ID from the payload (if present).
     */
    public function getPaymentIntentId(): ?string
    {
        // Check common locations in Stripe payloads
        $paymentIntentId = $this->getPayloadValue('data.object.id');

        if ($paymentIntentId && str_starts_with((string) $paymentIntentId, 'pi_')) {
            return (string) $paymentIntentId;
        }

        // Also check if it's nested under payment_intent
        return $this->getPayloadValue('data.object.payment_intent');
    }

    /**
     * Get the charge ID from the payload (if present).
     */
    public function getChargeId(): ?string
    {
        $chargeId = $this->getPayloadValue('data.object.id');

        if ($chargeId && str_starts_with((string) $chargeId, 'ch_')) {
            return (string) $chargeId;
        }

        return $this->getPayloadValue('data.object.latest_charge');
    }

    /**
     * Get the amount from the payload (in cents).
     */
    public function getAmountInCents(): ?int
    {
        $amount = $this->getPayloadValue('data.object.amount');

        return $amount !== null ? (int) $amount : null;
    }

    /**
     * Get the amount from the payload (in currency units).
     */
    public function getAmount(): ?string
    {
        $cents = $this->getAmountInCents();

        if ($cents === null) {
            return null;
        }

        return bcdiv((string) $cents, '100', 2);
    }

    /**
     * Get the currency from the payload.
     */
    public function getCurrency(): ?string
    {
        $currency = $this->getPayloadValue('data.object.currency');

        return $currency !== null ? strtoupper((string) $currency) : null;
    }

    /**
     * Get the customer ID from the payload.
     */
    public function getStripeCustomerId(): ?string
    {
        return $this->getPayloadValue('data.object.customer');
    }

    // =========================================================================
    // Display Methods
    // =========================================================================

    /**
     * Get a human-readable event type label.
     */
    public function getEventTypeLabel(): string
    {
        return match ($this->event_type) {
            'payment_intent.succeeded' => 'Payment Succeeded',
            'payment_intent.payment_failed' => 'Payment Failed',
            'payment_intent.created' => 'Payment Created',
            'charge.refunded' => 'Charge Refunded',
            'charge.dispute.created' => 'Dispute Created',
            'charge.dispute.closed' => 'Dispute Closed',
            'customer.subscription.created' => 'Subscription Created',
            'customer.subscription.updated' => 'Subscription Updated',
            'customer.subscription.deleted' => 'Subscription Deleted',
            'invoice.paid' => 'Invoice Paid',
            'invoice.payment_failed' => 'Invoice Payment Failed',
            default => str_replace(['.', '_'], [' - ', ' '], $this->event_type),
        };
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        if ($this->processed) {
            return 'Processed';
        }

        if ($this->error_message !== null) {
            return 'Failed';
        }

        return 'Pending';
    }

    /**
     * Get the status color for display.
     */
    public function getStatusColor(): string
    {
        if ($this->processed) {
            return 'success';
        }

        if ($this->error_message !== null) {
            return 'danger';
        }

        return 'warning';
    }

    /**
     * Get the status icon for display.
     */
    public function getStatusIcon(): string
    {
        if ($this->processed) {
            return 'heroicon-o-check-circle';
        }

        if ($this->error_message !== null) {
            return 'heroicon-o-x-circle';
        }

        return 'heroicon-o-clock';
    }
}
