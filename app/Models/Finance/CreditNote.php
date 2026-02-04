<?php

namespace App\Models\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CreditNote Model
 *
 * Represents a credit note issued against an invoice.
 * Credit notes preserve the invoice_type of the original invoice.
 *
 * @property string $id
 * @property string|null $credit_note_number
 * @property string $invoice_id
 * @property string $customer_id
 * @property string $amount
 * @property string $currency
 * @property string $reason
 * @property CreditNoteStatus $status
 * @property \Carbon\Carbon|null $issued_at
 * @property \Carbon\Carbon|null $applied_at
 * @property int|null $issued_by
 * @property string|null $xero_credit_note_id
 * @property \Carbon\Carbon|null $xero_synced_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class CreditNote extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'credit_notes';

    protected $fillable = [
        'credit_note_number',
        'invoice_id',
        'customer_id',
        'amount',
        'currency',
        'reason',
        'status',
        'issued_at',
        'applied_at',
        'issued_by',
        'xero_credit_note_id',
        'xero_synced_at',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'status' => 'draft',
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
            'status' => CreditNoteStatus::class,
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'applied_at' => 'datetime',
            'xero_synced_at' => 'datetime',
            'issued_by' => 'integer',
        ];
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
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
     * Check if credit note is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === CreditNoteStatus::Draft;
    }

    /**
     * Check if credit note has been issued.
     */
    public function isIssued(): bool
    {
        return $this->status === CreditNoteStatus::Issued;
    }

    /**
     * Check if credit note has been applied.
     */
    public function isApplied(): bool
    {
        return $this->status === CreditNoteStatus::Applied;
    }

    /**
     * Check if credit note can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status->allowsEditing();
    }

    /**
     * Check if credit note can be issued.
     */
    public function canBeIssued(): bool
    {
        return $this->status->canTransitionTo(CreditNoteStatus::Issued);
    }

    /**
     * Check if credit note can be applied.
     */
    public function canBeApplied(): bool
    {
        return $this->status->canTransitionTo(CreditNoteStatus::Applied);
    }

    // =========================================================================
    // Original Invoice Type Preservation
    // =========================================================================

    /**
     * Get the original invoice type.
     * Credit notes preserve the invoice_type of the original invoice.
     */
    public function getOriginalInvoiceType(): ?InvoiceType
    {
        $invoice = $this->invoice;

        return $invoice !== null ? $invoice->invoice_type : null;
    }

    /**
     * Get the original invoice type code (INV0-INV4).
     */
    public function getOriginalInvoiceTypeCode(): ?string
    {
        $type = $this->getOriginalInvoiceType();

        return $type !== null ? $type->code() : null;
    }

    /**
     * Get the original invoice type label.
     */
    public function getOriginalInvoiceTypeLabel(): ?string
    {
        $type = $this->getOriginalInvoiceType();

        return $type !== null ? $type->label() : null;
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
}
