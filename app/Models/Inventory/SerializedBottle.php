<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\OwnershipType;
use App\Models\Allocation\Allocation;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SerializedBottle Model
 *
 * Represents a uniquely serialized bottle with immutable identity.
 * This is a first-class object in the inventory system with unique serial number
 * and permanent allocation lineage.
 *
 * IMMUTABILITY RULES:
 * - serial_number: Cannot be changed after creation
 * - allocation_id: Cannot be changed after creation (allocation lineage is permanent)
 *
 * @property string $id
 * @property string $serial_number
 * @property string $wine_variant_id
 * @property string $format_id
 * @property string $allocation_id
 * @property string $inbound_batch_id
 * @property string $current_location_id
 * @property string|null $case_id
 * @property OwnershipType $ownership_type
 * @property string|null $custody_holder
 * @property BottleState $state
 * @property \Carbon\Carbon $serialized_at
 * @property int|null $serialized_by
 * @property string|null $nft_reference
 * @property \Carbon\Carbon|null $nft_minted_at
 * @property string|null $correction_reference
 */
class SerializedBottle extends Model
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
    protected $table = 'serialized_bottles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'serial_number',
        'wine_variant_id',
        'format_id',
        'allocation_id',
        'inbound_batch_id',
        'current_location_id',
        'case_id',
        'ownership_type',
        'custody_holder',
        'state',
        'serialized_at',
        'serialized_by',
        'nft_reference',
        'nft_minted_at',
        'correction_reference',
    ];

    /**
     * Immutable fields that cannot be changed after creation.
     *
     * These fields are protected by DUAL enforcement:
     * 1. Attribute mutators (setXxxAttribute) that block assignment on existing records
     * 2. Boot guard (static::updating) that blocks dirty field updates
     *
     * @var list<string>
     */
    protected static array $immutableFields = [
        'serial_number',
        'allocation_id',
    ];

    /**
     * Check if a field is immutable.
     *
     * @param  string  $field  The field name to check
     * @return bool True if the field is immutable
     */
    public static function isImmutableField(string $field): bool
    {
        return in_array($field, self::$immutableFields, true);
    }

    /**
     * Get the list of immutable fields.
     *
     * @return list<string>
     */
    public static function getImmutableFields(): array
    {
        return self::$immutableFields;
    }

    /**
     * Set the serial_number attribute.
     *
     * Enforces immutability: serial_number cannot be changed after creation.
     *
     * @throws \InvalidArgumentException If attempting to modify an existing serial_number
     */
    public function setSerialNumberAttribute(string $value): void
    {
        // If the model exists (not new) and serial_number is already set, block modification
        if ($this->exists && $this->getOriginal('serial_number') !== null) {
            throw new \InvalidArgumentException(
                'Serial number is immutable and cannot be changed after creation. Use the mis-serialization correction flow (US-B029) instead.'
            );
        }

        $this->attributes['serial_number'] = $value;
    }

    /**
     * Set the allocation_id attribute.
     *
     * Enforces immutability: allocation_id (allocation lineage) cannot be changed after creation.
     * The allocation lineage is propagated from InboundBatch at serialization time and is permanent.
     *
     * @throws \InvalidArgumentException If attempting to modify an existing allocation_id
     */
    public function setAllocationIdAttribute(string $value): void
    {
        // If the model exists (not new) and allocation_id is already set, block modification
        if ($this->exists && $this->getOriginal('allocation_id') !== null) {
            throw new \InvalidArgumentException(
                'Allocation lineage is immutable and cannot be changed after creation. Bottles are permanently bound to their original allocation.'
            );
        }

        $this->attributes['allocation_id'] = $value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ownership_type' => OwnershipType::class,
            'state' => BottleState::class,
            'serialized_at' => 'datetime',
            'nft_minted_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Enforce immutability of serial_number and allocation_id
        static::updating(function (SerializedBottle $bottle): void {
            foreach (self::$immutableFields as $field) {
                if ($bottle->isDirty($field)) {
                    throw new \InvalidArgumentException(
                        "Cannot modify immutable field '{$field}' on SerializedBottle"
                    );
                }
            }
        });
    }

    /**
     * Get the wine variant for this bottle.
     *
     * @return BelongsTo<WineVariant, $this>
     */
    public function wineVariant(): BelongsTo
    {
        return $this->belongsTo(WineVariant::class);
    }

    /**
     * Get the format for this bottle.
     *
     * @return BelongsTo<Format, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /**
     * Get the allocation (immutable allocation lineage).
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Get the inbound batch this bottle was serialized from.
     *
     * @return BelongsTo<InboundBatch, $this>
     */
    public function inboundBatch(): BelongsTo
    {
        return $this->belongsTo(InboundBatch::class);
    }

    /**
     * Get the current location of this bottle.
     *
     * @return BelongsTo<Location, $this>
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /**
     * Get the case this bottle belongs to (nullable).
     *
     * @return BelongsTo<InventoryCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(InventoryCase::class, 'case_id');
    }

    /**
     * Get the user who serialized this bottle.
     *
     * @return BelongsTo<User, $this>
     */
    public function serializedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'serialized_by');
    }

    /**
     * Get the audit logs for this bottle.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Get the movement items associated with this bottle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<MovementItem, $this>
     */
    public function movementItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MovementItem::class, 'serialized_bottle_id');
    }

    /**
     * Get the inventory movements that involve this bottle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<InventoryMovement, MovementItem, $this>
     */
    public function movements(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            InventoryMovement::class,
            MovementItem::class,
            'serialized_bottle_id', // Foreign key on movement_items table
            'id', // Foreign key on inventory_movements table
            'id', // Local key on serialized_bottles table
            'inventory_movement_id' // Local key on movement_items table
        );
    }

    /**
     * Check if the bottle is available for fulfillment.
     */
    public function isAvailableForFulfillment(): bool
    {
        return $this->state->isAvailableForFulfillment();
    }

    /**
     * Check if the bottle is physically present.
     */
    public function isPhysicallyPresent(): bool
    {
        return $this->state->isPhysicallyPresent();
    }

    /**
     * Check if the bottle is in a terminal state.
     */
    public function isInTerminalState(): bool
    {
        return $this->state->isTerminal();
    }

    /**
     * Check if the bottle has an NFT minted.
     */
    public function hasNft(): bool
    {
        return $this->nft_reference !== null;
    }

    /**
     * Check if the bottle is in a case.
     */
    public function isInCase(): bool
    {
        return $this->case_id !== null;
    }

    /**
     * Check if the bottle is stored.
     */
    public function isStored(): bool
    {
        return $this->state === BottleState::Stored;
    }

    /**
     * Check if the bottle is reserved for picking.
     */
    public function isReservedForPicking(): bool
    {
        return $this->state === BottleState::ReservedForPicking;
    }

    /**
     * Check if the bottle has been shipped.
     */
    public function isShipped(): bool
    {
        return $this->state === BottleState::Shipped;
    }

    /**
     * Check if the bottle has been consumed.
     */
    public function isConsumed(): bool
    {
        return $this->state === BottleState::Consumed;
    }

    /**
     * Check if the bottle has been destroyed.
     */
    public function isDestroyed(): bool
    {
        return $this->state === BottleState::Destroyed;
    }

    /**
     * Check if the bottle is missing.
     */
    public function isMissing(): bool
    {
        return $this->state === BottleState::Missing;
    }

    /**
     * Check if Crurated owns this bottle.
     */
    public function isCuratedOwned(): bool
    {
        return $this->ownership_type === OwnershipType::CururatedOwned;
    }

    /**
     * Check if this bottle can be consumed for events.
     */
    public function canConsumeForEvents(): bool
    {
        return $this->ownership_type->canConsumeForEvents() && $this->isStored();
    }

    /**
     * Get a display label for the bottle.
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->serial_number;
    }

    /**
     * Check if the bottle has been marked as mis-serialized.
     */
    public function isMisSerialized(): bool
    {
        return $this->state === BottleState::MisSerialized;
    }

    /**
     * Check if this bottle has a correction reference.
     */
    public function hasCorrectionReference(): bool
    {
        return $this->correction_reference !== null;
    }

    /**
     * Get the linked bottle (either original or corrective).
     *
     * @return BelongsTo<SerializedBottle, $this>
     */
    public function linkedBottle(): BelongsTo
    {
        return $this->belongsTo(SerializedBottle::class, 'correction_reference');
    }

    /**
     * Check if this bottle can be flagged as mis-serialized.
     * Only admin can flag, and bottle must not already be in a terminal state.
     */
    public function canFlagAsMisSerialized(): bool
    {
        // Cannot flag if already in terminal state (includes mis_serialized)
        return ! $this->isInTerminalState();
    }
}
