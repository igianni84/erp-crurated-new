<?php

namespace App\Models\Finance;

use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
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
 * Payment Model
 *
 * Represents a received payment from any source (Stripe or bank transfer).
 * Payments are linked to invoices via the InvoicePayment pivot model.
 *
 * @property string $id
 * @property string $payment_reference
 * @property PaymentSource $source
 * @property string $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property ReconciliationStatus $reconciliation_status
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_charge_id
 * @property string|null $bank_reference
 * @property Carbon $received_at
 * @property string|null $customer_id
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Payment extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'payments';

    protected $fillable = [
        'payment_reference',
        'source',
        'amount',
        'currency',
        'status',
        'reconciliation_status',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'bank_reference',
        'received_at',
        'customer_id',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'pending',
        'reconciliation_status' => 'pending',
    ];

    /**
     * @var list<string>
     */
    public array $auditExcludeFields = [
        'updated_at',
        'updated_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Enforce immutability on core payment fields
        static::updating(function (Payment $payment): void {
            // payment_reference and source are always immutable
            $alwaysImmutable = ['payment_reference', 'source'];
            foreach ($alwaysImmutable as $field) {
                if ($payment->isDirty($field)) {
                    throw new InvalidArgumentException(
                        "{$field} cannot be modified after creation. Payment {$field} is immutable."
                    );
                }
            }

            // After confirmation, amount, currency and received_at become immutable
            $originalStatus = $payment->getRawOriginal('status');
            if ($originalStatus !== PaymentStatus::Pending->value) {
                $immutableAfterConfirmation = ['amount', 'currency', 'received_at'];
                foreach ($immutableAfterConfirmation as $field) {
                    if ($payment->isDirty($field)) {
                        throw new InvalidArgumentException(
                            "{$field} cannot be modified after payment is confirmed."
                        );
                    }
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'source' => PaymentSource::class,
            'status' => PaymentStatus::class,
            'reconciliation_status' => ReconciliationStatus::class,
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
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
     * @return HasMany<InvoicePayment, $this>
     */
    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
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
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    /**
     * Check if payment is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === PaymentStatus::Confirmed;
    }

    /**
     * Check if payment has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    /**
     * Check if payment has been refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::Refunded;
    }

    /**
     * Check if payment can be applied to invoices.
     */
    public function canBeAppliedToInvoice(): bool
    {
        return $this->status->allowsInvoiceApplication();
    }

    /**
     * Check if payment can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->status->allowsRefund();
    }

    // =========================================================================
    // Reconciliation Helper Methods
    // =========================================================================

    /**
     * Check if payment reconciliation is pending.
     */
    public function isReconciliationPending(): bool
    {
        return $this->reconciliation_status === ReconciliationStatus::Pending;
    }

    /**
     * Check if payment is reconciled (matched).
     */
    public function isReconciled(): bool
    {
        return $this->reconciliation_status === ReconciliationStatus::Matched;
    }

    /**
     * Check if payment has a mismatch.
     */
    public function hasMismatch(): bool
    {
        return $this->reconciliation_status === ReconciliationStatus::Mismatched;
    }

    /**
     * Check if reconciliation requires attention.
     */
    public function requiresReconciliationAttention(): bool
    {
        return $this->reconciliation_status->requiresAttention();
    }

    /**
     * Check if this payment can trigger business events.
     * Only matched payments should trigger downstream events.
     */
    public function canTriggerBusinessEvents(): bool
    {
        return $this->reconciliation_status->allowsBusinessEvents()
            && $this->status === PaymentStatus::Confirmed;
    }

    // =========================================================================
    // Source Helper Methods
    // =========================================================================

    /**
     * Check if payment is from Stripe.
     */
    public function isFromStripe(): bool
    {
        return $this->source === PaymentSource::Stripe;
    }

    /**
     * Check if payment is from bank transfer.
     */
    public function isFromBankTransfer(): bool
    {
        return $this->source === PaymentSource::BankTransfer;
    }

    /**
     * Check if payment supports automatic reconciliation.
     */
    public function supportsAutoReconciliation(): bool
    {
        return $this->source->supportsAutoReconciliation();
    }

    /**
     * Check if payment supports automatic refunds.
     */
    public function supportsAutoRefund(): bool
    {
        return $this->source->supportsAutoRefund();
    }

    // =========================================================================
    // Computed Properties
    // =========================================================================

    /**
     * Get the total amount applied to invoices.
     */
    public function getTotalAppliedAmount(): string
    {
        $total = $this->invoicePayments()->sum('amount_applied');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Get the unapplied (remaining) amount.
     */
    public function getUnappliedAmount(): string
    {
        return bcsub($this->amount, $this->getTotalAppliedAmount(), 2);
    }

    /**
     * Check if payment is fully applied to invoices.
     */
    public function isFullyApplied(): bool
    {
        return bccomp($this->getTotalAppliedAmount(), $this->amount, 2) >= 0;
    }

    /**
     * Check if payment has any applied amounts.
     */
    public function hasApplications(): bool
    {
        return bccomp($this->getTotalAppliedAmount(), '0', 2) > 0;
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
     * Get the reconciliation status label for display.
     */
    public function getReconciliationStatusLabel(): string
    {
        return $this->reconciliation_status->label();
    }

    /**
     * Get the reconciliation status color for display.
     */
    public function getReconciliationStatusColor(): string
    {
        return $this->reconciliation_status->color();
    }

    /**
     * Get the reconciliation status icon for display.
     */
    public function getReconciliationStatusIcon(): string
    {
        return $this->reconciliation_status->icon();
    }

    /**
     * Get the source label for display.
     */
    public function getSourceLabel(): string
    {
        return $this->source->label();
    }

    /**
     * Get the source color for display.
     */
    public function getSourceColor(): string
    {
        return $this->source->color();
    }

    /**
     * Get the source icon for display.
     */
    public function getSourceIcon(): string
    {
        return $this->source->icon();
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 2);
    }

    /**
     * Get formatted unapplied amount with currency.
     */
    public function getFormattedUnappliedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->getUnappliedAmount(), 2);
    }

    // =========================================================================
    // Mismatch Type Constants
    // =========================================================================

    /**
     * Mismatch reason: Amount on payment doesn't match any invoice.
     */
    public const MISMATCH_AMOUNT_DIFFERENCE = 'amount_difference';

    /**
     * Mismatch reason: Customer on payment doesn't match invoice customer.
     */
    public const MISMATCH_CUSTOMER_MISMATCH = 'customer_mismatch';

    /**
     * Mismatch reason: Possible duplicate payment detected.
     */
    public const MISMATCH_DUPLICATE = 'duplicate';

    /**
     * Mismatch reason: No customer identified on payment.
     */
    public const MISMATCH_NO_CUSTOMER = 'no_customer';

    /**
     * Mismatch reason: No matching invoice found.
     */
    public const MISMATCH_NO_MATCH = 'no_match';

    /**
     * Mismatch reason: Multiple invoices match the payment.
     */
    public const MISMATCH_MULTIPLE_MATCHES = 'multiple_matches';

    /**
     * Mismatch reason: Application to invoice failed.
     */
    public const MISMATCH_APPLICATION_FAILED = 'application_failed';

    /**
     * Get the valid mismatch types.
     *
     * @return array<string, string>
     */
    public static function getMismatchTypes(): array
    {
        return [
            self::MISMATCH_AMOUNT_DIFFERENCE => 'Amount Difference',
            self::MISMATCH_CUSTOMER_MISMATCH => 'Customer Mismatch',
            self::MISMATCH_DUPLICATE => 'Possible Duplicate',
            self::MISMATCH_NO_CUSTOMER => 'No Customer',
            self::MISMATCH_NO_MATCH => 'No Match',
            self::MISMATCH_MULTIPLE_MATCHES => 'Multiple Matches',
            self::MISMATCH_APPLICATION_FAILED => 'Application Failed',
        ];
    }

    // =========================================================================
    // Mismatch Helper Methods
    // =========================================================================

    /**
     * Get the mismatch type from metadata.
     */
    public function getMismatchType(): ?string
    {
        if (! $this->hasMismatch()) {
            return null;
        }

        // Check metadata for stored type
        if ($this->metadata !== null && isset($this->metadata['mismatch_details']['reason'])) {
            return (string) $this->metadata['mismatch_details']['reason'];
        }

        // Also check for legacy 'reason' in metadata root
        if ($this->metadata !== null && isset($this->metadata['reason'])) {
            return (string) $this->metadata['reason'];
        }

        return null;
    }

    /**
     * Get the human-readable label for the mismatch type.
     */
    public function getMismatchTypeLabel(): ?string
    {
        $type = $this->getMismatchType();

        if ($type === null) {
            // If no type but has mismatch, infer from state
            if (! $this->hasMismatch()) {
                return null;
            }

            if ($this->customer === null) {
                return self::getMismatchTypes()[self::MISMATCH_NO_CUSTOMER];
            }

            if (! $this->hasApplications()) {
                return self::getMismatchTypes()[self::MISMATCH_NO_MATCH];
            }

            return 'Unknown';
        }

        return self::getMismatchTypes()[$type] ?? 'Unknown';
    }

    /**
     * Check if mismatch is due to amount difference.
     */
    public function isAmountMismatch(): bool
    {
        return $this->getMismatchType() === self::MISMATCH_AMOUNT_DIFFERENCE;
    }

    /**
     * Check if mismatch is due to customer mismatch.
     */
    public function isCustomerMismatch(): bool
    {
        return $this->getMismatchType() === self::MISMATCH_CUSTOMER_MISMATCH;
    }

    /**
     * Check if mismatch is due to possible duplicate.
     */
    public function isDuplicateMismatch(): bool
    {
        return $this->getMismatchType() === self::MISMATCH_DUPLICATE;
    }

    /**
     * Get the mismatch reason from metadata.
     */
    public function getMismatchReason(): string
    {
        if (! $this->hasMismatch()) {
            return 'No mismatch';
        }

        // Check metadata for stored reason
        if ($this->metadata !== null && isset($this->metadata['mismatch_reason'])) {
            return (string) $this->metadata['mismatch_reason'];
        }

        // Infer reason based on payment state
        if ($this->customer === null) {
            return 'Customer not identified - unable to match to invoices';
        }

        if (! $this->hasApplications()) {
            return 'Payment amount does not match any open invoice for this customer';
        }

        return 'Unknown mismatch reason - review payment details';
    }

    /**
     * Get additional mismatch details from metadata.
     *
     * @return array<string, mixed>
     */
    public function getMismatchDetails(): array
    {
        if (! $this->hasMismatch()) {
            return [];
        }

        $details = [];

        // Include any mismatch-related metadata
        if ($this->metadata !== null) {
            if (isset($this->metadata['mismatch_details'])) {
                $mismatchDetails = $this->metadata['mismatch_details'];
                if (is_array($mismatchDetails)) {
                    $details = array_merge($details, $mismatchDetails);
                }
            }

            if (isset($this->metadata['expected_amount'])) {
                $details['expected_amount'] = $this->metadata['expected_amount'];
            }

            if (isset($this->metadata['expected_customer'])) {
                $details['expected_customer'] = $this->metadata['expected_customer'];
            }

            if (isset($this->metadata['duplicate_of'])) {
                $details['possible_duplicate'] = $this->metadata['duplicate_of'];
            }
        }

        return $details;
    }

    /**
     * Set mismatch information in metadata.
     *
     * @param  array<string, mixed>  $details
     */
    public function setMismatchInfo(string $reason, array $details = []): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['mismatch_reason'] = $reason;

        if (! empty($details)) {
            $metadata['mismatch_details'] = $details;
        }

        $this->metadata = $metadata;
    }

    /**
     * Clear mismatch information from metadata.
     */
    public function clearMismatchInfo(): void
    {
        if ($this->metadata === null) {
            return;
        }

        $metadata = $this->metadata;
        unset($metadata['mismatch_reason'], $metadata['mismatch_details'], $metadata['expected_amount'], $metadata['expected_customer'], $metadata['duplicate_of']);
        $this->metadata = empty($metadata) ? null : $metadata;
    }
}
