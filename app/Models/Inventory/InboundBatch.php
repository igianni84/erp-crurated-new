<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Models\Allocation\Allocation;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InboundBatch Model
 *
 * Represents a physical receipt record from Module D.
 * This is the bridge between procurement/transfers and serialized inventory.
 * InboundBatch preserves allocation lineage for all bottles created from it.
 *
 * @property string $id
 * @property string $source_type
 * @property string $product_reference_type
 * @property string $product_reference_id
 * @property string|null $allocation_id
 * @property string|null $procurement_intent_id
 * @property int $quantity_expected
 * @property int $quantity_received
 * @property string $packaging_type
 * @property string $receiving_location_id
 * @property OwnershipType $ownership_type
 * @property \Carbon\Carbon $received_date
 * @property string|null $condition_notes
 * @property InboundBatchStatus $serialization_status
 * @property string|null $wms_reference_id
 */
class InboundBatch extends Model
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
    protected $table = 'inbound_batches';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source_type',
        'product_reference_type',
        'product_reference_id',
        'allocation_id',
        'procurement_intent_id',
        'quantity_expected',
        'quantity_received',
        'packaging_type',
        'receiving_location_id',
        'ownership_type',
        'received_date',
        'condition_notes',
        'serialization_status',
        'wms_reference_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ownership_type' => OwnershipType::class,
            'serialization_status' => InboundBatchStatus::class,
            'received_date' => 'date',
            'quantity_expected' => 'integer',
            'quantity_received' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (InboundBatch $batch): void {
            // Ensure quantity_received is non-negative
            if ($batch->quantity_received < 0) {
                throw new \InvalidArgumentException(
                    'quantity_received cannot be negative'
                );
            }
        });
    }

    /**
     * Get the receiving location for this batch.
     *
     * @return BelongsTo<Location, $this>
     */
    public function receivingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'receiving_location_id');
    }

    /**
     * Get the allocation for this batch (allocation lineage).
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Get the product reference (morphic relationship).
     *
     * @return MorphTo<Model, $this>
     */
    public function productReference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the audit logs for this inbound batch.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if serialization can be started on this batch.
     */
    public function canStartSerialization(): bool
    {
        // Check serialization status allows it
        if (! $this->serialization_status->canStartSerialization()) {
            return false;
        }

        // Check receiving location allows serialization
        $location = $this->receivingLocation;
        if (! $location || ! $location->canSerialize()) {
            return false;
        }

        // Check there are remaining bottles to serialize
        return $this->getRemainingUnserializedAttribute() > 0;
    }

    /**
     * Get the remaining unserialized quantity.
     */
    public function getRemainingUnserializedAttribute(): int
    {
        // This will be updated when SerializedBottle model is created
        // For now, return quantity_received as all are unserialized
        return $this->quantity_received;
    }

    /**
     * Check if there is a discrepancy between expected and received quantities.
     */
    public function hasDiscrepancy(): bool
    {
        return $this->quantity_expected !== $this->quantity_received;
    }

    /**
     * Get the quantity delta (received - expected).
     * Positive = overage, Negative = shortage.
     */
    public function getQuantityDeltaAttribute(): int
    {
        return $this->quantity_received - $this->quantity_expected;
    }

    /**
     * Check if the batch has an allocation lineage.
     */
    public function hasAllocationLineage(): bool
    {
        return $this->allocation_id !== null;
    }

    /**
     * Check if the batch has a WMS reference.
     */
    public function hasWmsReference(): bool
    {
        return $this->wms_reference_id !== null;
    }

    /**
     * Get a display label for the batch.
     */
    public function getDisplayLabelAttribute(): string
    {
        /** @var \Carbon\Carbon|null $receivedDate */
        $receivedDate = $this->received_date;
        $date = $receivedDate instanceof \Carbon\Carbon
            ? $receivedDate->format('Y-m-d')
            : 'Unknown date';

        return "Batch #{$this->id} ({$date})";
    }

    /**
     * Check if the batch is pending serialization.
     */
    public function isPendingSerialization(): bool
    {
        return $this->serialization_status === InboundBatchStatus::PendingSerialization;
    }

    /**
     * Check if the batch is partially serialized.
     */
    public function isPartiallySerialized(): bool
    {
        return $this->serialization_status === InboundBatchStatus::PartiallySerialized;
    }

    /**
     * Check if the batch is fully serialized.
     */
    public function isFullySerialized(): bool
    {
        return $this->serialization_status === InboundBatchStatus::FullySerialized;
    }

    /**
     * Check if the batch has a discrepancy status.
     */
    public function hasDiscrepancyStatus(): bool
    {
        return $this->serialization_status === InboundBatchStatus::Discrepancy;
    }

    /**
     * Check if the batch requires attention.
     */
    public function requiresAttention(): bool
    {
        return $this->serialization_status->requiresAttention();
    }
}
