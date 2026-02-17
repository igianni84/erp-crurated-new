<?php

namespace App\Models\Fulfillment;

use App\Enums\Fulfillment\PackagingPreference;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Inventory\Location;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * ShippingOrder Model
 *
 * Represents an explicit shipping authorization - what a customer is authorized to receive.
 * A Shipping Order is the administrative record that links customer entitlements (vouchers)
 * to a physical delivery intent.
 *
 * Key invariants:
 * - Status transitions must follow defined workflow
 * - Each voucher can only be in one active SO at a time
 * - Vouchers become locked when SO moves to planned
 *
 * @property string $id
 * @property string $uuid
 * @property string $customer_id
 * @property string|null $destination_address_id
 * @property string|null $destination_address
 * @property string|null $source_warehouse_id
 * @property ShippingOrderStatus $status
 * @property PackagingPreference $packaging_preference
 * @property string|null $shipping_method
 * @property string|null $carrier
 * @property string|null $incoterms
 * @property Carbon|null $requested_ship_date
 * @property string|null $special_instructions
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property ShippingOrderStatus|null $previous_status
 */
class ShippingOrder extends Model
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
    protected $table = 'shipping_orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'destination_address_id',
        'destination_address',
        'source_warehouse_id',
        'status',
        'packaging_preference',
        'shipping_method',
        'carrier',
        'incoterms',
        'requested_ship_date',
        'special_instructions',
        'created_by',
        'approved_by',
        'approved_at',
        'previous_status',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ShippingOrderStatus::Draft,
        'packaging_preference' => PackagingPreference::Loose,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShippingOrderStatus::class,
            'packaging_preference' => PackagingPreference::class,
            'requested_ship_date' => 'date',
            'approved_at' => 'datetime',
            'previous_status' => ShippingOrderStatus::class,
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Validate status transitions
        static::updating(function (ShippingOrder $order): void {
            if ($order->isDirty('status')) {
                $oldStatusRaw = $order->getOriginal('status');

                // Handle both string (from DB) and enum (from memory) values
                $oldStatus = $oldStatusRaw instanceof ShippingOrderStatus
                    ? $oldStatusRaw
                    : ShippingOrderStatus::from((string) $oldStatusRaw);

                $newStatus = $order->status;

                if (! $oldStatus->canTransitionTo($newStatus)) {
                    throw new InvalidArgumentException(
                        "Invalid status transition from {$oldStatus->value} to {$newStatus->value}"
                    );
                }

                // Store previous status when going to on_hold
                if ($newStatus === ShippingOrderStatus::OnHold) {
                    $order->previous_status = $oldStatus;
                }

                // Clear previous status when leaving on_hold
                if ($oldStatus === ShippingOrderStatus::OnHold && $newStatus !== ShippingOrderStatus::OnHold) {
                    $order->previous_status = null;
                }
            }
        });
    }

    /**
     * Get the customer this shipping order belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the source warehouse (location) for this shipping order.
     *
     * @return BelongsTo<Location, $this>
     */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_warehouse_id');
    }

    /**
     * Get the user who created this shipping order.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this shipping order.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the audit logs for this shipping order.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get the shipments for this shipping order.
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Get the shipping order lines for this shipping order.
     *
     * @return HasMany<ShippingOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ShippingOrderLine::class);
    }

    /**
     * Get the exceptions for this shipping order.
     *
     * @return HasMany<ShippingOrderException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ShippingOrderException::class);
    }

    /**
     * Get the shipping order audit logs for this shipping order.
     *
     * @return HasMany<ShippingOrderAuditLog, $this>
     */
    public function shippingOrderAuditLogs(): HasMany
    {
        return $this->hasMany(ShippingOrderAuditLog::class);
    }

    /**
     * Check if the shipping order is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === ShippingOrderStatus::Draft;
    }

    /**
     * Check if the shipping order is planned.
     */
    public function isPlanned(): bool
    {
        return $this->status === ShippingOrderStatus::Planned;
    }

    /**
     * Check if the shipping order is in picking.
     */
    public function isPicking(): bool
    {
        return $this->status === ShippingOrderStatus::Picking;
    }

    /**
     * Check if the shipping order is shipped.
     */
    public function isShipped(): bool
    {
        return $this->status === ShippingOrderStatus::Shipped;
    }

    /**
     * Check if the shipping order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === ShippingOrderStatus::Completed;
    }

    /**
     * Check if the shipping order is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === ShippingOrderStatus::Cancelled;
    }

    /**
     * Check if the shipping order is on hold.
     */
    public function isOnHold(): bool
    {
        return $this->status === ShippingOrderStatus::OnHold;
    }

    /**
     * Check if the shipping order is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if the shipping order is active (non-terminal).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if the shipping order can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status->allowsEditing();
    }

    /**
     * Check if the shipping order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status->allowsCancellation();
    }

    /**
     * Check if vouchers should be locked for this order.
     */
    public function requiresVoucherLock(): bool
    {
        return $this->status->requiresVoucherLock();
    }

    /**
     * Check if a transition to the given status is allowed.
     */
    public function canTransitionTo(ShippingOrderStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    /**
     * Get the allowed transitions from the current status.
     *
     * @return list<ShippingOrderStatus>
     */
    public function getAllowedTransitions(): array
    {
        return $this->status->allowedTransitions();
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
     * Get the packaging preference label for UI display.
     */
    public function getPackagingPreferenceLabel(): string
    {
        return $this->packaging_preference->label();
    }

    /**
     * Get the packaging preference description for UI display.
     */
    public function getPackagingPreferenceDescription(): string
    {
        return $this->packaging_preference->description();
    }
}
