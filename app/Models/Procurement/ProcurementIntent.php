<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\SourcingModel;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\SellableSku;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * ProcurementIntent Model
 *
 * Central pre-sourcing entity representing a decision to source wine.
 * Links to either a BottleSku (SellableSku) or a LiquidProduct.
 *
 * @property string $id UUID primary key
 * @property string $product_reference_type Morphic type (sellable_skus or liquid_products)
 * @property string $product_reference_id UUID of the referenced product
 * @property int $quantity Number of bottles or bottle-equivalents
 * @property ProcurementTriggerType $trigger_type What triggered this intent
 * @property SourcingModel $sourcing_model How the product will be sourced
 * @property string|null $preferred_inbound_location Preferred warehouse for delivery
 * @property string|null $rationale Operational notes
 * @property ProcurementIntentStatus $status Current lifecycle status
 * @property Carbon|null $approved_at When the intent was approved
 * @property int|null $approved_by User ID who approved the intent
 * @property string|null $source_allocation_id FK to allocation that triggered this intent (voucher-driven)
 * @property string|null $source_voucher_id FK to voucher that triggered this intent (voucher-driven)
 * @property bool $needs_ops_review Flag indicating intent needs Ops attention
 */
class ProcurementIntent extends Model
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
    protected $table = 'procurement_intents';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_reference_type',
        'product_reference_id',
        'quantity',
        'trigger_type',
        'sourcing_model',
        'preferred_inbound_location',
        'rationale',
        'source_allocation_id',
        'source_voucher_id',
        'needs_ops_review',
        'status',
        'approved_at',
        'approved_by',
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
            'trigger_type' => ProcurementTriggerType::class,
            'sourcing_model' => SourcingModel::class,
            'status' => ProcurementIntentStatus::class,
            'approved_at' => 'datetime',
            'needs_ops_review' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (ProcurementIntent $intent): void {
            // Enforce invariant: quantity > 0 always
            if ($intent->quantity <= 0) {
                throw new InvalidArgumentException(
                    'Quantity must be greater than 0'
                );
            }
        });
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
     * Get the user who approved this intent.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the source allocation that triggered this intent (for voucher-driven intents).
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function sourceAllocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class, 'source_allocation_id');
    }

    /**
     * Get the source voucher that triggered this intent (for voucher-driven intents).
     *
     * @return BelongsTo<Voucher, $this>
     */
    public function sourceVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'source_voucher_id');
    }

    /**
     * Get the audit logs for this procurement intent.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get the purchase orders linked to this intent.
     *
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'procurement_intent_id');
    }

    /**
     * Get the bottling instructions linked to this intent.
     *
     * @return HasMany<BottlingInstruction, $this>
     */
    public function bottlingInstructions(): HasMany
    {
        return $this->hasMany(BottlingInstruction::class, 'procurement_intent_id');
    }

    /**
     * Get the inbounds linked to this intent.
     *
     * @return HasMany<Inbound, $this>
     */
    public function inbounds(): HasMany
    {
        return $this->hasMany(Inbound::class, 'procurement_intent_id');
    }

    /**
     * Check if the intent is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === ProcurementIntentStatus::Draft;
    }

    /**
     * Check if the intent is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === ProcurementIntentStatus::Approved;
    }

    /**
     * Check if the intent is executed.
     */
    public function isExecuted(): bool
    {
        return $this->status === ProcurementIntentStatus::Executed;
    }

    /**
     * Check if the intent is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === ProcurementIntentStatus::Closed;
    }

    /**
     * Check if the intent is for a liquid product.
     */
    public function isForLiquidProduct(): bool
    {
        return $this->product_reference_type === 'liquid_products';
    }

    /**
     * Check if this intent was auto-created from a voucher sale.
     */
    public function isVoucherDriven(): bool
    {
        return $this->trigger_type === ProcurementTriggerType::VoucherDriven;
    }

    /**
     * Check if this intent has source context (allocation/voucher linked).
     */
    public function hasSourceContext(): bool
    {
        return $this->source_allocation_id !== null || $this->source_voucher_id !== null;
    }

    /**
     * Check if this intent needs Ops review.
     */
    public function needsOpsReview(): bool
    {
        return $this->needs_ops_review;
    }

    /**
     * Mark this intent as reviewed by Ops.
     */
    public function markAsReviewed(): void
    {
        $this->needs_ops_review = false;
        $this->save();
    }

    /**
     * Check if the intent is for a bottle SKU (SellableSku).
     */
    public function isForBottleSku(): bool
    {
        return $this->product_reference_type === 'sellable_skus';
    }

    /**
     * Check if linked objects can be created for this intent.
     */
    public function canCreateLinkedObjects(): bool
    {
        return $this->status->allowsLinkedObjectCreation();
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
        if ($this->isForBottleSku() && $product instanceof SellableSku) {
            return $product->sku_code ?? 'Unknown SKU';
        }

        // Handle LiquidProduct
        if ($this->isForLiquidProduct() && $product instanceof LiquidProduct) {
            $wineVariant = $product->wineVariant;
            if ($wineVariant && $wineVariant->wineMaster) {
                return $wineVariant->wineMaster->name.' '.$wineVariant->vintage_year.' (Liquid)';
            }

            return 'Unknown Liquid Product';
        }

        return 'Unknown Product';
    }
}
