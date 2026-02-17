<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\EmpConfidenceLevel;
use App\Enums\Commercial\EmpSource;
use App\Models\Pim\SellableSku;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EstimatedMarketPrice Model
 *
 * Represents estimated market prices as reference for pricing decisions.
 * EMP is read-only in Module S (imported from external process).
 *
 * @property string $id
 * @property string $sellable_sku_id
 * @property string $market
 * @property string $emp_value
 * @property EmpSource $source
 * @property EmpConfidenceLevel $confidence_level
 * @property Carbon|null $fetched_at
 */
class EstimatedMarketPrice extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'estimated_market_prices';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sellable_sku_id',
        'market',
        'emp_value',
        'source',
        'confidence_level',
        'fetched_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emp_value' => 'decimal:2',
            'source' => EmpSource::class,
            'confidence_level' => EmpConfidenceLevel::class,
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Get the Sellable SKU that this EMP belongs to.
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function sellableSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class);
    }

    /**
     * Check if the EMP data is fresh (less than 24 hours old).
     */
    public function isFresh(): bool
    {
        if ($this->fetched_at === null) {
            return false;
        }

        return $this->fetched_at->diffInHours(now()) < 24;
    }

    /**
     * Check if the EMP data is stale (more than 7 days old).
     */
    public function isStale(): bool
    {
        if ($this->fetched_at === null) {
            return true;
        }

        return $this->fetched_at->diffInDays(now()) > 7;
    }

    /**
     * Get the freshness indicator for UI display.
     */
    public function getFreshnessIndicator(): string
    {
        if ($this->fetched_at === null) {
            return 'unknown';
        }

        if ($this->isFresh()) {
            return 'fresh';
        }

        if ($this->isStale()) {
            return 'stale';
        }

        return 'recent';
    }

    /**
     * Get the freshness color for UI display.
     */
    public function getFreshnessColor(): string
    {
        return match ($this->getFreshnessIndicator()) {
            'fresh' => 'success',
            'recent' => 'warning',
            'stale' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the source label for UI display.
     */
    public function getSourceLabel(): string
    {
        return $this->source->label();
    }

    /**
     * Get the source color for UI display.
     */
    public function getSourceColor(): string
    {
        return $this->source->color();
    }

    /**
     * Get the confidence level label for UI display.
     */
    public function getConfidenceLevelLabel(): string
    {
        return $this->confidence_level->label();
    }

    /**
     * Get the confidence level color for UI display.
     */
    public function getConfidenceLevelColor(): string
    {
        return $this->confidence_level->color();
    }
}
