<?php

namespace App\Models\Fulfillment;

use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Inventory\InventoryCase;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ShippingOrderLine Model
 *
 * Represents a single line item in a Shipping Order, linking a voucher
 * to a specific allocation lineage. Each line represents exactly one bottle.
 *
 * Key invariants:
 * - 1 voucher = 1 line = 1 bottle
 * - allocation_id is IMMUTABLE after creation (copied from voucher)
 * - Late binding (bound_bottle_serial) is assigned after WMS pick confirmation
 * - Early binding (early_binding_serial) comes from Module D personalization
 *
 * @property string $id
 * @property string $shipping_order_id
 * @property string $voucher_id
 * @property string $allocation_id
 * @property string $status
 * @property string|null $bound_bottle_serial
 * @property string|null $bound_case_id
 * @property string|null $early_binding_serial
 * @property \Carbon\Carbon|null $binding_confirmed_at
 * @property int|null $binding_confirmed_by
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ShippingOrderLine extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * Status constants until ShippingOrderLineStatus enum is implemented (US-C007).
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_PICKED = 'picked';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shipping_order_lines';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shipping_order_id',
        'voucher_id',
        'allocation_id',
        'status',
        'bound_bottle_serial',
        'bound_case_id',
        'early_binding_serial',
        'binding_confirmed_at',
        'binding_confirmed_by',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'binding_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Prevent modification of allocation_id after creation (IMMUTABLE)
        static::updating(function (ShippingOrderLine $line): void {
            if ($line->isDirty('allocation_id')) {
                throw new \InvalidArgumentException(
                    'allocation_id is immutable after creation. Cross-allocation substitution is not allowed.'
                );
            }
        });
    }

    /**
     * Get the shipping order this line belongs to.
     *
     * @return BelongsTo<ShippingOrder, $this>
     */
    public function shippingOrder(): BelongsTo
    {
        return $this->belongsTo(ShippingOrder::class);
    }

    /**
     * Get the voucher for this line.
     *
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Get the allocation (lineage) for this line.
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Get the bound case (if bound to a case).
     *
     * @return BelongsTo<InventoryCase, $this>
     */
    public function boundCase(): BelongsTo
    {
        return $this->belongsTo(InventoryCase::class, 'bound_case_id');
    }

    /**
     * Get the user who confirmed the binding.
     *
     * @return BelongsTo<User, $this>
     */
    public function bindingConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'binding_confirmed_by');
    }

    /**
     * Get the user who created this line.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the audit logs for this shipping order line.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if this line is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this line is validated.
     */
    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    /**
     * Check if this line is picked.
     */
    public function isPicked(): bool
    {
        return $this->status === self::STATUS_PICKED;
    }

    /**
     * Check if this line is shipped.
     */
    public function isShipped(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    /**
     * Check if this line is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if this line has been bound to a bottle.
     */
    public function isBound(): bool
    {
        return $this->bound_bottle_serial !== null;
    }

    /**
     * Check if this line has early binding (from Module D personalization).
     */
    public function hasEarlyBinding(): bool
    {
        return $this->early_binding_serial !== null;
    }

    /**
     * Check if the binding has been confirmed.
     */
    public function isBindingConfirmed(): bool
    {
        return $this->binding_confirmed_at !== null;
    }

    /**
     * Get the effective serial (early binding takes precedence).
     */
    public function getEffectiveSerial(): ?string
    {
        return $this->early_binding_serial ?? $this->bound_bottle_serial;
    }
}
