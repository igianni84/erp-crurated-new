<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Models\User;
use App\Traits\HasUuid;
use Carbon\Carbon;
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
 * IMMUTABILITY RULES (US-B051):
 * - Model has no update() or delete() capability (boot guards throw exceptions)
 * - DB table has no soft_deletes column
 * - Any correction requires creating a new compensating movement
 * - Original movements are NEVER modified
 * - Full audit trail is preserved indefinitely
 *
 * COMPENSATING MOVEMENT PATTERN:
 * When an error needs to be corrected, create a new movement that reverses
 * or compensates for the original movement. Never modify the original.
 * Example: If items were transferred to wrong location, create a new
 * transfer movement to move them to the correct location.
 *
 * @property string $id
 * @property MovementType $movement_type
 * @property MovementTrigger $trigger
 * @property string|null $source_location_id
 * @property string|null $destination_location_id
 * @property bool $custody_changed
 * @property string|null $reason
 * @property string|null $wms_event_id
 * @property Carbon $executed_at
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
     *
     * @return HasMany<MovementItem, $this>
     */
    public function movementItems(): HasMany
    {
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

    // =========================================================================
    // IMMUTABILITY ENFORCEMENT (US-B051)
    // =========================================================================

    /**
     * Check if this model is immutable.
     * InventoryMovement is ALWAYS immutable.
     *
     * This method exists to make immutability explicit and queryable.
     */
    public static function isImmutable(): bool
    {
        return true;
    }

    /**
     * Check if this movement can be corrected.
     * Movements cannot be corrected directly - a compensating movement must be created instead.
     *
     * @return false Always returns false as movements are immutable
     */
    public function canBeEdited(): bool
    {
        return false;
    }

    /**
     * Check if this movement can be deleted.
     * Movements cannot be deleted - they form an append-only ledger.
     *
     * @return false Always returns false as movements are immutable
     */
    public function canBeDeleted(): bool
    {
        return false;
    }

    /**
     * Get guidance on how to correct an error in this movement.
     * Since movements are immutable, corrections must be made via compensating movements.
     *
     * @return string Human-readable guidance for operators
     */
    public function getCorrectionGuidance(): string
    {
        return 'This movement cannot be modified or deleted. To correct an error, '.
               'create a new compensating movement that reverses or corrects the effect '.
               'of the original movement. All movements are preserved for audit purposes.';
    }

    /**
     * Get the reason why this model is immutable.
     *
     * @return string Explanation of immutability
     */
    public static function getImmutabilityReason(): string
    {
        return 'InventoryMovement records form an append-only audit ledger. '.
               'Modifying or deleting movements would compromise the integrity of the inventory audit trail. '.
               'Use compensating movements to correct errors.';
    }
}
