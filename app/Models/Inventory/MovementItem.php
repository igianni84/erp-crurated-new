<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * MovementItem Model
 *
 * Represents a detail item involved in an inventory movement.
 * Items are immutable: insert only, no update, no delete.
 * Each item references either a serialized bottle or a case (or both).
 *
 * IMMUTABILITY RULES:
 * - Items cannot be updated after creation
 * - Items cannot be deleted
 *
 * @property int $id
 * @property string $inventory_movement_id
 * @property string|null $serialized_bottle_id
 * @property string|null $case_id
 * @property int $quantity
 * @property string|null $notes
 */
class MovementItem extends Model
{
    use HasFactory;

    // NO SoftDeletes trait - items are immutable and never deleted
    // NO HasUuid trait - items use auto-incrementing id as they are not standalone entities

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'movement_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inventory_movement_id',
        'serialized_bottle_id',
        'case_id',
        'quantity',
        'notes',
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
        ];
    }

    /**
     * Boot the model.
     * Enforces immutability: prevents updates and deletes.
     * Validates that at least one of serialized_bottle_id or case_id is set.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Validate that at least one reference is set on creation
        static::creating(function (MovementItem $item): void {
            if ($item->serialized_bottle_id === null && $item->case_id === null) {
                throw new InvalidArgumentException(
                    'MovementItem must reference at least one of: serialized_bottle_id or case_id'
                );
            }
        });

        // Prevent any updates to existing records
        static::updating(function ($model): void {
            throw new InvalidArgumentException(
                'MovementItem records are immutable and cannot be updated.'
            );
        });

        // Prevent deletion of records
        static::deleting(function ($model): void {
            throw new InvalidArgumentException(
                'MovementItem records are immutable and cannot be deleted.'
            );
        });
    }

    /**
     * Get the inventory movement this item belongs to.
     *
     * @return BelongsTo<InventoryMovement, $this>
     */
    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    /**
     * Get the serialized bottle for this item (nullable).
     *
     * @return BelongsTo<SerializedBottle, $this>
     */
    public function serializedBottle(): BelongsTo
    {
        return $this->belongsTo(SerializedBottle::class);
    }

    /**
     * Get the case for this item (nullable).
     *
     * @return BelongsTo<InventoryCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(InventoryCase::class, 'case_id');
    }

    /**
     * Check if this item references a serialized bottle.
     */
    public function hasBottle(): bool
    {
        return $this->serialized_bottle_id !== null;
    }

    /**
     * Check if this item references a case.
     */
    public function hasCase(): bool
    {
        return $this->case_id !== null;
    }

    /**
     * Get a display label for this movement item.
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->hasBottle() && $this->hasCase()) {
            return "Bottle & Case #{$this->id}";
        }

        if ($this->hasBottle()) {
            return "Bottle #{$this->serialized_bottle_id}";
        }

        return "Case #{$this->case_id}";
    }
}
