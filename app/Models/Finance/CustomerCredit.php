<?php

namespace App\Models\Finance;

use App\Enums\Finance\CustomerCreditStatus;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * CustomerCredit Model
 *
 * Represents customer credits from overpayments that can be applied to future invoices.
 * Customer credits are visible in the customer finance view.
 *
 * @property string $id
 * @property string $uuid
 * @property int $customer_id
 * @property int|null $source_payment_id
 * @property int|null $source_invoice_id
 * @property string $original_amount
 * @property string $remaining_amount
 * @property string $currency
 * @property CustomerCreditStatus $status
 * @property string $reason
 * @property string|null $notes
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class CustomerCredit extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'customer_credits';

    protected $fillable = [
        'customer_id',
        'source_payment_id',
        'source_invoice_id',
        'original_amount',
        'remaining_amount',
        'currency',
        'status',
        'reason',
        'notes',
        'expires_at',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'available',
        'currency' => 'EUR',
    ];

    /**
     * @var list<string>
     */
    public array $auditExcludeFields = [
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerCreditStatus::class,
            'original_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Set remaining_amount to original_amount on creation if not set
        static::creating(function (CustomerCredit $credit): void {
            if (! isset($credit->attributes['remaining_amount'])) {
                $credit->remaining_amount = $credit->original_amount;
            }
        });

        // Validate status transitions
        static::updating(function (CustomerCredit $credit): void {
            if ($credit->isDirty('status')) {
                $originalStatus = $credit->getOriginal('status');
                $newStatus = $credit->status;

                // Handle string to enum conversion if needed
                if (is_string($originalStatus)) {
                    $originalStatus = CustomerCreditStatus::from($originalStatus);
                }

                if (! $originalStatus->canTransitionTo($newStatus)) {
                    throw new InvalidArgumentException(
                        "Invalid status transition from '{$originalStatus->label()}' to '{$newStatus->label()}'."
                    );
                }
            }

            // Validate amounts
            if ($credit->isDirty('remaining_amount')) {
                if (bccomp($credit->remaining_amount, '0', 2) < 0) {
                    throw new InvalidArgumentException(
                        'Remaining amount cannot be negative.'
                    );
                }

                if (bccomp($credit->remaining_amount, $credit->original_amount, 2) > 0) {
                    throw new InvalidArgumentException(
                        'Remaining amount cannot exceed original amount.'
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
     * @return BelongsTo<Payment, $this>
     */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * Check if credit is available for use.
     */
    public function isAvailable(): bool
    {
        return $this->status === CustomerCreditStatus::Available;
    }

    /**
     * Check if credit is partially used.
     */
    public function isPartiallyUsed(): bool
    {
        return $this->status === CustomerCreditStatus::PartiallyUsed;
    }

    /**
     * Check if credit is fully used.
     */
    public function isFullyUsed(): bool
    {
        return $this->status === CustomerCreditStatus::FullyUsed;
    }

    /**
     * Check if credit is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === CustomerCreditStatus::Expired;
    }

    /**
     * Check if credit is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === CustomerCreditStatus::Cancelled;
    }

    /**
     * Check if credit can be used (applied to invoices).
     */
    public function canBeUsed(): bool
    {
        // Check status allows use
        if (! $this->status->canBeUsed()) {
            return false;
        }

        // Check not expired
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        // Check has remaining amount
        return bccomp($this->remaining_amount, '0', 2) > 0;
    }

    /**
     * Check if credit has expired by date.
     */
    public function hasExpiredByDate(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    // =========================================================================
    // Amount Operations
    // =========================================================================

    /**
     * Get the used amount.
     */
    public function getUsedAmount(): string
    {
        return bcsub($this->original_amount, $this->remaining_amount, 2);
    }

    /**
     * Use a portion of this credit.
     *
     * @throws InvalidArgumentException If amount exceeds remaining or credit cannot be used
     */
    public function use(string $amount): void
    {
        if (! $this->canBeUsed()) {
            throw new InvalidArgumentException(
                'This credit cannot be used. Status: '.$this->status->label()
            );
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException(
                'Amount must be greater than zero.'
            );
        }

        if (bccomp($amount, $this->remaining_amount, 2) > 0) {
            throw new InvalidArgumentException(
                "Amount ({$amount}) exceeds remaining credit ({$this->remaining_amount})."
            );
        }

        $this->remaining_amount = bcsub($this->remaining_amount, $amount, 2);

        // Update status based on remaining amount
        if (bccomp($this->remaining_amount, '0', 2) <= 0) {
            $this->status = CustomerCreditStatus::FullyUsed;
        } elseif (bccomp($this->remaining_amount, $this->original_amount, 2) < 0) {
            $this->status = CustomerCreditStatus::PartiallyUsed;
        }

        $this->save();
    }

    /**
     * Mark this credit as expired.
     */
    public function markExpired(): void
    {
        if ($this->status->isTerminal()) {
            throw new InvalidArgumentException(
                'Cannot expire a credit that is already in terminal status: '.$this->status->label()
            );
        }

        $this->status = CustomerCreditStatus::Expired;
        $this->save();
    }

    /**
     * Cancel this credit.
     */
    public function cancel(string $reason): void
    {
        if ($this->status->isTerminal()) {
            throw new InvalidArgumentException(
                'Cannot cancel a credit that is already in terminal status: '.$this->status->label()
            );
        }

        $this->status = CustomerCreditStatus::Cancelled;
        $this->notes = ($this->notes ?? '').' Cancelled: '.$reason;
        $this->save();
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
     * Get formatted original amount with currency.
     */
    public function getFormattedOriginalAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->original_amount, 2);
    }

    /**
     * Get formatted remaining amount with currency.
     */
    public function getFormattedRemainingAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->remaining_amount, 2);
    }

    /**
     * Get formatted used amount with currency.
     */
    public function getFormattedUsedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->getUsedAmount(), 2);
    }

    // =========================================================================
    // Query Scopes
    // =========================================================================
    /**
     * Scope to only include available credits.
     *
     * @param  Builder<CustomerCredit>  $query
     * @return Builder<CustomerCredit>
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', CustomerCreditStatus::Available);
    }

    /**
     * Scope to only include usable credits (available or partially used).
     *
     * @param  Builder<CustomerCredit>  $query
     * @return Builder<CustomerCredit>
     */
    public function scopeUsable($query)
    {
        return $query->whereIn('status', [
            CustomerCreditStatus::Available,
            CustomerCreditStatus::PartiallyUsed,
        ])->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->where('remaining_amount', '>', 0);
    }

    /**
     * Scope to only include credits for a specific customer.
     *
     * @param  Builder<CustomerCredit>  $query
     * @return Builder<CustomerCredit>
     */
    public function scopeForCustomer($query, Customer $customer)
    {
        return $query->where('customer_id', $customer->id);
    }

    /**
     * Scope to only include expired credits that need status update.
     *
     * @param  Builder<CustomerCredit>  $query
     * @return Builder<CustomerCredit>
     */
    public function scopeExpiredButNotMarked($query)
    {
        return $query->whereNotIn('status', [
            CustomerCreditStatus::FullyUsed,
            CustomerCreditStatus::Expired,
            CustomerCreditStatus::Cancelled,
        ])->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    // =========================================================================
    // Static Factory Methods
    // =========================================================================

    /**
     * Create a customer credit from an overpayment.
     */
    public static function createFromOverpayment(
        Customer $customer,
        Payment $payment,
        Invoice $invoice,
        string $amount,
        ?int $createdBy = null
    ): CustomerCredit {
        return self::create([
            'customer_id' => $customer->id,
            'source_payment_id' => $payment->id,
            'source_invoice_id' => $invoice->id,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'currency' => $payment->currency,
            'status' => CustomerCreditStatus::Available,
            'reason' => "Overpayment on invoice {$invoice->invoice_number} via payment {$payment->payment_reference}",
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Get total available credit for a customer.
     */
    public static function getTotalAvailableForCustomer(Customer $customer): string
    {
        $total = self::usable()
            ->forCustomer($customer)
            ->sum('remaining_amount');

        return number_format((float) $total, 2, '.', '');
    }
}
