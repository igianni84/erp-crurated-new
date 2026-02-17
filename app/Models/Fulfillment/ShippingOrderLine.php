<?php

namespace App\Models\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use App\Models\Inventory\InventoryCase;
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
 * @property ShippingOrderLineStatus $status
 * @property string|null $bound_bottle_serial
 * @property string|null $bound_case_id
 * @property string|null $early_binding_serial
 * @property Carbon|null $binding_confirmed_at
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
        'status' => ShippingOrderLineStatus::Pending,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShippingOrderLineStatus::class,
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
                throw new InvalidArgumentException(
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
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Check if this line is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ShippingOrderLineStatus::Pending;
    }

    /**
     * Check if this line is validated.
     */
    public function isValidated(): bool
    {
        return $this->status === ShippingOrderLineStatus::Validated;
    }

    /**
     * Check if this line is picked.
     */
    public function isPicked(): bool
    {
        return $this->status === ShippingOrderLineStatus::Picked;
    }

    /**
     * Check if this line is shipped.
     */
    public function isShipped(): bool
    {
        return $this->status === ShippingOrderLineStatus::Shipped;
    }

    /**
     * Check if this line is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === ShippingOrderLineStatus::Cancelled;
    }

    /**
     * Check if this line is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if this line is active (non-terminal).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if a transition to the given status is allowed.
     */
    public function canTransitionTo(ShippingOrderLineStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    /**
     * Get the allowed transitions from the current status.
     *
     * @return list<ShippingOrderLineStatus>
     */
    public function getAllowedTransitions(): array
    {
        return $this->status->allowedTransitions();
    }

    /**
     * Check if binding can be performed on this line.
     */
    public function canBind(): bool
    {
        return $this->status->allowsBinding();
    }

    /**
     * Check if this line can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status->allowsCancellation();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
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
