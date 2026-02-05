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
 * - fx_rate_at_issuance
 * - due_date (modifiable only in draft status)
 *
 * @property string $id
 * @property string|null $invoice_number
 * @property InvoiceType $invoice_type
 * @property string $customer_id
 * @property string $currency
 * @property string|null $fx_rate_at_issuance
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $amount_paid
 * @property InvoiceStatus $status
 * @property string|null $source_type
 * @property string|null $source_id
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
 * @property-read bool $is_overdue
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> overdue()
 * @method static \Illuminate\Database\Eloquent\Builder<static> notOverdue()
 * @method static \Illuminate\Database\Eloquent\Builder<static> unpaidImmediate(?int $thresholdHours = null)
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
        'fx_rate_at_issuance',
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
            'fx_rate_at_issuance' => 'decimal:6',
            'issued_at' => 'datetime',
            'due_date' => 'date',
            'xero_synced_at' => 'datetime',
            // Note: source_id is a string to support both int IDs and UUIDs
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

            // After issuance, amounts, currency, FX rate, and due_date become immutable
            if ($invoice->getOriginal('status') !== InvoiceStatus::Draft->value) {
                $immutableAfterIssuance = ['subtotal', 'tax_amount', 'total_amount', 'currency', 'fx_rate_at_issuance', 'due_date'];

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

    /**
     * Get the subscription if this is an INV0 (membership service) invoice.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'source_id');
    }

    /**
     * Get the source subscription for INV0 invoices.
     * Returns null if this is not a subscription-based invoice.
     */
    public function getSourceSubscription(): ?Subscription
    {
        if ($this->source_type !== 'subscription' || $this->source_id === null) {
            return null;
        }

        return Subscription::find($this->source_id);
    }

    /**
     * Check if this invoice is linked to a subscription (INV0).
     */
    public function isSubscriptionInvoice(): bool
    {
        return $this->invoice_type === InvoiceType::MembershipService
            && $this->source_type === 'subscription'
            && $this->source_id !== null;
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
     * Check if due date can be modified.
     * Due date is only modifiable while invoice is in draft status.
     */
    public function canModifyDueDate(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if due date is required for this invoice type.
     */
    public function requiresDueDate(): bool
    {
        return $this->invoice_type->requiresDueDate();
    }

    /**
     * Get the default due date days for this invoice type.
     * Returns null if immediate payment is expected.
     */
    public function getDefaultDueDateDays(): ?int
    {
        return $this->invoice_type->defaultDueDateDays();
    }

    /**
     * Check if this invoice type expects immediate payment.
     */
    public function expectsImmediatePayment(): bool
    {
        return $this->invoice_type->defaultDueDateDays() === null;
    }

    /**
     * Check if this is an unpaid immediate invoice past the alert threshold.
     *
     * Returns true if:
     * - Invoice type expects immediate payment (INV1, INV2, INV4)
     * - Invoice status is 'issued' (not paid)
     * - Invoice was issued more than threshold hours ago
     *
     * @param  int|null  $thresholdHours  Hours since issuance (default: config value or 24)
     */
    public function isUnpaidPastThreshold(?int $thresholdHours = null): bool
    {
        // Must be immediate payment type
        if (! $this->expectsImmediatePayment()) {
            return false;
        }

        // Must be in issued status (not paid yet)
        if ($this->status !== InvoiceStatus::Issued) {
            return false;
        }

        // Must have issued_at
        if ($this->issued_at === null) {
            return false;
        }

        $thresholdHours = $thresholdHours ?? (int) config('finance.immediate_invoice_alert_hours', 24);
        $cutoffTime = now()->subHours($thresholdHours);

        return $this->issued_at->lte($cutoffTime);
    }

    /**
     * Get hours since this invoice was issued.
     * Returns null if not issued.
     */
    public function getHoursSinceIssuance(): ?int
    {
        if ($this->issued_at === null) {
            return null;
        }

        return (int) $this->issued_at->diffInHours(now());
    }

    /**
     * Check if invoice is overdue.
     * Invoice is overdue when: status = issued AND due_date < today
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

    /**
     * Get computed is_overdue attribute.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }

    /**
     * Get the number of days overdue.
     * Returns null if not overdue.
     */
    public function getDaysOverdue(): ?int
    {
        if (! $this->isOverdue()) {
            return null;
        }

        return (int) $this->due_date?->diffInDays(now());
    }

    // =========================================================================
    // Query Scopes
    // =========================================================================

    /**
     * Scope to get overdue invoices.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeOverdue(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay());
    }

    /**
     * Scope to get invoices that are not overdue.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeNotOverdue(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q): void {
            $q->where('status', '!=', InvoiceStatus::Issued)
                ->orWhereNull('due_date')
                ->orWhere('due_date', '>=', now()->startOfDay());
        });
    }

    /**
     * Scope to get unpaid immediate invoices (INV1, INV2, INV4) past threshold.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @param  int|null  $thresholdHours  Hours since issuance (default: config value or 24)
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeUnpaidImmediate(\Illuminate\Database\Eloquent\Builder $query, ?int $thresholdHours = null): \Illuminate\Database\Eloquent\Builder
    {
        $thresholdHours = $thresholdHours ?? (int) config('finance.immediate_invoice_alert_hours', 24);
        $cutoffTime = now()->subHours($thresholdHours);

        $immediateTypes = collect(InvoiceType::cases())
            ->filter(fn (InvoiceType $type): bool => ! $type->requiresDueDate())
            ->values()
            ->all();

        return $query
            ->where('status', InvoiceStatus::Issued)
            ->whereIn('invoice_type', $immediateTypes)
            ->where('issued_at', '<=', $cutoffTime);
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

    // =========================================================================
    // Currency Methods
    // =========================================================================

    /**
     * Get the currency symbol for display.
     */
    public function getCurrencySymbol(): string
    {
        return match ($this->currency) {
            'EUR' => '€',
            'GBP' => '£',
            'USD' => '$',
            'CHF' => 'CHF',
            'JPY' => '¥',
            default => $this->currency,
        };
    }

    /**
     * Get formatted amount with currency symbol.
     */
    public function formatAmount(string $amount): string
    {
        return $this->getCurrencySymbol().' '.number_format((float) $amount, 2);
    }

    /**
     * Check if the invoice is in the base currency (EUR).
     */
    public function isBaseCurrency(): bool
    {
        return $this->currency === 'EUR';
    }

    /**
     * Check if the invoice has an FX rate (foreign currency at issuance).
     */
    public function hasFxRate(): bool
    {
        return $this->fx_rate_at_issuance !== null;
    }

    /**
     * Get the FX rate description for display.
     */
    public function getFxRateDescription(): ?string
    {
        if ($this->fx_rate_at_issuance === null) {
            return null;
        }

        return "1 {$this->currency} = {$this->fx_rate_at_issuance} EUR";
    }

    /**
     * Calculate amount in base currency (EUR) using FX rate.
     * Returns null if no FX rate is available.
     */
    public function getAmountInBaseCurrency(string $amount): ?string
    {
        if ($this->isBaseCurrency()) {
            return $amount;
        }

        if ($this->fx_rate_at_issuance === null) {
            return null;
        }

        return bcmul($amount, $this->fx_rate_at_issuance, 2);
    }

    /**
     * Get total amount in base currency (EUR).
     */
    public function getTotalInBaseCurrency(): ?string
    {
        return $this->getAmountInBaseCurrency($this->total_amount);
    }

    /**
     * Get list of supported currencies.
     *
     * @return array<string, string>
     */
    public static function getSupportedCurrencies(): array
    {
        return [
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'USD' => 'USD - US Dollar',
            'CHF' => 'CHF - Swiss Franc',
        ];
    }

    // =========================================================================
    // Tax Breakdown Methods
    // =========================================================================

    /**
     * Get the tax breakdown for this invoice.
     *
     * Groups invoice lines by tax rate and returns breakdown showing
     * taxable amounts and tax amounts per rate.
     *
     * @return array{
     *     total_subtotal: string,
     *     total_tax: string,
     *     tax_breakdown: array<string, array{rate: string, taxable_amount: string, tax_amount: string, line_count: int, description: string}>,
     *     has_mixed_rates: bool,
     *     destination_country: string|null,
     *     is_cross_border: bool,
     *     duty_summary: array{has_duties: bool, duty_amount: string}|null
     * }
     */
    public function getTaxBreakdown(): array
    {
        $lines = $this->invoiceLines()->get();
        $taxBreakdown = [];
        $totalSubtotal = '0.00';
        $totalTax = '0.00';
        $dutyAmount = '0.00';
        $hasDuties = false;
        $destinationCountry = null;
        $originCountry = null;

        foreach ($lines as $line) {
            $lineSubtotal = bcmul($line->quantity, $line->unit_price, 2);
            $lineTax = $line->tax_amount;
            $taxRate = $line->tax_rate;

            $totalSubtotal = bcadd($totalSubtotal, $lineSubtotal, 2);
            $totalTax = bcadd($totalTax, $lineTax, 2);

            // Check for duty lines and extract cross-border info from metadata
            $metadata = $line->metadata ?? [];
            $lineType = $metadata['line_type'] ?? null;

            if ($lineType === 'duties') {
                $hasDuties = true;
                $dutyAmount = bcadd($dutyAmount, $lineSubtotal, 2);
            }

            // Extract country information
            if ($destinationCountry === null && isset($metadata['destination_country'])) {
                $destinationCountry = $metadata['destination_country'];
            }
            if ($originCountry === null && isset($metadata['origin_country'])) {
                $originCountry = $metadata['origin_country'];
            }

            // Group by tax rate
            $rateKey = $taxRate;
            if (! isset($taxBreakdown[$rateKey])) {
                $taxBreakdown[$rateKey] = [
                    'rate' => $taxRate,
                    'taxable_amount' => '0.00',
                    'tax_amount' => '0.00',
                    'line_count' => 0,
                    'description' => $this->getTaxRateDescription($taxRate),
                ];
            }

            $taxBreakdown[$rateKey]['taxable_amount'] = bcadd($taxBreakdown[$rateKey]['taxable_amount'], $lineSubtotal, 2);
            $taxBreakdown[$rateKey]['tax_amount'] = bcadd($taxBreakdown[$rateKey]['tax_amount'], $lineTax, 2);
            $taxBreakdown[$rateKey]['line_count']++;
        }

        // Sort by tax rate (highest first)
        uasort($taxBreakdown, fn (array $a, array $b): int => bccomp($b['rate'], $a['rate'], 2));

        $isCrossBorder = $originCountry !== null && $destinationCountry !== null && $originCountry !== $destinationCountry;

        return [
            'total_subtotal' => $totalSubtotal,
            'total_tax' => $totalTax,
            'tax_breakdown' => $taxBreakdown,
            'has_mixed_rates' => count($taxBreakdown) > 1,
            'destination_country' => $destinationCountry,
            'is_cross_border' => $isCrossBorder,
            'duty_summary' => $hasDuties ? ['has_duties' => true, 'duty_amount' => $dutyAmount] : null,
        ];
    }

    /**
     * Get description for a tax rate.
     */
    protected function getTaxRateDescription(string $taxRate): string
    {
        if (bccomp($taxRate, '0', 2) === 0) {
            return 'Zero-rated (0%)';
        }

        return "VAT at {$taxRate}%";
    }

    /**
     * Check if this invoice has mixed tax rates (multiple VAT rates applied).
     */
    public function hasMixedTaxRates(): bool
    {
        return $this->getTaxBreakdown()['has_mixed_rates'];
    }

    /**
     * Check if this invoice is for a cross-border shipment (INV2).
     */
    public function isCrossBorderShipment(): bool
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return false;
        }

        return $this->getTaxBreakdown()['is_cross_border'];
    }

    /**
     * Check if this invoice includes customs duties (INV2 cross-border).
     */
    public function hasDuties(): bool
    {
        $breakdown = $this->getTaxBreakdown();

        return $breakdown['duty_summary'] !== null && $breakdown['duty_summary']['has_duties'];
    }

    /**
     * Get the destination country from invoice line metadata.
     * Primarily for INV2 (shipping) invoices.
     */
    public function getDestinationCountry(): ?string
    {
        return $this->getTaxBreakdown()['destination_country'];
    }

    /**
     * Check if this is a shipping invoice (INV2).
     */
    public function isShippingInvoice(): bool
    {
        return $this->invoice_type === InvoiceType::ShippingRedemption;
    }
}
