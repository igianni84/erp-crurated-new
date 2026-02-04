<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Customer\Party;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PurchaseOrder Model
 *
 * Represents a contractual sourcing agreement with a supplier.
 * A PurchaseOrder MUST be linked to a ProcurementIntent (invariant enforced at DB level).
 *
 * @property string $id UUID primary key
 * @property string $procurement_intent_id FK to ProcurementIntent (required)
 * @property string $supplier_party_id FK to Party (supplier)
 * @property string $product_reference_type Morphic type (sellable_skus or liquid_products)
 * @property string $product_reference_id UUID of the referenced product
 * @property int $quantity Number of bottles or bottle-equivalents
 * @property string $unit_cost Unit cost as decimal string
 * @property string $currency Currency code (e.g., EUR, USD)
 * @property string|null $incoterms Incoterms for delivery
 * @property bool $ownership_transfer Whether ownership transfers on delivery
 * @property \Carbon\Carbon|null $expected_delivery_start Start of delivery window
 * @property \Carbon\Carbon|null $expected_delivery_end End of delivery window
 * @property string|null $destination_warehouse Warehouse for delivery
 * @property string|null $serialization_routing_note Special routing instructions
 * @property PurchaseOrderStatus $status Current lifecycle status
 * @property \Carbon\Carbon|null $confirmed_at Timestamp when PO was confirmed
 * @property int|null $confirmed_by User who confirmed the PO
 */
class PurchaseOrder extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'purchase_orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'procurement_intent_id',
        'supplier_party_id',
        'product_reference_type',
        'product_reference_id',
        'quantity',
        'unit_cost',
        'currency',
        'incoterms',
        'ownership_transfer',
        'expected_delivery_start',
        'expected_delivery_end',
        'destination_warehouse',
        'serialization_routing_note',
        'status',
        'confirmed_at',
        'confirmed_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'ownership_transfer' => 'boolean',
            'expected_delivery_start' => 'date',
            'expected_delivery_end' => 'date',
            'status' => PurchaseOrderStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (PurchaseOrder $order): void {
            // Enforce invariant: procurement_intent_id is required
            // This is also enforced at DB level, but we check here for early feedback
            if (empty($order->procurement_intent_id)) {
                throw new \InvalidArgumentException(
                    'A Purchase Order cannot exist without a Procurement Intent'
                );
            }
        });
    }

    /**
     * Get the procurement intent that this PO belongs to.
     *
     * @return BelongsTo<ProcurementIntent, $this>
     */
    public function procurementIntent(): BelongsTo
    {
        return $this->belongsTo(ProcurementIntent::class, 'procurement_intent_id');
    }

    /**
     * Get the supplier party for this PO.
     *
     * @return BelongsTo<Party, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_party_id');
    }

    /**
     * Get the referenced product (SellableSku or LiquidProduct).
     * Morphic relationship.
     *
     * @return MorphTo<Model, $this>
     */
    public function productReference(): MorphTo
    {
        return $this->morphTo('product_reference');
    }

    /**
     * Get the audit logs for this purchase order.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Get the inbounds linked to this PO.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Inbound, $this>
     */
    public function inbounds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inbound::class, 'purchase_order_id');
    }

    /**
     * Get the user who confirmed this PO.
     *
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'confirmed_by');
    }

    /**
     * Check if the PO is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    /**
     * Check if the PO has been sent.
     */
    public function isSent(): bool
    {
        return $this->status === PurchaseOrderStatus::Sent;
    }

    /**
     * Check if the PO is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === PurchaseOrderStatus::Confirmed;
    }

    /**
     * Check if the PO is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === PurchaseOrderStatus::Closed;
    }

    /**
     * Check if the PO is for a liquid product.
     */
    public function isForLiquidProduct(): bool
    {
        return $this->product_reference_type === 'liquid_products';
    }

    /**
     * Check if the PO is for a bottle SKU (SellableSku).
     */
    public function isForBottleSku(): bool
    {
        return $this->product_reference_type === 'sellable_skus';
    }

    /**
     * Check if ownership transfers with this PO.
     */
    public function hasOwnershipTransfer(): bool
    {
        return $this->ownership_transfer === true;
    }

    /**
     * Check if the delivery window has passed.
     */
    public function isDeliveryOverdue(): bool
    {
        if ($this->expected_delivery_end === null) {
            return false;
        }

        return $this->expected_delivery_end->isPast() && ! $this->isClosed();
    }

    /**
     * Get the total cost (quantity * unit_cost).
     */
    public function getTotalCost(): float
    {
        return (float) $this->quantity * (float) $this->unit_cost;
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get a display label for the product reference.
     */
    public function getProductLabel(): string
    {
        $product = $this->productReference;

        if (! $product) {
            return 'Unknown Product';
        }

        // Handle SellableSku
        if ($this->isForBottleSku() && $product instanceof \App\Models\Pim\SellableSku) {
            return $product->sku_code ?? 'Unknown SKU';
        }

        // Handle LiquidProduct
        if ($this->isForLiquidProduct() && $product instanceof \App\Models\Pim\LiquidProduct) {
            $wineVariant = $product->wineVariant;
            if ($wineVariant && $wineVariant->wineMaster) {
                return $wineVariant->wineMaster->name.' '.$wineVariant->vintage_year.' (Liquid)';
            }

            return 'Unknown Liquid Product';
        }

        return 'Unknown Product';
    }

    /**
     * Get the delivery window as a formatted string.
     */
    public function getDeliveryWindowLabel(): string
    {
        if ($this->expected_delivery_start === null && $this->expected_delivery_end === null) {
            return 'Not specified';
        }

        if ($this->expected_delivery_start !== null && $this->expected_delivery_end !== null) {
            return $this->expected_delivery_start->format('Y-m-d').' - '.$this->expected_delivery_end->format('Y-m-d');
        }

        if ($this->expected_delivery_start !== null) {
            return 'From '.$this->expected_delivery_start->format('Y-m-d');
        }

        return 'Until '.$this->expected_delivery_end->format('Y-m-d');
    }
}
