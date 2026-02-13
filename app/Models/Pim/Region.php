<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    use Auditable;
    use AuditLoggable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'country_id',
        'parent_region_id',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentRegion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_region_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function childRegions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_region_id');
    }

    /**
     * @return HasMany<Appellation, $this>
     */
    public function appellations(): HasMany
    {
        return $this->hasMany(Appellation::class);
    }

    /**
     * @return HasMany<Producer, $this>
     */
    public function producers(): HasMany
    {
        return $this->hasMany(Producer::class);
    }

    /**
     * @return HasMany<WineMaster, $this>
     */
    public function wineMasters(): HasMany
    {
        return $this->hasMany(WineMaster::class);
    }

    /**
     * Scope to top-level regions (no parent).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_region_id');
    }

    /**
     * Scope to regions within a specific country.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCountry(Builder $query, string $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Get the full hierarchical path (e.g. "France > Bordeaux > Pauillac").
     */
    public function getFullPathAttribute(): string
    {
        $parts = [$this->name];
        $current = $this->parentRegion;

        while ($current !== null) {
            array_unshift($parts, $current->name);
            $current = $current->parentRegion;
        }

        $country = $this->country;
        if ($country !== null) {
            array_unshift($parts, $country->name);
        }

        return implode(' > ', $parts);
    }
}
