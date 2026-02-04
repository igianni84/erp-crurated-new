<?php

namespace App\Models\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * Invoice Model
 *
 * The base model for ERP invoices. Invoice type (INV0-INV4) is IMMUTABLE
 * after creation and cannot be changed under any circumstances.
 *
 * After issuance, the following fields become immutable:
 * - invoice_lines (managed by InvoiceLine model)
 * - subtotal, tax_amount, total_amount
 * - currency
 *
 * @property string $id
 * @property string|null $invoice_number
 * @property InvoiceType $invoice_type
 * @property string $customer_id
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $amount_paid
 * @property InvoiceStatus $status
 * @property string|null $source_type
 * @property int|null $source_id
 * @property \Carbon\Carbon|null $issued_at
 * @property \Carbon\Carbon|null $due_date
 * @property string|null $notes
 * @property string|null $xero_invoice_id
 * @property \Carbon\Carbon|null $xero_synced_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Invoice extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'customer_id',
        'currency',
        'subtotal',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'status',
        'source_type',
        'source_id',
        'issued_at',
        'due_date',
        'notes',
        'xero_invoice_id',
        'xero_synced_at',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
        'amount_paid' => 0,
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
            'invoice_type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_date' => 'date',
            'xero_synced_at' => 'datetime',
            'source_id' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Enforce invoice_type immutability - can NEVER be changed after creation
        static::updating(function (Invoice $invoice): void {
            if ($invoice->isDirty('invoice_type')) {
                throw new InvalidArgumentException(
                    'invoice_type cannot be modified after creation. Invoice type is immutable.'
                );
            }

            // After issuance, amounts and currency become immutable
            if ($invoice->getOriginal('status') !== InvoiceStatus::Draft->value) {
                $immutableAfterIssuance = ['subtotal', 'tax_amount', 'total_amount', 'currency'];

                foreach ($immutableAfterIssuance as $field) {
                    if ($invoice->isDirty($field)) {
                        throw new InvalidArgumentException(
                            "'{$field}' cannot be modified after invoice is issued. Use credit notes for corrections."
                        );
                    }
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
     * @return HasMany<InvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Alias for invoiceLines for convenience.
     *
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->invoiceLines();
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Alias for invoicePayments for convenience.
     *
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->invoicePayments();
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
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
     * Check if invoice is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * Check if invoice has been issued.
     */
    public function isIssued(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    /**
     * Check if invoice is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->status === InvoiceStatus::PartiallyPaid;
    }

    /**
     * Check if invoice has been credited.
     */
    public function isCredited(): bool
    {
        return $this->status === InvoiceStatus::Credited;
    }

    /**
     * Check if invoice has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::Cancelled;
    }

    /**
     * Check if invoice can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status->allowsEditing();
    }

    /**
     * Check if invoice can receive payments.
     */
    public function canReceivePayment(): bool
    {
        return $this->status->allowsPayment();
    }

    /**
     * Check if credit notes can be created for this invoice.
     */
    public function canHaveCreditNote(): bool
    {
        return $this->status->allowsCreditNote();
    }

    /**
     * Check if invoice can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status->allowsCancellation();
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status !== InvoiceStatus::Issued) {
            return false;
        }

        if ($this->due_date === null) {
            return false;
        }

        return $this->due_date->isPast();
    }

    // =========================================================================
    // Computed Properties
    // =========================================================================

    /**
     * Get the outstanding amount (total - paid).
     */
    public function getOutstandingAmount(): string
    {
        return bcsub($this->total_amount, $this->amount_paid, 2);
    }

    /**
     * Check if invoice is fully paid based on amounts.
     */
    public function isFullyPaid(): bool
    {
        return bccomp($this->amount_paid, $this->total_amount, 2) >= 0;
    }

    /**
     * Check if invoice has any payments applied.
     */
    public function hasPayments(): bool
    {
        return bccomp($this->amount_paid, '0', 2) > 0;
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
     * Get the invoice type label for display.
     */
    public function getTypeLabel(): string
    {
        return $this->invoice_type->label();
    }

    /**
     * Get the invoice type code (INV0-INV4).
     */
    public function getTypeCode(): string
    {
        return $this->invoice_type->code();
    }

    /**
     * Get the invoice type color for display.
     */
    public function getTypeColor(): string
    {
        return $this->invoice_type->color();
    }

    /**
     * Get the invoice type icon for display.
     */
    public function getTypeIcon(): string
    {
        return $this->invoice_type->icon();
    }

    /**
     * Get formatted total amount with currency.
     */
    public function getFormattedTotal(): string
    {
        return $this->currency.' '.number_format((float) $this->total_amount, 2);
    }

    /**
     * Get formatted outstanding amount with currency.
     */
    public function getFormattedOutstanding(): string
    {
        return $this->currency.' '.number_format((float) $this->getOutstandingAmount(), 2);
    }
}
