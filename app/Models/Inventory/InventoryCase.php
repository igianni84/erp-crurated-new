<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\CaseIntegrityStatus;
use App\Models\Allocation\Allocation;
use App\Models\Pim\CaseConfiguration;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InventoryCase Model
 *
 * Represents a physical case (container) for bottles in the inventory system.
 * Cases can be intact or broken, and track their contained bottles.
 *
 * INTEGRITY RULES:
 * - Once a case is broken (integrity_status = BROKEN), it cannot revert to INTACT
 * - Broken cases remain in the system for audit purposes
 *
 * @property string $id
 * @property string $case_configuration_id
 * @property string $allocation_id
 * @property string|null $inbound_batch_id
 * @property string $current_location_id
 * @property bool $is_original
 * @property bool $is_breakable
 * @property CaseIntegrityStatus $integrity_status
 * @property \Carbon\Carbon|null $broken_at
 * @property int|null $broken_by
 * @property string|null $broken_reason
 */
class InventoryCase extends Model
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
    protected $table = 'cases';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'case_configuration_id',
        'allocation_id',
        'inbound_batch_id',
        'current_location_id',
        'is_original',
        'is_breakable',
        'integrity_status',
        'broken_at',
        'broken_by',
        'broken_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'integrity_status' => CaseIntegrityStatus::class,
            'is_original' => 'boolean',
            'is_breakable' => 'boolean',
            'broken_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Enforce that broken cases cannot be reverted to intact (US-B052)
        static::updating(function (InventoryCase $case): void {
            if ($case->isDirty('integrity_status')) {
                $originalStatus = $case->getOriginal('integrity_status');
                $newStatus = $case->integrity_status;

                // If original was broken and trying to change to intact, block it
                if ($originalStatus === CaseIntegrityStatus::Broken && $newStatus === CaseIntegrityStatus::Intact) {
                    throw new \InvalidArgumentException(
                        'Cannot revert case integrity status from BROKEN to INTACT. Breaking is irreversible.'
                    );
                }
            }
        });
    }

    /**
     * Get the case configuration.
     *
     * @return BelongsTo<CaseConfiguration, $this>
     */
    public function caseConfiguration(): BelongsTo
    {
        return $this->belongsTo(CaseConfiguration::class);
    }

    /**
     * Get the allocation (allocation lineage).
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Get the inbound batch this case came from (nullable).
     *
     * @return BelongsTo<InboundBatch, $this>
     */
    public function inboundBatch(): BelongsTo
    {
        return $this->belongsTo(InboundBatch::class);
    }

    /**
     * Get the current location of this case.
     *
     * @return BelongsTo<Location, $this>
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /**
     * Get the user who broke this case.
     *
     * @return BelongsTo<User, $this>
     */
    public function brokenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'broken_by');
    }

    /**
     * Get the serialized bottles contained in this case.
     *
     * @return HasMany<SerializedBottle, $this>
     */
    public function serializedBottles(): HasMany
    {
        return $this->hasMany(SerializedBottle::class, 'case_id');
    }

    /**
     * Get the audit logs for this case.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Get the movement items associated with this case.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<MovementItem, $this>
     */
    public function movementItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MovementItem::class, 'case_id');
    }

    /**
     * Get the inventory movements that involve this case.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<InventoryMovement, MovementItem, $this>
     */
    public function movements(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            InventoryMovement::class,
            MovementItem::class,
            'case_id', // Foreign key on movement_items table
            'id', // Foreign key on inventory_movements table
            'id', // Local key on cases table
            'inventory_movement_id' // Local key on movement_items table
        );
    }

    /**
     * Check if the case is intact.
     */
    public function isIntact(): bool
    {
        return $this->integrity_status === CaseIntegrityStatus::Intact;
    }

    /**
     * Check if the case is broken.
     */
    public function isBroken(): bool
    {
        return $this->integrity_status === CaseIntegrityStatus::Broken;
    }

    /**
     * Check if the case can be handled as a unit (i.e., is intact).
     */
    public function canHandleAsUnit(): bool
    {
        return $this->integrity_status->canHandleAsUnit();
    }

    /**
     * Check if this case can be broken.
     */
    public function canBreak(): bool
    {
        return $this->is_breakable && $this->isIntact();
    }

    /**
     * Get the count of bottles in this case.
     */
    public function getBottleCountAttribute(): int
    {
        return $this->serializedBottles()->count();
    }

    /**
     * Get a display label for the case.
     */
    public function getDisplayLabelAttribute(): string
    {
        return "Case #{$this->id}";
    }
}
