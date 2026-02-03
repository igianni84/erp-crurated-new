<?php

namespace App\Models\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Allocation Model
 *
 * Represents a supply allocation at the Bottle SKU level (WineVariant + Format).
 * This is the authoritative source of sellable supply for Module A.
 *
 * @property AllocationSourceType $source_type
 * @property AllocationSupplyForm $supply_form
 * @property AllocationStatus $status
 * @property int $total_quantity
 * @property int $sold_quantity
 * @property int $remaining_quantity
 * @property bool $serialization_required
 */
class Allocation extends Model
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
    protected $table = 'allocations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wine_variant_id',
        'format_id',
        'source_type',
        'supply_form',
        'total_quantity',
        'sold_quantity',
        'expected_availability_start',
        'expected_availability_end',
        'serialization_required',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => AllocationSourceType::class,
            'supply_form' => AllocationSupplyForm::class,
            'status' => AllocationStatus::class,
            'total_quantity' => 'integer',
            'sold_quantity' => 'integer',
            'expected_availability_start' => 'date',
            'expected_availability_end' => 'date',
            'serialization_required' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Allocation $allocation): void {
            // Ensure sold_quantity never exceeds total_quantity
            if ($allocation->sold_quantity > $allocation->total_quantity) {
                throw new \InvalidArgumentException(
                    'sold_quantity cannot exceed total_quantity'
                );
            }
        });
    }

    /**
     * Get the wine variant for this allocation.
     *
     * @return BelongsTo<WineVariant, $this>
     */
    public function wineVariant(): BelongsTo
    {
        return $this->belongsTo(WineVariant::class);
    }

    /**
     * Get the format for this allocation (bottle size).
     *
     * @return BelongsTo<Format, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /**
     * Get the remaining quantity (total - sold).
     * This is a computed attribute.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return $this->total_quantity - $this->sold_quantity;
    }

    /**
     * Check if the allocation is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === AllocationStatus::Draft;
    }

    /**
     * Check if the allocation is active.
     */
    public function isActive(): bool
    {
        return $this->status === AllocationStatus::Active;
    }

    /**
     * Check if the allocation is exhausted.
     */
    public function isExhausted(): bool
    {
        return $this->status === AllocationStatus::Exhausted;
    }

    /**
     * Check if the allocation is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === AllocationStatus::Closed;
    }

    /**
     * Check if constraints can be edited.
     */
    public function canEditConstraints(): bool
    {
        return $this->status->allowsConstraintEditing();
    }

    /**
     * Check if the allocation can be consumed (sold).
     */
    public function canBeConsumed(): bool
    {
        return $this->status->allowsConsumption() && $this->remaining_quantity > 0;
    }

    /**
     * Check if the allocation is near exhaustion (less than 10% remaining).
     */
    public function isNearExhaustion(): bool
    {
        if ($this->total_quantity === 0) {
            return false;
        }

        return ($this->remaining_quantity / $this->total_quantity) < 0.10;
    }

    /**
     * Get a display label for the bottle SKU.
     */
    public function getBottleSkuLabel(): string
    {
        $wineVariant = $this->wineVariant;
        $format = $this->format;

        if (! $wineVariant || ! $format) {
            return 'Unknown';
        }

        $wineMaster = $wineVariant->wineMaster;
        $wineName = $wineMaster ? $wineMaster->name : 'Unknown Wine';
        $vintage = $wineVariant->vintage_year ?? 'NV';
        $formatLabel = $format->volume_ml.'ml';

        return "{$wineName} {$vintage} - {$formatLabel}";
    }

    /**
     * Get the availability window as a formatted string.
     */
    public function getAvailabilityWindowLabel(): string
    {
        /** @var \Carbon\Carbon|null $start */
        $start = $this->expected_availability_start;
        /** @var \Carbon\Carbon|null $end */
        $end = $this->expected_availability_end;

        $hasStart = $start instanceof \Carbon\Carbon;
        $hasEnd = $end instanceof \Carbon\Carbon;

        if (! $hasStart && ! $hasEnd) {
            return 'Not specified';
        }

        $startStr = $hasStart ? $start->format('Y-m-d') : '';
        $endStr = $hasEnd ? $end->format('Y-m-d') : '';

        if ($hasStart && ! $hasEnd) {
            return 'From '.$startStr;
        }

        if (! $hasStart) {
            return 'Until '.$endStr;
        }

        return $startStr.' - '.$endStr;
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }
}
