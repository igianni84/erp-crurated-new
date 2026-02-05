<?php

namespace App\Models\Finance;

use App\Enums\Finance\StorageBillingStatus;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Inventory\Location;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * StorageBillingPeriod Model
 *
 * Represents a storage billing period for a customer, tracking bottle-days
 * and calculated amounts for storage invoicing (INV3).
 *
 * @property string $id
 * @property string $customer_id
 * @property string|null $location_id
 * @property \Carbon\Carbon $period_start
 * @property \Carbon\Carbon $period_end
 * @property int $bottle_count
 * @property int $bottle_days
 * @property string $unit_rate
 * @property string $calculated_amount
 * @property string $currency
 * @property StorageBillingStatus $status
 * @property string|null $invoice_id
 * @property \Carbon\Carbon $calculated_at
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class StorageBillingPeriod extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'storage_billing_periods';

    protected $fillable = [
        'customer_id',
        'location_id',
        'period_start',
        'period_end',
        'bottle_count',
        'bottle_days',
        'unit_rate',
        'calculated_amount',
        'currency',
        'status',
        'invoice_id',
        'calculated_at',
        'metadata',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'status' => 'pending',
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
            'status' => StorageBillingStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'bottle_count' => 'integer',
            'bottle_days' => 'integer',
            'unit_rate' => 'decimal:4',
            'calculated_amount' => 'decimal:2',
            'calculated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Validate status transitions
        static::updating(function (StorageBillingPeriod $period): void {
            if ($period->isDirty('status')) {
                $originalStatus = StorageBillingStatus::tryFrom($period->getOriginal('status'));
                $newStatus = $period->status;

                if ($originalStatus !== null && ! $originalStatus->canTransitionTo($newStatus)) {
                    throw new InvalidArgumentException(
                        "Cannot transition storage billing period from '{$originalStatus->value}' to '{$newStatus->value}'."
                    );
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
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
     * Check if the period is pending (not yet invoiced).
     */
    public function isPending(): bool
    {
        return $this->status === StorageBillingStatus::Pending;
    }

    /**
     * Check if the period has been invoiced.
     */
    public function isInvoiced(): bool
    {
        return $this->status === StorageBillingStatus::Invoiced;
    }

    /**
     * Check if the period has been paid.
     */
    public function isPaid(): bool
    {
        return $this->status === StorageBillingStatus::Paid;
    }

    /**
     * Check if the period is blocked (custody blocked due to non-payment).
     */
    public function isBlocked(): bool
    {
        return $this->status === StorageBillingStatus::Blocked;
    }

    /**
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if invoice generation is allowed.
     */
    public function allowsInvoicing(): bool
    {
        return $this->status->allowsInvoicing();
    }

    /**
     * Check if custody operations are blocked.
     */
    public function custodyBlocked(): bool
    {
        return $this->status->custodyBlocked();
    }

    /**
     * Check if this status requires attention.
     */
    public function requiresAttention(): bool
    {
        return $this->status->requiresAttention();
    }

    /**
     * Check if status can transition to the given status.
     */
    public function canTransitionTo(StorageBillingStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    // =========================================================================
    // Invoice Helper Methods
    // =========================================================================

    /**
     * Check if an invoice has been generated for this period.
     */
    public function hasInvoice(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * Check if this period has a location (vs aggregated).
     */
    public function hasLocation(): bool
    {
        return $this->location_id !== null;
    }

    // =========================================================================
    // Calculation Helper Methods
    // =========================================================================

    /**
     * Get the number of days in this billing period.
     */
    public function getPeriodDays(): int
    {
        return (int) $this->period_start->diffInDays($this->period_end) + 1;
    }

    /**
     * Calculate the average bottles per day.
     */
    public function getAverageBottlesPerDay(): float
    {
        $days = $this->getPeriodDays();
        if ($days === 0) {
            return 0.0;
        }

        return $this->bottle_days / $days;
    }

    /**
     * Recalculate the amount based on bottle_days and unit_rate.
     */
    public function recalculateAmount(): void
    {
        $this->calculated_amount = bcmul((string) $this->bottle_days, (string) $this->unit_rate, 2);
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
     * Get formatted amount with currency.
     */
    public function getFormattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->calculated_amount, 2);
    }

    /**
     * Get formatted unit rate.
     */
    public function getFormattedUnitRate(): string
    {
        return $this->currency.' '.number_format((float) $this->unit_rate, 4);
    }

    /**
     * Get a display label for the period.
     */
    public function getPeriodLabel(): string
    {
        return $this->period_start->format('Y-m-d').' to '.$this->period_end->format('Y-m-d');
    }

    /**
     * Get a display name including customer and period.
     */
    public function getDisplayName(): string
    {
        $locationName = $this->location !== null ? ' @ '.$this->location->name : '';

        return $this->getPeriodLabel().$locationName;
    }

    // =========================================================================
    // Block/Unblock Methods
    // =========================================================================

    /**
     * Unblock this storage billing period after payment.
     *
     * This method transitions the period from blocked to paid status,
     * removes the custody block, and logs the event.
     *
     * @param  int|null  $userId  The user who authorized the unblock (null for system)
     * @param  string|null  $reason  Optional reason for the unblock
     *
     * @throws InvalidArgumentException if period is not currently blocked
     */
    public function unblock(?int $userId = null, ?string $reason = null): void
    {
        if (! $this->isBlocked()) {
            throw new InvalidArgumentException(
                "Cannot unblock storage billing period: current status is '{$this->status->value}', expected 'blocked'."
            );
        }

        $this->status = StorageBillingStatus::Paid;
        $this->save();

        // Log the unblock in audit trail
        $this->auditLogs()->create([
            'event' => 'storage_billing_unblocked',
            'old_values' => ['status' => StorageBillingStatus::Blocked->value],
            'new_values' => [
                'status' => StorageBillingStatus::Paid->value,
                'reason' => $reason ?? 'Payment received - custody block removed',
            ],
            'user_id' => $userId,
        ]);
    }

    /**
     * Get the overdue invoice that caused this period to be blocked.
     *
     * Returns null if not blocked or if the invoice cannot be found.
     */
    public function getBlockingInvoice(): ?Invoice
    {
        if (! $this->isBlocked()) {
            return null;
        }

        return $this->invoice;
    }

    /**
     * Get the number of days this period has been blocked.
     *
     * Returns null if not blocked.
     */
    public function getDaysBlocked(): ?int
    {
        if (! $this->isBlocked()) {
            return null;
        }

        // Look for the block event in audit logs
        $blockEvent = $this->auditLogs()
            ->where('event', 'storage_billing_blocked')
            ->latest()
            ->first();

        if ($blockEvent === null) {
            return null;
        }

        return (int) $blockEvent->created_at->diffInDays(now());
    }

    /**
     * Get block reason from audit log.
     */
    public function getBlockReason(): ?string
    {
        if (! $this->isBlocked()) {
            return null;
        }

        $blockEvent = $this->auditLogs()
            ->where('event', 'storage_billing_blocked')
            ->latest()
            ->first();

        if ($blockEvent === null) {
            return null;
        }

        /** @var array<string, mixed>|null $newValues */
        $newValues = $blockEvent->new_values;

        if ($newValues === null || ! isset($newValues['reason'])) {
            return null;
        }

        return (string) $newValues['reason'];
    }

    /**
     * Check if custody operations should be blocked for the related customer.
     *
     * This is a convenience method that checks the status and provides
     * a clear API for other modules (B, C) to use.
     */
    public function shouldBlockCustodyOperations(): bool
    {
        return $this->status === StorageBillingStatus::Blocked;
    }

    /**
     * Get the resolution instructions for a blocked period.
     */
    public function getResolutionInstructions(): ?string
    {
        if (! $this->isBlocked()) {
            return null;
        }

        $invoice = $this->invoice;
        if ($invoice === null) {
            return 'Pay the outstanding storage invoice to remove this block.';
        }

        $outstanding = $invoice->getOutstandingAmount();
        $invoiceNumber = $invoice->invoice_number ?? 'Draft';

        return "Pay invoice {$invoiceNumber} ({$invoice->currency} {$outstanding} outstanding) to remove this custody block.";
    }

    // =========================================================================
    // Static Query Helpers
    // =========================================================================

    /**
     * Get blocked storage billing periods for a specific customer.
     *
     * @return \Illuminate\Database\Eloquent\Builder<StorageBillingPeriod>
     */
    public static function blockedForCustomer(string $customerId): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \Illuminate\Database\Eloquent\Builder<StorageBillingPeriod> $query */
        $query = static::query()
            ->where('customer_id', $customerId)
            ->where('status', StorageBillingStatus::Blocked);

        return $query;
    }

    /**
     * Check if a customer has any blocked storage billing periods.
     */
    public static function customerHasBlockedPeriods(string $customerId): bool
    {
        return static::blockedForCustomer($customerId)->exists();
    }

    /**
     * Get the count of blocked periods for a customer.
     */
    public static function getBlockedCountForCustomer(string $customerId): int
    {
        return static::blockedForCustomer($customerId)->count();
    }
}
