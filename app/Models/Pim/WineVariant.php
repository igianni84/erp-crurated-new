<?php

namespace App\Models\Pim;

use App\Enums\ProductLifecycleStatus;
use App\Traits\Auditable;
use App\Traits\HasProductLifecycle;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProductLifecycleStatus $lifecycle_status
 */
class WineVariant extends Model
{
    use Auditable;
    use HasFactory;
    use HasProductLifecycle;
    use HasUuid;
    use SoftDeletes;

    /**
     * Sensitive fields that trigger automatic status change to in_review when modified on published products.
     *
     * @var list<string>
     */
    public const SENSITIVE_FIELDS = [
        'wine_master_id',
        'vintage_year',
        'alcohol_percentage',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wine_master_id',
        'vintage_year',
        'alcohol_percentage',
        'drinking_window_start',
        'drinking_window_end',
        'critic_scores',
        'production_notes',
        'lifecycle_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vintage_year' => 'integer',
            'alcohol_percentage' => 'decimal:2',
            'drinking_window_start' => 'integer',
            'drinking_window_end' => 'integer',
            'critic_scores' => 'array',
            'production_notes' => 'array',
            'lifecycle_status' => ProductLifecycleStatus::class,
        ];
    }

    /**
     * Get the wine master that owns this variant.
     *
     * @return BelongsTo<WineMaster, $this>
     */
    public function wineMaster(): BelongsTo
    {
        return $this->belongsTo(WineMaster::class);
    }

    /**
     * Get the sellable SKUs for this wine variant.
     *
     * @return HasMany<SellableSku, $this>
     */
    public function sellableSkus(): HasMany
    {
        return $this->hasMany(SellableSku::class);
    }

    /**
     * Get the liquid product for this wine variant.
     *
     * @return HasOne<LiquidProduct, $this>
     */
    public function liquidProduct(): HasOne
    {
        return $this->hasOne(LiquidProduct::class);
    }

    /**
     * Field completeness configuration with weights.
     * Required fields have higher weights, optional fields have lower weights.
     *
     * @var array<string, array{weight: int, required: bool}>
     */
    public const COMPLETENESS_FIELDS = [
        // Required fields (high weight)
        'wine_master_id' => ['weight' => 20, 'required' => true],
        'vintage_year' => ['weight' => 20, 'required' => true],
        // Important optional fields (medium weight)
        'alcohol_percentage' => ['weight' => 15, 'required' => false],
        'drinking_window_start' => ['weight' => 10, 'required' => false],
        'drinking_window_end' => ['weight' => 10, 'required' => false],
        'critic_scores' => ['weight' => 10, 'required' => false],
        // Nice-to-have fields (lower weight)
        'production_notes' => ['weight' => 5, 'required' => false],
        'has_sellable_skus' => ['weight' => 10, 'required' => false],
    ];

    /**
     * Calculate the completeness percentage for this wine variant.
     * Returns a value between 0 and 100.
     */
    public function getCompletenessPercentage(): int
    {
        $totalWeight = 0;
        $completedWeight = 0;

        foreach (self::COMPLETENESS_FIELDS as $config) {
            $totalWeight += $config['weight'];
        }

        foreach (self::COMPLETENESS_FIELDS as $field => $config) {
            if ($this->isFieldComplete($field)) {
                $completedWeight += $config['weight'];
            }
        }

        return (int) round(($completedWeight / $totalWeight) * 100);
    }

    /**
     * Check if a specific field is considered complete.
     */
    protected function isFieldComplete(string $field): bool
    {
        return match ($field) {
            'wine_master_id' => $this->getAttribute('wine_master_id') !== null,
            'vintage_year' => $this->getAttribute('vintage_year') !== null,
            'alcohol_percentage' => $this->getAttribute('alcohol_percentage') !== null,
            'drinking_window_start' => $this->getAttribute('drinking_window_start') !== null,
            'drinking_window_end' => $this->getAttribute('drinking_window_end') !== null,
            'critic_scores' => ! empty($this->critic_scores),
            'production_notes' => ! empty($this->production_notes),
            'has_sellable_skus' => $this->sellableSkus()->count() > 0,
            default => false,
        };
    }

    /**
     * Get the completeness color based on percentage.
     * <50% = danger (red), 50-80% = warning (yellow), >80% = success (green)
     */
    public function getCompletenessColor(): string
    {
        $percentage = $this->getCompletenessPercentage();

        if ($percentage < 50) {
            return 'danger';
        }

        if ($percentage <= 80) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Get the list of incomplete required fields.
     *
     * @return list<string>
     */
    public function getIncompleteRequiredFields(): array
    {
        $incomplete = [];

        foreach (self::COMPLETENESS_FIELDS as $field => $config) {
            if ($config['required'] && ! $this->isFieldComplete($field)) {
                $incomplete[] = $field;
            }
        }

        return $incomplete;
    }
}
