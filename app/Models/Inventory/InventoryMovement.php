<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Models\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * InventoryMovement Model
 *
 * Represents an immutable record of physical inventory events.
 * Movements are append-only: insert only, no update, no delete.
 * This ensures complete audit trail for all inventory changes.
 *
 * @property string $id
 * @property MovementType $movement_type
 * @property MovementTrigger $trigger
 * @property string|null $source_location_id
 * @property string|null $destination_location_id
 * @property bool $custody_changed
 * @property string|null $reason
 * @property string|null $wms_event_id
 * @property \Carbon\Carbon $executed_at
 * @property int|null $executed_by
 */
class InventoryMovement extends Model
{
    use HasFactory;
    use HasUuid;

    // NO SoftDeletes trait - movements are immutable and never deleted

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inventory_movements';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'movement_type',
        'trigger',
        'source_location_id',
        'destination_location_id',
        'custody_changed',
        'reason',
        'wms_event_id',
        'executed_at',
        'executed_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'trigger' => MovementTrigger::class,
            'custody_changed' => 'boolean',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     * Enforces immutability: prevents updates and deletes.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Prevent any updates to existing records
        static::updating(function ($model): void {
            throw new InvalidArgumentException(
                'InventoryMovement records are immutable and cannot be updated. Create a new compensating movement instead.'
            );
        });

        // Prevent deletion of records
        static::deleting(function ($model): void {
            throw new InvalidArgumentException(
                'InventoryMovement records are immutable and cannot be deleted. Movements form an append-only ledger.'
            );
        });
    }

    /**
     * Get the source location for this movement.
     *
     * @return BelongsTo<Location, $this>
     */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    /**
     * Get the destination location for this movement.
     *
     * @return BelongsTo<Location, $this>
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    /**
     * Get the user who executed this movement.
     *
     * @return BelongsTo<User, $this>
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * Get the movement items associated with this movement.
     * MovementItem model will be created in US-B007.
     *
     * @return HasMany<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function movementItems(): HasMany
    {
        // MovementItem class will be created in US-B007
        /** @phpstan-ignore-next-line */
        return $this->hasMany(MovementItem::class, 'inventory_movement_id');
    }

    /**
     * Check if this movement was triggered by WMS.
     */
    public function isWmsTriggered(): bool
    {
        return $this->trigger === MovementTrigger::WmsEvent;
    }

    /**
     * Check if this movement was triggered by an operator.
     */
    public function isOperatorTriggered(): bool
    {
        return $this->trigger === MovementTrigger::ErpOperator;
    }

    /**
     * Check if this movement was automatic.
     */
    public function isAutomatic(): bool
    {
        return $this->trigger === MovementTrigger::SystemAutomatic;
    }

    /**
     * Check if this is a transfer movement.
     */
    public function isTransfer(): bool
    {
        return $this->movement_type === MovementType::InternalTransfer;
    }

    /**
     * Check if this is a consumption movement.
     */
    public function isConsumption(): bool
    {
        return $this->movement_type === MovementType::EventConsumption;
    }

    /**
     * Check if custody changed in this movement.
     */
    public function hasCustodyChange(): bool
    {
        return $this->custody_changed;
    }

    /**
     * Get the count of items in this movement.
     */
    public function getItemsCountAttribute(): int
    {
        return $this->movementItems()->count();
    }
}
