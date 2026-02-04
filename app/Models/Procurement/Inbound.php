<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inbound Model
 *
 * Represents a physical fact record of goods arrival.
 * IMPORTANT: Inbound does NOT imply ownership - ownership_flag must be explicitly set.
 *
 * @property string $id UUID primary key
 * @property string|null $procurement_intent_id FK to ProcurementIntent (optional)
 * @property string|null $purchase_order_id FK to PurchaseOrder (optional)
 * @property string $warehouse Physical warehouse location
 * @property string $product_reference_type Morphic type (sellable_skus or liquid_products)
 * @property string $product_reference_id UUID of the referenced product
 * @property int $quantity Number of bottles
 * @property InboundPackaging $packaging Packaging type (cases, loose, mixed)
 * @property OwnershipFlag $ownership_flag Explicit ownership status
 * @property \Carbon\Carbon $received_date When goods were received
 * @property string|null $condition_notes Notes on condition (damage, etc.)
 * @property bool $serialization_required Whether serialization is required
 * @property string|null $serialization_location_authorized Authorized location for serialization
 * @property string|null $serialization_routing_rule Special routing rules
 * @property InboundStatus $status Current lifecycle status
 * @property bool $handed_to_module_b Whether handed off to Module B
 * @property \Carbon\Carbon|null $handed_to_module_b_at When handed off to Module B
 */
class Inbound extends Model
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
    protected $table = 'inbounds';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'procurement_intent_id',
        'purchase_order_id',
        'warehouse',
        'product_reference_type',
        'product_reference_id',
        'quantity',
        'packaging',
        'ownership_flag',
        'received_date',
        'condition_notes',
        'serialization_required',
        'serialization_location_authorized',
        'serialization_routing_rule',
        'status',
        'handed_to_module_b',
        'handed_to_module_b_at',
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
            'packaging' => InboundPackaging::class,
            'ownership_flag' => OwnershipFlag::class,
            'received_date' => 'date',
            'serialization_required' => 'boolean',
            'status' => InboundStatus::class,
            'handed_to_module_b' => 'boolean',
            'handed_to_module_b_at' => 'datetime',
        ];
    }

    /**
     * Get the procurement intent that this inbound belongs to (optional).
     *
     * @return BelongsTo<ProcurementIntent, $this>
     */
    public function procurementIntent(): BelongsTo
    {
        return $this->belongsTo(ProcurementIntent::class, 'procurement_intent_id');
    }

    /**
     * Get the purchase order that this inbound belongs to (optional).
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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
     * Get the audit logs for this inbound.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if this inbound is linked to a procurement intent.
     */
    public function isLinked(): bool
    {
        return $this->procurement_intent_id !== null;
    }

    /**
     * Check if this inbound is unlinked (no procurement intent).
     */
    public function isUnlinked(): bool
    {
        return $this->procurement_intent_id === null;
    }

    /**
     * Check if the inbound is in recorded status.
     */
    public function isRecorded(): bool
    {
        return $this->status === InboundStatus::Recorded;
    }

    /**
     * Check if the inbound is in routed status.
     */
    public function isRouted(): bool
    {
        return $this->status === InboundStatus::Routed;
    }

    /**
     * Check if the inbound is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === InboundStatus::Completed;
    }

    /**
     * Check if ownership is clarified (not pending).
     */
    public function hasOwnershipClarity(): bool
    {
        return $this->ownership_flag->isClarified();
    }

    /**
     * Check if ownership is pending.
     */
    public function hasOwnershipPending(): bool
    {
        return $this->ownership_flag === OwnershipFlag::Pending;
    }

    /**
     * Check if we own this product.
     */
    public function isOwned(): bool
    {
        return $this->ownership_flag->isOwned();
    }

    /**
     * Check if we are holding this product in custody (but don't own it).
     */
    public function isInCustody(): bool
    {
        return $this->ownership_flag === OwnershipFlag::InCustody;
    }

    /**
     * Check if serialization routing is valid.
     */
    public function hasValidSerializationRouting(): bool
    {
        if (! $this->serialization_required) {
            return true;
        }

        return $this->serialization_location_authorized !== null;
    }

    /**
     * Check if hand-off to Module B is allowed.
     */
    public function canHandOffToModuleB(): bool
    {
        return $this->status->allowsHandOff()
            && $this->hasOwnershipClarity()
            && ! $this->handed_to_module_b;
    }

    /**
     * Check if the inbound has been handed to Module B.
     */
    public function wasHandedToModuleB(): bool
    {
        return $this->handed_to_module_b === true;
    }

    /**
     * Check if the inbound is for a liquid product.
     */
    public function isForLiquidProduct(): bool
    {
        return $this->product_reference_type === 'liquid_products';
    }

    /**
     * Check if the inbound is for a bottle SKU (SellableSku).
     */
    public function isForBottleSku(): bool
    {
        return $this->product_reference_type === 'sellable_skus';
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
     * Get the ownership flag color for UI display.
     */
    public function getOwnershipColor(): string
    {
        return $this->ownership_flag->color();
    }

    /**
     * Get the ownership flag label for UI display.
     */
    public function getOwnershipLabel(): string
    {
        return $this->ownership_flag->label();
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
}
