<?php

namespace App\Models\Fulfillment;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Models\Inventory\Location;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shipment Model
 *
 * Represents the physical shipping event - the "point of no return" where
 * goods leave the warehouse. Once a shipment is confirmed, the bottle serials
 * are immutable.
 *
 * Key invariants:
 * - Every shipment MUST have a shipping_order_id
 * - shipped_bottle_serials is immutable after confirmation
 * - delivered and failed are terminal states
 *
 * @property string $id
 * @property string $shipping_order_id
 * @property string $carrier
 * @property string|null $tracking_number
 * @property \Carbon\Carbon|null $shipped_at
 * @property \Carbon\Carbon|null $delivered_at
 * @property ShipmentStatus $status
 * @property array<int, string> $shipped_bottle_serials
 * @property string $origin_warehouse_id
 * @property string $destination_address
 * @property string|null $weight
 * @property string|null $notes
 */
class Shipment extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shipments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shipping_order_id',
        'carrier',
        'tracking_number',
        'shipped_at',
        'delivered_at',
        'status',
        'shipped_bottle_serials',
        'origin_warehouse_id',
        'destination_address',
        'weight',
        'notes',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ShipmentStatus::Preparing,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'shipped_bottle_serials' => 'array',
            'weight' => 'decimal:2',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Prevent modification of shipped_bottle_serials after confirmation
        static::updating(function (Shipment $shipment): void {
            // If already confirmed (shipped) and trying to change serials, block it
            $originalStatusRaw = $shipment->getOriginal('status');
            $originalStatus = $originalStatusRaw instanceof ShipmentStatus
                ? $originalStatusRaw
                : ShipmentStatus::from((string) $originalStatusRaw);

            // Only check immutability if we were already shipped
            if ($originalStatus !== ShipmentStatus::Preparing && $shipment->isDirty('shipped_bottle_serials')) {
                throw new \InvalidArgumentException(
                    'shipped_bottle_serials is immutable after shipment confirmation'
                );
            }

            // Validate status transitions
            if ($shipment->isDirty('status')) {
                $newStatus = $shipment->status;

                if (! $originalStatus->canTransitionTo($newStatus)) {
                    throw new \InvalidArgumentException(
                        "Invalid status transition from {$originalStatus->value} to {$newStatus->value}"
                    );
                }
            }
        });
    }

    /**
     * Get the shipping order this shipment belongs to.
     *
     * @return BelongsTo<ShippingOrder, $this>
     */
    public function shippingOrder(): BelongsTo
    {
        return $this->belongsTo(ShippingOrder::class);
    }

    /**
     * Get the origin warehouse (location) for this shipment.
     *
     * @return BelongsTo<Location, $this>
     */
    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_warehouse_id');
    }

    /**
     * Get the audit logs for this shipment.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the shipment is in preparing status.
     */
    public function isPreparing(): bool
    {
        return $this->status === ShipmentStatus::Preparing;
    }

    /**
     * Check if the shipment has been shipped.
     */
    public function isShipped(): bool
    {
        return $this->status === ShipmentStatus::Shipped;
    }

    /**
     * Check if the shipment is in transit.
     */
    public function isInTransit(): bool
    {
        return $this->status === ShipmentStatus::InTransit;
    }

    /**
     * Check if the shipment is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === ShipmentStatus::Delivered;
    }

    /**
     * Check if the shipment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === ShipmentStatus::Failed;
    }

    /**
     * Check if the shipment is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if the shipment is active (non-terminal).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if a transition to the given status is allowed.
     */
    public function canTransitionTo(ShipmentStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    /**
     * Get the allowed transitions from the current status.
     *
     * @return list<ShipmentStatus>
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
     * Get the count of shipped bottles.
     */
    public function getBottleCount(): int
    {
        return count($this->shipped_bottle_serials ?? []);
    }

    /**
     * Check if a specific bottle serial is in this shipment.
     */
    public function hasBottleSerial(string $serial): bool
    {
        return in_array($serial, $this->shipped_bottle_serials ?? [], true);
    }
}
