<?php

namespace App\Models\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Pim\SellableSku;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * InvoiceLine Model
 *
 * Represents a single line item on an invoice. Invoice lines become IMMUTABLE
 * after the parent invoice has been issued (status != draft).
 *
 * line_total = (quantity * unit_price) + tax_amount
 *
 * @property int $id
 * @property string $invoice_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $line_total
 * @property int|null $sellable_sku_id
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class InvoiceLine extends Model
{
    use HasFactory;

    protected $table = 'invoice_lines';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sellable_sku_id',
        'metadata',
    ];

    protected $attributes = [
        'tax_rate' => 0,
        'tax_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sellable_sku_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Auto-calculate line_total before saving if not set
        static::saving(function (InvoiceLine $line): void {
            $line->line_total = $line->calculateLineTotal();
        });

        // Enforce immutability after invoice issuance
        static::updating(function (InvoiceLine $line): void {
            $invoice = $line->invoice;

            if ($invoice !== null && $invoice->status !== InvoiceStatus::Draft) {
                throw new InvalidArgumentException(
                    'Invoice lines cannot be modified after invoice is issued. Use credit notes for corrections.'
                );
            }
        });

        // Prevent deletion after invoice issuance
        static::deleting(function (InvoiceLine $line): void {
            $invoice = $line->invoice;

            if ($invoice !== null && $invoice->status !== InvoiceStatus::Draft) {
                throw new InvalidArgumentException(
                    'Invoice lines cannot be deleted after invoice is issued. Use credit notes for corrections.'
                );
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
     * @return BelongsTo<SellableSku, $this>
     */
    public function sellableSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class);
    }

    // =========================================================================
    // Calculation Methods
    // =========================================================================

    /**
     * Calculate the line total: (quantity * unit_price) + tax_amount
     */
    public function calculateLineTotal(): string
    {
        $subtotal = bcmul($this->quantity ?? '0', $this->unit_price ?? '0', 2);

        return bcadd($subtotal, $this->tax_amount ?? '0', 2);
    }

    /**
     * Calculate the subtotal (quantity * unit_price, before tax).
     */
    public function getSubtotal(): string
    {
        return bcmul($this->quantity ?? '0', $this->unit_price ?? '0', 2);
    }

    /**
     * Calculate tax amount based on tax rate and subtotal.
     * This can be used to auto-calculate tax_amount if only tax_rate is provided.
     */
    public function calculateTaxAmount(): string
    {
        $subtotal = $this->getSubtotal();
        $taxRate = bcdiv($this->tax_rate ?? '0', '100', 4);

        return bcmul($subtotal, $taxRate, 2);
    }

    /**
     * Recalculate and set tax_amount based on tax_rate.
     */
    public function recalculateTax(): self
    {
        $this->tax_amount = $this->calculateTaxAmount();
        $this->line_total = $this->calculateLineTotal();

        return $this;
    }

    // =========================================================================
    // State Methods
    // =========================================================================

    /**
     * Check if this line can be edited.
     */
    public function canBeEdited(): bool
    {
        $invoice = $this->invoice;

        return $invoice === null || $invoice->status === InvoiceStatus::Draft;
    }

    /**
     * Check if this line is linked to a sellable SKU.
     */
    public function hasSellableSku(): bool
    {
        return $this->sellable_sku_id !== null;
    }

    // =========================================================================
    // Display Methods
    // =========================================================================

    /**
     * Get the formatted unit price with currency from parent invoice.
     */
    public function getFormattedUnitPrice(): string
    {
        $invoice = $this->invoice;
        $currency = $invoice !== null ? $invoice->currency : 'EUR';

        return $currency.' '.number_format((float) $this->unit_price, 2);
    }

    /**
     * Get the formatted line total with currency from parent invoice.
     */
    public function getFormattedLineTotal(): string
    {
        $invoice = $this->invoice;
        $currency = $invoice !== null ? $invoice->currency : 'EUR';

        return $currency.' '.number_format((float) $this->line_total, 2);
    }

    /**
     * Get the formatted tax amount with currency from parent invoice.
     */
    public function getFormattedTaxAmount(): string
    {
        $invoice = $this->invoice;
        $currency = $invoice !== null ? $invoice->currency : 'EUR';

        return $currency.' '.number_format((float) $this->tax_amount, 2);
    }

    /**
     * Get the formatted subtotal with currency from parent invoice.
     */
    public function getFormattedSubtotal(): string
    {
        $invoice = $this->invoice;
        $currency = $invoice !== null ? $invoice->currency : 'EUR';

        return $currency.' '.number_format((float) $this->getSubtotal(), 2);
    }

    /**
     * Get the formatted quantity.
     */
    public function getFormattedQuantity(): string
    {
        return number_format((float) $this->quantity, 2);
    }

    /**
     * Get the formatted tax rate as percentage.
     */
    public function getFormattedTaxRate(): string
    {
        return number_format((float) $this->tax_rate, 2).'%';
    }

    /**
     * Get a metadata value by key.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set a metadata value by key.
     *
     * @param  mixed  $value
     */
    public function setMetadataValue(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;

        return $this;
    }

    // =========================================================================
    // Pricing Metadata Methods (US-E032)
    // =========================================================================

    /**
     * Get the pricing snapshot ID used for this line.
     *
     * The pricing snapshot ID links to Module S pricing data for audit trail.
     */
    public function getPricingSnapshotId(): ?string
    {
        return $this->getMetadataValue('pricing_snapshot_id');
    }

    /**
     * Check if this line has pricing metadata from Module S.
     */
    public function hasPricingMetadata(): bool
    {
        return $this->getPricingSnapshotId() !== null;
    }

    /**
     * Get the pricing metadata array.
     *
     * Contains details from Module S including:
     * - price_book_id: The Price Book that provided the price
     * - price_book_entry_id: The specific entry used
     * - offer_id: The Offer applied (if any)
     * - discount_applied: Amount of discount (if any)
     *
     * @return array<string, mixed>|null
     */
    public function getPricingMetadata(): ?array
    {
        return $this->getMetadataValue('pricing');
    }

    /**
     * Get the Price Book ID used for this line.
     */
    public function getPriceBookId(): ?string
    {
        $pricing = $this->getPricingMetadata();

        return $pricing['price_book_id'] ?? null;
    }

    /**
     * Get the Offer ID applied to this line.
     */
    public function getOfferId(): ?string
    {
        $pricing = $this->getPricingMetadata();

        return $pricing['offer_id'] ?? null;
    }

    /**
     * Get the tax jurisdiction (country code) for this line.
     */
    public function getTaxJurisdiction(): ?string
    {
        return $this->getMetadataValue('tax_jurisdiction');
    }

    /**
     * Get a formatted description of the pricing source.
     */
    public function getPricingSourceDescription(): string
    {
        $snapshotId = $this->getPricingSnapshotId();
        if ($snapshotId === null) {
            return 'Manual pricing';
        }

        $priceBookId = $this->getPriceBookId();
        $offerId = $this->getOfferId();

        if ($offerId !== null) {
            return "Offer pricing (Snapshot: {$snapshotId})";
        }

        if ($priceBookId !== null) {
            return "Price Book pricing (Snapshot: {$snapshotId})";
        }

        return "Module S pricing (Snapshot: {$snapshotId})";
    }
}
