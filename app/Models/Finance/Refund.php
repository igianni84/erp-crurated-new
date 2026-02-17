<?php

namespace App\Models\Finance;

use App\Enums\Finance\RefundMethod;
use App\Enums\Finance\RefundStatus;
use App\Enums\Finance\RefundType;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * Refund Model
 *
 * Represents a refund issued for a payment linked to an invoice.
 * Refunds require both an invoice and payment to be linked.
 *
 * @property int $id
 * @property string $uuid
 * @property int $invoice_id
 * @property int $payment_id
 * @property int|null $credit_note_id
 * @property RefundType $refund_type
 * @property RefundMethod $method
 * @property string $amount
 * @property string $currency
 * @property RefundStatus $status
 * @property string $reason
 * @property string|null $stripe_refund_id
 * @property string|null $bank_reference
 * @property Carbon|null $processed_at
 * @property int|null $processed_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Refund extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'refunds';

    protected $fillable = [
        'invoice_id',
        'payment_id',
        'credit_note_id',
        'refund_type',
        'method',
        'amount',
        'currency',
        'status',
        'reason',
        'stripe_refund_id',
        'bank_reference',
        'processed_at',
        'processed_by',
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
            'refund_type' => RefundType::class,
            'method' => RefundMethod::class,
            'status' => RefundStatus::class,
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
            'processed_by' => 'integer',
            'invoice_id' => 'integer',
            'payment_id' => 'integer',
            'credit_note_id' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Validate invoice and payment are linked when creating
        static::creating(function (Refund $refund): void {
            $refund->validateInvoicePaymentLink();
            $refund->validateAmountAgainstPayment();
        });

        // Validate on updates as well
        static::updating(function (Refund $refund): void {
            // Cannot change invoice_id or payment_id after creation
            if ($refund->isDirty('invoice_id')) {
                throw new InvalidArgumentException('Cannot change the invoice after refund creation.');
            }
            if ($refund->isDirty('payment_id')) {
                throw new InvalidArgumentException('Cannot change the payment after refund creation.');
            }

            // Validate amount if changed
            if ($refund->isDirty('amount')) {
                $refund->validateAmountAgainstPayment();
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // =========================================================================
    // Validation Methods
    // =========================================================================

    /**
     * Validate that the payment is linked to the invoice.
     *
     * @throws InvalidArgumentException
     */
    protected function validateInvoicePaymentLink(): void
    {
        $invoicePayment = InvoicePayment::where('invoice_id', $this->invoice_id)
            ->where('payment_id', $this->payment_id)
            ->first();

        if ($invoicePayment === null) {
            throw new InvalidArgumentException(
                'The payment must be applied to the invoice before creating a refund.'
            );
        }
    }

    /**
     * Validate that the refund amount does not exceed the payment applied amount.
     *
     * @throws InvalidArgumentException
     */
    protected function validateAmountAgainstPayment(): void
    {
        $invoicePayment = InvoicePayment::where('invoice_id', $this->invoice_id)
            ->where('payment_id', $this->payment_id)
            ->first();

        if ($invoicePayment !== null) {
            if (bccomp($this->amount, $invoicePayment->amount_applied, 2) > 0) {
                throw new InvalidArgumentException(
                    'Refund amount cannot exceed the payment applied amount ('.$invoicePayment->amount_applied.').'
                );
            }
        }
    }

    // =========================================================================
    // Status Helper Methods
    // =========================================================================

    /**
     * Check if refund is pending.
     */
    public function isPending(): bool
    {
        return $this->status === RefundStatus::Pending;
    }

    /**
     * Check if refund has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === RefundStatus::Processed;
    }

    /**
     * Check if refund has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === RefundStatus::Failed;
    }

    /**
     * Check if refund can be retried.
     */
    public function canBeRetried(): bool
    {
        return $this->status->allowsRetry();
    }

    /**
     * Check if refund is in terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    // =========================================================================
    // Type Helper Methods
    // =========================================================================

    /**
     * Check if this is a full refund.
     */
    public function isFullRefund(): bool
    {
        return $this->refund_type === RefundType::Full;
    }

    /**
     * Check if this is a partial refund.
     */
    public function isPartialRefund(): bool
    {
        return $this->refund_type === RefundType::Partial;
    }

    // =========================================================================
    // Method Helper Methods
    // =========================================================================

    /**
     * Check if refund is via Stripe.
     */
    public function isStripeRefund(): bool
    {
        return $this->method === RefundMethod::Stripe;
    }

    /**
     * Check if refund is via bank transfer.
     */
    public function isBankTransferRefund(): bool
    {
        return $this->method === RefundMethod::BankTransfer;
    }

    /**
     * Check if refund method supports automatic processing.
     */
    public function supportsAutoProcess(): bool
    {
        return $this->method->supportsAutoProcess();
    }

    /**
     * Check if refund method requires manual tracking.
     */
    public function requiresManualTracking(): bool
    {
        return $this->method->requiresManualTracking();
    }

    // =========================================================================
    // Processing Methods
    // =========================================================================

    /**
     * Check if Stripe refund can be processed.
     */
    public function canProcessStripeRefund(): bool
    {
        if (! $this->isStripeRefund()) {
            return false;
        }

        if (! $this->isPending()) {
            return false;
        }

        $payment = $this->payment;

        return $payment !== null && $payment->stripe_charge_id !== null;
    }

    /**
     * Check if bank refund can be marked as processed.
     */
    public function canMarkBankRefundProcessed(): bool
    {
        return $this->isBankTransferRefund() && $this->isPending();
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
     * Get the refund type label for display.
     */
    public function getTypeLabel(): string
    {
        return $this->refund_type->label();
    }

    /**
     * Get the refund type color for display.
     */
    public function getTypeColor(): string
    {
        return $this->refund_type->color();
    }

    /**
     * Get the refund type icon for display.
     */
    public function getTypeIcon(): string
    {
        return $this->refund_type->icon();
    }

    /**
     * Get the method label for display.
     */
    public function getMethodLabel(): string
    {
        return $this->method->label();
    }

    /**
     * Get the method color for display.
     */
    public function getMethodColor(): string
    {
        return $this->method->color();
    }

    /**
     * Get the method icon for display.
     */
    public function getMethodIcon(): string
    {
        return $this->method->icon();
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 2);
    }

    /**
     * Get a truncated reason for list display.
     */
    public function getTruncatedReason(int $maxLength = 50): string
    {
        if (strlen($this->reason) <= $maxLength) {
            return $this->reason;
        }

        return substr($this->reason, 0, $maxLength).'...';
    }

    /**
     * Get the external reference (Stripe ID or bank reference).
     */
    public function getExternalReference(): ?string
    {
        if ($this->isStripeRefund()) {
            return $this->stripe_refund_id;
        }

        return $this->bank_reference;
    }
}
