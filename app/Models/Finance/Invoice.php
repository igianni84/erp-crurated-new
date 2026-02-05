<?php

namespace App\Models\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\ServiceFeeType;
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

    // =========================================================================
    // Redemption Fee Methods (INV2 - Shipping)
    // =========================================================================

    /**
     * Check if this invoice includes a redemption fee.
     *
     * Redemption fees apply to INV2 (shipping) invoices when a customer
     * redeems vouchers for wine delivery, as opposed to simply shipping
     * their own wine from custody.
     *
     * The fee amount comes from Module S pricing.
     */
    public function hasRedemptionFee(): bool
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return false;
        }

        $lines = $this->invoiceLines()->get();

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            if (isset($metadata['line_type']) && $metadata['line_type'] === 'redemption') {
                $lineAmount = bcmul($line->quantity, $line->unit_price, 2);
                if (bccomp($lineAmount, '0', 2) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the redemption fee amount for this invoice.
     *
     * Returns null if this invoice does not have a redemption fee.
     */
    public function getRedemptionFeeAmount(): ?string
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return null;
        }

        $lines = $this->invoiceLines()->get();

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            if (isset($metadata['line_type']) && $metadata['line_type'] === 'redemption') {
                return bcmul($line->quantity, $line->unit_price, 2);
            }
        }

        return null;
    }

    /**
     * Get the redemption fee invoice line.
     *
     * Returns null if this invoice does not have a redemption fee.
     */
    public function getRedemptionFeeLine(): ?InvoiceLine
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return null;
        }

        $lines = $this->invoiceLines()->get();

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            if (isset($metadata['line_type']) && $metadata['line_type'] === 'redemption') {
                return $line;
            }
        }

        return null;
    }

    /**
     * Check if this is a redemption shipment (vs shipping-only).
     *
     * A redemption shipment involves voucher redemption for wine delivery,
     * while shipping-only is when a customer ships their own wine from custody.
     *
     * This can be determined by:
     * 1. Presence of a redemption fee line
     * 2. shipment_type metadata in invoice lines
     */
    public function isRedemptionShipment(): bool
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return false;
        }

        // Check for redemption fee line
        if ($this->hasRedemptionFee()) {
            return true;
        }

        // Check for shipment_type metadata
        $lines = $this->invoiceLines()->get();
        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            if (isset($metadata['shipment_type']) && $metadata['shipment_type'] === 'redemption') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this is a shipping-only shipment (no redemption).
     *
     * Only applies to INV2 (shipping) invoices.
     */
    public function isShippingOnly(): bool
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return false;
        }

        return ! $this->isRedemptionShipment();
    }

    /**
     * Get the shipment type for display/reporting.
     *
     * Returns 'redemption', 'shipping_only', or null if not a shipping invoice.
     */
    public function getShipmentType(): ?string
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return null;
        }

        return $this->isRedemptionShipment() ? 'redemption' : 'shipping_only';
    }

    /**
     * Get the shipment type label for display.
     */
    public function getShipmentTypeLabel(): ?string
    {
        $type = $this->getShipmentType();

        return match ($type) {
            'redemption' => 'Redemption + Shipping',
            'shipping_only' => 'Shipping Only',
            default => null,
        };
    }

    // =========================================================================
    // Multi-Shipment Aggregation Methods (INV2)
    // =========================================================================

    /**
     * Check if this is a multi-shipment invoice (INV2 aggregating multiple shipments).
     *
     * Multi-shipment invoices have source_id as a JSON array of shipping order IDs.
     */
    public function isMultiShipmentInvoice(): bool
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return false;
        }

        if ($this->source_id === null) {
            return false;
        }

        // Try to decode source_id as JSON array
        $decoded = json_decode($this->source_id, true);

        return is_array($decoded) && count($decoded) > 1;
    }

    /**
     * Get all shipping order IDs for this invoice.
     *
     * For single-shipment invoices, returns array with the single source_id.
     * For multi-shipment invoices, returns the decoded JSON array.
     *
     * @return array<string>
     */
    public function getShippingOrderIds(): array
    {
        if ($this->invoice_type !== InvoiceType::ShippingRedemption) {
            return [];
        }

        if ($this->source_id === null) {
            return [];
        }

        // Try to decode as JSON array
        $decoded = json_decode($this->source_id, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Single shipping order ID
        return [$this->source_id];
    }

    /**
     * Get the count of shipments in this invoice.
     */
    public function getShipmentCount(): int
    {
        return count($this->getShippingOrderIds());
    }

    /**
     * Get invoice lines grouped by shipping order ID.
     *
     * For multi-shipment invoices, lines should have shipping_order_id in metadata.
     *
     * @return array<string, \Illuminate\Support\Collection<int, InvoiceLine>>
     */
    public function getLinesByShippingOrder(): array
    {
        if (! $this->isShippingInvoice()) {
            return [];
        }

        $lines = $this->invoiceLines()->get();
        $grouped = [];

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            $orderId = $metadata['shipping_order_id'] ?? $this->source_id ?? 'unknown';

            if (! isset($grouped[$orderId])) {
                $grouped[$orderId] = collect();
            }
            $grouped[$orderId]->push($line);
        }

        return $grouped;
    }

    /**
     * Get the subtotal for a specific shipping order.
     */
    public function getSubtotalForShippingOrder(string $shippingOrderId): string
    {
        $linesByOrder = $this->getLinesByShippingOrder();
        $lines = $linesByOrder[$shippingOrderId] ?? collect();

        $subtotal = '0.00';
        foreach ($lines as $line) {
            $lineSubtotal = bcmul($line->quantity, $line->unit_price, 2);
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
        }

        return $subtotal;
    }

    /**
     * Get the total (incl. tax) for a specific shipping order.
     */
    public function getTotalForShippingOrder(string $shippingOrderId): string
    {
        $linesByOrder = $this->getLinesByShippingOrder();
        $lines = $linesByOrder[$shippingOrderId] ?? collect();

        $total = '0.00';
        foreach ($lines as $line) {
            $total = bcadd($total, $line->line_total, 2);
        }

        return $total;
    }

    /**
     * Get a summary of all shipments in this invoice.
     *
     * Returns an array of shipment summaries for display purposes.
     *
     * @return array<string, array{shipping_order_id: string, line_count: int, subtotal: string, tax: string, total: string, lines: \Illuminate\Support\Collection<int, InvoiceLine>}>
     */
    public function getShipmentSummaries(): array
    {
        if (! $this->isShippingInvoice()) {
            return [];
        }

        $linesByOrder = $this->getLinesByShippingOrder();
        $summaries = [];

        foreach ($linesByOrder as $orderId => $lines) {
            $subtotal = '0.00';
            $tax = '0.00';
            $total = '0.00';

            foreach ($lines as $line) {
                $lineSubtotal = bcmul($line->quantity, $line->unit_price, 2);
                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                $tax = bcadd($tax, $line->tax_amount, 2);
                $total = bcadd($total, $line->line_total, 2);
            }

            $summaries[$orderId] = [
                'shipping_order_id' => $orderId,
                'line_count' => $lines->count(),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'lines' => $lines,
            ];
        }

        return $summaries;
    }

    /**
     * Get carrier info for a specific shipping order from line metadata.
     *
     * @return array{carrier_name: string|null, tracking_number: string|null}|null
     */
    public function getCarrierInfoForShippingOrder(string $shippingOrderId): ?array
    {
        $linesByOrder = $this->getLinesByShippingOrder();
        $lines = $linesByOrder[$shippingOrderId] ?? collect();

        if ($lines->isEmpty()) {
            return null;
        }

        // Get carrier info from first line's metadata
        $firstLine = $lines->first();
        $metadata = $firstLine->metadata ?? [];

        return [
            'carrier_name' => $metadata['carrier_name'] ?? null,
            'tracking_number' => $metadata['tracking_number'] ?? null,
        ];
    }

    /**
     * Check if any of the shipments in this invoice is cross-border.
     */
    public function hasAnyCrossBorderShipment(): bool
    {
        $linesByOrder = $this->getLinesByShippingOrder();

        foreach ($linesByOrder as $lines) {
            foreach ($lines as $line) {
                $metadata = $line->metadata ?? [];
                if (isset($metadata['is_cross_border']) && $metadata['is_cross_border'] === true) {
                    return true;
                }
            }
        }

        return false;
    }

    // =========================================================================
    // Storage Fee Methods (INV3)
    // =========================================================================

    /**
     * Check if this is a storage fee invoice (INV3).
     */
    public function isStorageFeeInvoice(): bool
    {
        return $this->invoice_type === InvoiceType::StorageFee;
    }

    /**
     * Check if this storage invoice has multiple locations.
     *
     * A storage invoice with location breakdown will have separate invoice lines
     * for each storage location, identified by location_id in line metadata.
     */
    public function hasLocationBreakdown(): bool
    {
        if ($this->invoice_type !== InvoiceType::StorageFee) {
            return false;
        }

        $lines = $this->invoiceLines()->get();
        $locationIds = [];

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            $lineType = $metadata['line_type'] ?? null;

            if ($lineType === 'storage_fee' && isset($metadata['location_id'])) {
                $locationIds[] = $metadata['location_id'];
            }
        }

        // Has location breakdown if there are 2+ unique location IDs
        return count(array_unique($locationIds)) > 1;
    }

    /**
     * Get the number of locations in this storage invoice.
     */
    public function getStorageLocationCount(): int
    {
        if ($this->invoice_type !== InvoiceType::StorageFee) {
            return 0;
        }

        $lines = $this->invoiceLines()->get();
        $locationIds = [];

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            $lineType = $metadata['line_type'] ?? null;

            if ($lineType === 'storage_fee' && isset($metadata['location_id'])) {
                $locationIds[] = $metadata['location_id'];
            }
        }

        $uniqueLocations = array_unique($locationIds);

        // If no location_id found but has storage lines, count as 1 location (aggregated)
        if (count($uniqueLocations) === 0 && $lines->isNotEmpty()) {
            return 1;
        }

        return count($uniqueLocations);
    }

    /**
     * Get invoice lines grouped by storage location.
     *
     * For storage invoices with location breakdown, lines are grouped by
     * location_id in their metadata.
     *
     * @return array<string, \Illuminate\Support\Collection<int, InvoiceLine>>
     */
    public function getLinesByStorageLocation(): array
    {
        if (! $this->isStorageFeeInvoice()) {
            return [];
        }

        $lines = $this->invoiceLines()->get();
        $grouped = [];

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            $locationId = $metadata['location_id'] ?? 'all_locations';
            $locationName = $metadata['location_name'] ?? 'All Locations';

            $key = $locationId;
            if (! isset($grouped[$key])) {
                $grouped[$key] = collect();
            }
            $grouped[$key]->push($line);
        }

        return $grouped;
    }

    /**
     * Get a summary of storage usage by location.
     *
     * Returns an array of location summaries for display purposes.
     *
     * @return array<string, array{
     *     location_id: string|null,
     *     location_name: string,
     *     bottle_count: int,
     *     bottle_days: int,
     *     unit_rate: string,
     *     subtotal: string,
     *     tax: string,
     *     total: string,
     *     rate_tier: string|null,
     *     lines: \Illuminate\Support\Collection<int, InvoiceLine>
     * }>
     */
    public function getStorageLocationSummaries(): array
    {
        if (! $this->isStorageFeeInvoice()) {
            return [];
        }

        $linesByLocation = $this->getLinesByStorageLocation();
        $summaries = [];

        foreach ($linesByLocation as $locationKey => $lines) {
            $subtotal = '0.00';
            $tax = '0.00';
            $total = '0.00';
            $bottleCount = 0;
            $bottleDays = 0;
            $unitRate = '0.0000';
            $rateTier = null;
            $locationName = 'All Locations';
            $locationId = null;

            foreach ($lines as $line) {
                $lineSubtotal = bcmul($line->quantity, $line->unit_price, 2);
                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                $tax = bcadd($tax, $line->tax_amount, 2);
                $total = bcadd($total, $line->line_total, 2);

                $metadata = $line->metadata ?? [];
                $bottleCount = max($bottleCount, (int) ($metadata['bottle_count'] ?? 0));
                $bottleDays += (int) ($metadata['bottle_days'] ?? (int) $line->quantity);
                $unitRate = $metadata['unit_rate'] ?? $line->unit_price;
                $rateTier = $metadata['rate_tier'] ?? null;
                $locationName = $metadata['location_name'] ?? 'All Locations';
                $locationId = $metadata['location_id'] ?? null;
            }

            $summaries[$locationKey] = [
                'location_id' => $locationId,
                'location_name' => $locationName,
                'bottle_count' => $bottleCount,
                'bottle_days' => $bottleDays,
                'unit_rate' => $unitRate,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'rate_tier' => $rateTier,
                'lines' => $lines,
            ];
        }

        return $summaries;
    }

    /**
     * Get total bottle-days for this storage invoice.
     */
    public function getTotalBottleDays(): int
    {
        if (! $this->isStorageFeeInvoice()) {
            return 0;
        }

        $lines = $this->invoiceLines()->get();
        $totalBottleDays = 0;

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            $bottleDays = $metadata['bottle_days'] ?? (int) $line->quantity;
            $totalBottleDays += (int) $bottleDays;
        }

        return $totalBottleDays;
    }

    /**
     * Get the storage billing period for this INV3 invoice.
     */
    public function getStorageBillingPeriod(): ?StorageBillingPeriod
    {
        if ($this->source_type !== 'storage_billing_period' || $this->source_id === null) {
            return null;
        }

        return StorageBillingPeriod::find($this->source_id);
    }

    /**
     * Get the billing period dates for display.
     *
     * @return array{start: string|null, end: string|null, label: string}|null
     */
    public function getStoragePeriodDates(): ?array
    {
        // Try to get from storage billing period
        $period = $this->getStorageBillingPeriod();
        if ($period !== null) {
            return [
                'start' => $period->period_start->format('Y-m-d'),
                'end' => $period->period_end->format('Y-m-d'),
                'label' => $period->period_start->format('M j').' - '.$period->period_end->format('M j, Y'),
            ];
        }

        // Try to get from first invoice line metadata
        $firstLine = $this->invoiceLines()->first();
        if ($firstLine !== null) {
            $metadata = $firstLine->metadata ?? [];
            $start = $metadata['period_start'] ?? null;
            $end = $metadata['period_end'] ?? null;

            if ($start !== null && $end !== null) {
                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => date('M j', strtotime($start)).' - '.date('M j, Y', strtotime($end)),
                ];
            }
        }

        return null;
    }

    // =========================================================================
    // Service Fee Methods (INV4 - Service Events)
    // =========================================================================

    /**
     * Check if this is a service events invoice (INV4).
     */
    public function isServiceEventsInvoice(): bool
    {
        return $this->invoice_type === InvoiceType::ServiceEvents;
    }

    /**
     * Get all service fee types present on this INV4 invoice.
     *
     * Returns unique ServiceFeeType enums from invoice line metadata.
     *
     * @return array<int, ServiceFeeType>
     */
    public function getServiceFeeTypes(): array
    {
        if (! $this->isServiceEventsInvoice()) {
            return [];
        }

        $lines = $this->invoiceLines()->get();
        $types = [];

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            if (isset($metadata['service_type'])) {
                $feeType = ServiceFeeType::tryFromString($metadata['service_type']);
                if ($feeType !== null && ! in_array($feeType, $types, true)) {
                    $types[] = $feeType;
                }
            }
        }

        return $types;
    }

    /**
     * Get all service type string values present on this INV4 invoice.
     *
     * @return array<int, string>
     */
    public function getServiceTypeValues(): array
    {
        if (! $this->isServiceEventsInvoice()) {
            return [];
        }

        $lines = $this->invoiceLines()->get();
        $types = [];

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            if (isset($metadata['service_type']) && ! in_array($metadata['service_type'], $types, true)) {
                $types[] = $metadata['service_type'];
            }
        }

        return $types;
    }

    /**
     * Check if this invoice has a specific service fee type.
     */
    public function hasServiceFeeType(ServiceFeeType $type): bool
    {
        return in_array($type, $this->getServiceFeeTypes(), true);
    }

    /**
     * Check if this invoice has any event-related service fees.
     *
     * Event-related includes: event_attendance, tasting_fee
     */
    public function hasEventRelatedFees(): bool
    {
        foreach ($this->getServiceFeeTypes() as $type) {
            if ($type->isEventRelated()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this invoice has any advisory/consultation fees.
     */
    public function hasAdvisoryFees(): bool
    {
        foreach ($this->getServiceFeeTypes() as $type) {
            if ($type->isAdvisory()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this invoice has tasting fees.
     */
    public function hasTastingFees(): bool
    {
        return $this->hasServiceFeeType(ServiceFeeType::TastingFee);
    }

    /**
     * Check if this invoice has consultation fees.
     */
    public function hasConsultationFees(): bool
    {
        return $this->hasServiceFeeType(ServiceFeeType::Consultation);
    }

    /**
     * Get the primary service fee type for this invoice.
     *
     * Returns the first service fee type found, or null.
     */
    public function getPrimaryServiceFeeType(): ?ServiceFeeType
    {
        $types = $this->getServiceFeeTypes();

        return $types[0] ?? null;
    }

    /**
     * Get a human-readable summary of service fee types.
     */
    public function getServiceFeeTypesSummary(): string
    {
        $types = $this->getServiceFeeTypes();

        if (empty($types)) {
            return 'Service Fees';
        }

        return implode(', ', array_map(fn (ServiceFeeType $t) => $t->label(), $types));
    }

    /**
     * Get invoice lines grouped by service fee type.
     *
     * @return array<string, \Illuminate\Support\Collection<int, InvoiceLine>>
     */
    public function getLinesByServiceFeeType(): array
    {
        if (! $this->isServiceEventsInvoice()) {
            return [];
        }

        $lines = $this->invoiceLines()->get();
        $grouped = [];

        foreach ($lines as $line) {
            $metadata = $line->metadata ?? [];
            $serviceType = $metadata['service_type'] ?? 'other_service';

            if (! isset($grouped[$serviceType])) {
                $grouped[$serviceType] = collect();
            }
            $grouped[$serviceType]->push($line);
        }

        return $grouped;
    }

    /**
     * Get a summary of amounts by service fee type.
     *
     * @return array<string, array{
     *     service_type: string,
     *     service_type_label: string,
     *     line_count: int,
     *     subtotal: string,
     *     tax: string,
     *     total: string,
     *     lines: \Illuminate\Support\Collection<int, InvoiceLine>
     * }>
     */
    public function getServiceFeeTypeSummaries(): array
    {
        if (! $this->isServiceEventsInvoice()) {
            return [];
        }

        $linesByType = $this->getLinesByServiceFeeType();
        $summaries = [];

        foreach ($linesByType as $typeValue => $lines) {
            $subtotal = '0.00';
            $tax = '0.00';
            $total = '0.00';

            foreach ($lines as $line) {
                $lineSubtotal = bcmul($line->quantity, $line->unit_price, 2);
                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                $tax = bcadd($tax, $line->tax_amount, 2);
                $total = bcadd($total, $line->line_total, 2);
            }

            $feeType = ServiceFeeType::tryFromString($typeValue);
            $label = $feeType !== null ? $feeType->label() : ucfirst(str_replace('_', ' ', $typeValue));

            $summaries[$typeValue] = [
                'service_type' => $typeValue,
                'service_type_label' => $label,
                'line_count' => $lines->count(),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'lines' => $lines,
            ];
        }

        return $summaries;
    }

    /**
     * Get the event booking ID for this INV4 invoice.
     */
    public function getEventBookingId(): ?string
    {
        if ($this->source_type !== 'event_booking' || $this->source_id === null) {
            return null;
        }

        return $this->source_id;
    }

    /**
     * Get event details from invoice line metadata.
     *
     * @return array{
     *     event_name: string|null,
     *     event_date: string|null,
     *     event_type: string|null,
     *     venue: string|null,
     *     attendee_count: int|null
     * }|null
     */
    public function getEventDetails(): ?array
    {
        if (! $this->isServiceEventsInvoice()) {
            return null;
        }

        $firstLine = $this->invoiceLines()->first();
        if ($firstLine === null) {
            return null;
        }

        $metadata = $firstLine->metadata ?? [];

        return [
            'event_name' => $metadata['event_name'] ?? null,
            'event_date' => $metadata['event_date'] ?? null,
            'event_type' => $metadata['event_type'] ?? null,
            'venue' => $metadata['venue'] ?? null,
            'attendee_count' => isset($metadata['attendee_count']) ? (int) $metadata['attendee_count'] : null,
        ];
    }

    /**
     * Check if this INV4 invoice has an event reference.
     *
     * Returns false for ad-hoc service invoices without event booking.
     */
    public function hasEventReference(): bool
    {
        return $this->source_type === 'event_booking' && $this->source_id !== null;
    }

    /**
     * Check if this INV4 invoice is an ad-hoc service invoice.
     *
     * Ad-hoc invoices don't have an event booking reference.
     */
    public function isAdHocServiceInvoice(): bool
    {
        if (! $this->isServiceEventsInvoice()) {
            return false;
        }

        return ! $this->hasEventReference();
    }
}
