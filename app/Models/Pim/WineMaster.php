<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WineMaster extends Model
{
    use Auditable;
    use AuditLoggable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'producer',
        'producer_id',
        'appellation',
        'appellation_id',
        'classification',
        'country',
        'country_id',
        'region',
        'region_id',
        'description',
        'liv_ex_code',
        'regulatory_attributes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'regulatory_attributes' => 'array',
        ];
    }

    // =========================================================================
    // Relationships to lookup tables
    // =========================================================================

    /**
     * @return BelongsTo<Producer, $this>
     */
    public function producerRelation(): BelongsTo
    {
        return $this->belongsTo(Producer::class, 'producer_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function countryRelation(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function regionRelation(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * @return BelongsTo<Appellation, $this>
     */
    public function appellationRelation(): BelongsTo
    {
        return $this->belongsTo(Appellation::class, 'appellation_id');
    }

    // =========================================================================
    // Existing relationships
    // =========================================================================

    /**
     * Get the wine variants for this wine master.
     *
     * @return HasMany<WineVariant, $this>
     */
    public function wineVariants(): HasMany
    {
        return $this->hasMany(WineVariant::class);
    }

    // =========================================================================
    // Display accessors (FK record with legacy string fallback)
    // =========================================================================

    public function getProducerNameAttribute(): string
    {
        $relation = $this->producerRelation;

        return $relation !== null ? $relation->name : ($this->producer ?? '');
    }

    public function getCountryNameAttribute(): string
    {
        $relation = $this->countryRelation;

        return $relation !== null ? $relation->name : ($this->country ?? '');
    }

    public function getRegionNameAttribute(): string
    {
        $relation = $this->regionRelation;

        return $relation !== null ? $relation->name : ($this->region ?? '');
    }

    public function getAppellationNameAttribute(): string
    {
        $relation = $this->appellationRelation;

        return $relation !== null ? $relation->name : ($this->appellation ?? '');
    }
}
