<?php

namespace App\Models\Finance;

use App\Enums\Finance\BillingCycle;
use App\Enums\Finance\SubscriptionPlanType;
use App\Enums\Finance\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * Subscription Model
 *
 * Represents a customer subscription for membership or services.
 * Generates INV0 invoices on billing dates.
 *
 * @property string $id
 * @property string $customer_id
 * @property SubscriptionPlanType $plan_type
 * @property string $plan_name
 * @property BillingCycle $billing_cycle
 * @property string $amount
 * @property string $currency
 * @property SubscriptionStatus $status
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon $next_billing_date
 * @property \Carbon\Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $stripe_subscription_id
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Subscription extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'customer_id',
        'plan_type',
        'plan_name',
        'billing_cycle',
        'amount',
        'currency',
        'status',
        'started_at',
        'next_billing_date',
        'cancelled_at',
        'cancellation_reason',
        'stripe_subscription_id',
        'metadata',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'status' => 'active',
    ];

    /**
     * @var list<string>
     */
    public array $auditExcludeFields = [
        'updated_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'plan_type' => SubscriptionPlanType::class,
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'amount' => 'decimal:2',
            'started_at' => 'date',
            'next_billing_date' => 'date',
            'cancelled_at' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Validate status transitions
        static::updating(function (Subscription $subscription): void {
            if ($subscription->isDirty('status')) {
                $originalStatus = SubscriptionStatus::tryFrom($subscription->getOriginal('status'));
                $newStatus = $subscription->status;

                if ($originalStatus !== null && ! $originalStatus->canTransitionTo($newStatus)) {
                    throw new InvalidArgumentException(
                        "Cannot transition subscription from '{$originalStatus->value}' to '{$newStatus->value}'."
                    );
                }

                // Set cancelled_at when transitioning to cancelled
                if ($newStatus === SubscriptionStatus::Cancelled && $subscription->cancelled_at === null) {
                    $subscription->cancelled_at = now();
                }
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all invoices generated from this subscription.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'source_id')
            ->where('source_type', 'subscription');
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // =========================================================================
    // Status Helper Methods
    // =========================================================================

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /**
     * Check if subscription is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === SubscriptionStatus::Suspended;
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled;
    }

    /**
     * Check if subscription is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if billing is allowed for this subscription.
     */
    public function allowsBilling(): bool
    {
        return $this->status->allowsBilling();
    }

    /**
     * Check if customer has access to membership benefits.
     */
    public function hasAccess(): bool
    {
        return $this->status->hasAccess();
    }

    /**
     * Check if the subscription is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->status->isBlocked();
    }

    /**
     * Check if status can transition to the given status.
     */
    public function canTransitionTo(SubscriptionStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    // =========================================================================
    // Billing Helper Methods
    // =========================================================================

    /**
     * Check if the subscription is due for billing today.
     */
    public function isDueForBilling(): bool
    {
        if (! $this->allowsBilling()) {
            return false;
        }

        return $this->next_billing_date->isToday();
    }

    /**
     * Check if the subscription is overdue for billing.
     */
    public function isOverdueForBilling(): bool
    {
        if (! $this->allowsBilling()) {
            return false;
        }

        return $this->next_billing_date->isPast();
    }

    /**
     * Calculate the next billing date based on the billing cycle.
     */
    public function calculateNextBillingDate(?Carbon $fromDate = null): Carbon
    {
        $from = $fromDate ?? $this->next_billing_date;

        return $from->copy()->addMonths($this->billing_cycle->months());
    }

    /**
     * Get the number of months in the billing cycle.
     */
    public function getBillingCycleMonths(): int
    {
        return $this->billing_cycle->months();
    }

    // =========================================================================
    // Plan Helper Methods
    // =========================================================================

    /**
     * Check if this is a membership subscription.
     */
    public function isMembership(): bool
    {
        return $this->plan_type === SubscriptionPlanType::Membership;
    }

    /**
     * Check if this is a service subscription.
     */
    public function isService(): bool
    {
        return $this->plan_type === SubscriptionPlanType::Service;
    }

    // =========================================================================
    // Stripe Helper Methods
    // =========================================================================

    /**
     * Check if subscription is linked to Stripe.
     */
    public function hasStripeSubscription(): bool
    {
        return $this->stripe_subscription_id !== null;
    }

    // =========================================================================
    // Display Methods
    // =========================================================================

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color for display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon for display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Get the plan type label for display.
     */
    public function getPlanTypeLabel(): string
    {
        return $this->plan_type->label();
    }

    /**
     * Get the plan type color for display.
     */
    public function getPlanTypeColor(): string
    {
        return $this->plan_type->color();
    }

    /**
     * Get the plan type icon for display.
     */
    public function getPlanTypeIcon(): string
    {
        return $this->plan_type->icon();
    }

    /**
     * Get the billing cycle label for display.
     */
    public function getBillingCycleLabel(): string
    {
        return $this->billing_cycle->label();
    }

    /**
     * Get the billing cycle color for display.
     */
    public function getBillingCycleColor(): string
    {
        return $this->billing_cycle->color();
    }

    /**
     * Get the billing cycle icon for display.
     */
    public function getBillingCycleIcon(): string
    {
        return $this->billing_cycle->icon();
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 2);
    }

    /**
     * Get a display name for the subscription.
     */
    public function getDisplayName(): string
    {
        return $this->plan_name.' ('.$this->billing_cycle->label().')';
    }
}
