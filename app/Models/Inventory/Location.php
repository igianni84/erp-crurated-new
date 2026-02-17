<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Models\AuditLog;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Location Model
 *
 * Represents a physical location where wine can be stored.
 * Locations are the foundation of the inventory system, defining
 * where physical wine exists and whether serialization can occur.
 *
 * @property string $id
 * @property string $name
 * @property LocationType $location_type
 * @property string $country
 * @property string|null $address
 * @property bool $serialization_authorized
 * @property string|null $linked_wms_id
 * @property LocationStatus $status
 * @property string|null $notes
 */
class Location extends Model
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
    protected $table = 'locations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'location_type',
        'country',
        'address',
        'serialization_authorized',
        'linked_wms_id',
        'status',
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
            'location_type' => LocationType::class,
            'status' => LocationStatus::class,
            'serialization_authorized' => 'boolean',
        ];
    }

    /**
     * Get the audit logs for this location.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get the serialized bottles currently at this location.
     *
     * @return HasMany<SerializedBottle, $this>
     */
    public function serializedBottles(): HasMany
    {
        return $this->hasMany(SerializedBottle::class, 'current_location_id');
    }

    /**
     * Get the inbound batches received at this location.
     *
     * @return HasMany<InboundBatch, $this>
     */
    public function inboundBatches(): HasMany
    {
        return $this->hasMany(InboundBatch::class, 'receiving_location_id');
    }

    /**
     * Get the cases currently at this location.
     *
     * @return HasMany<InventoryCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(InventoryCase::class, 'current_location_id');
    }

    /**
     * Check if serialization can be performed at this location.
     */
    public function canSerialize(): bool
    {
        return $this->serialization_authorized && $this->status === LocationStatus::Active;
    }

    /**
     * Check if the location is active.
     */
    public function isActive(): bool
    {
        return $this->status === LocationStatus::Active;
    }

    /**
     * Check if the location is linked to a WMS.
     */
    public function hasWmsLink(): bool
    {
        return $this->linked_wms_id !== null;
    }

    /**
     * Get a display label for the location.
     */
    public function getDisplayLabelAttribute(): string
    {
        return "{$this->name} ({$this->country})";
    }
}
