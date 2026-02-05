<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\BundlePricingLogic;
use App\Enums\Commercial\BundleStatus;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bundle Model
 *
 * Represents a commercial grouping of Sellable SKUs sold together.
 * Bundles can have different pricing strategies: sum of components,
 * fixed price, or percentage off the sum.
 *
 * A Bundle generates a composite Sellable SKU in PIM for tracking purposes.
 * The bundle_sku field stores the reference to this composite SKU.
 *
 * Pricing Logic:
 * - sum_components: Bundle price equals sum of all component prices
 * - fixed_price: Bundle has a manually set price (stored in fixed_price field)
 * - percentage_off_sum: Bundle price is sum of components minus a percentage
 *
 * @property string $id
 * @property string $name
 * @property string $bundle_sku
 * @property BundlePricingLogic $pricing_logic
 * @property string|null $fixed_price
 * @property float|null $percentage_off
 * @property BundleStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Bundle extends Model
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
    protected $table = 'bundles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'bundle_sku',
        'pricing_logic',
        'fixed_price',
        'percentage_off',
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
            'pricing_logic' => BundlePricingLogic::class,
            'fixed_price' => 'decimal:2',
            'percentage_off' => 'decimal:2',
            'status' => BundleStatus::class,
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the components of this bundle.
     *
     * @return HasMany<BundleComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(BundleComponent::class, 'bundle_id');
    }

    /**
     * Get the audit logs for this bundle.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    /**
     * Check if the bundle is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === BundleStatus::Draft;
    }

    /**
     * Check if the bundle is active.
     */
    public function isActive(): bool
    {
        return $this->status === BundleStatus::Active;
    }

    /**
     * Check if the bundle is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === BundleStatus::Inactive;
    }

    /**
     * Check if the bundle can be activated.
     * Draft bundles with at least one component can be activated.
     */
    public function canBeActivated(): bool
    {
        return $this->isDraft() && $this->hasComponents();
    }

    /**
     * Check if the bundle can be deactivated.
     * Active bundles can be deactivated.
     */
    public function canBeDeactivated(): bool
    {
        return $this->isActive();
    }

    /**
     * Check if the bundle can be edited.
     * Only draft bundles can be edited.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    // =========================================================================
    // Pricing Logic Helpers
    // =========================================================================

    /**
     * Check if pricing is sum of components.
     */
    public function isSumComponents(): bool
    {
        return $this->pricing_logic === BundlePricingLogic::SumComponents;
    }

    /**
     * Check if pricing is fixed price.
     */
    public function isFixedPrice(): bool
    {
        return $this->pricing_logic === BundlePricingLogic::FixedPrice;
    }

    /**
     * Check if pricing is percentage off sum.
     */
    public function isPercentageOffSum(): bool
    {
        return $this->pricing_logic === BundlePricingLogic::PercentageOffSum;
    }

    /**
     * Get the fixed price value as float.
     */
    public function getFixedPriceValue(): ?float
    {
        if ($this->fixed_price === null) {
            return null;
        }

        return (float) $this->fixed_price;
    }

    /**
     * Get the percentage off value as float.
     */
    public function getPercentageOffValue(): ?float
    {
        if ($this->percentage_off === null) {
            return null;
        }

        return (float) $this->percentage_off;
    }

    // =========================================================================
    // Component Helpers
    // =========================================================================

    /**
     * Check if the bundle has any components.
     */
    public function hasComponents(): bool
    {
        return $this->components()->exists();
    }

    /**
     * Get the number of components in this bundle.
     */
    public function getComponentsCount(): int
    {
        return $this->components()->count();
    }

    /**
     * Get the total quantity of all components (sum of quantities).
     */
    public function getTotalComponentQuantity(): int
    {
        return (int) $this->components()->sum('quantity');
    }

    /**
     * Calculate the bundle price based on the pricing logic.
     *
     * @param  float  $sumOfComponents  The sum of all component prices
     * @return float The calculated bundle price
     */
    public function calculateBundlePrice(float $sumOfComponents): float
    {
        return match ($this->pricing_logic) {
            BundlePricingLogic::SumComponents => $sumOfComponents,
            BundlePricingLogic::FixedPrice => $this->fixed_price !== null
                ? (float) $this->fixed_price
                : $sumOfComponents,
            BundlePricingLogic::PercentageOffSum => $this->percentage_off !== null
                ? $sumOfComponents * (1 - (float) $this->percentage_off / 100)
                : $sumOfComponents,
        };
    }

    // =========================================================================
    // UI Helper Methods
    // =========================================================================

    /**
     * Get the pricing logic label for UI display.
     */
    public function getPricingLogicLabel(): string
    {
        return $this->pricing_logic->label();
    }

    /**
     * Get the pricing logic color for UI display.
     */
    public function getPricingLogicColor(): string
    {
        return $this->pricing_logic->color();
    }

    /**
     * Get the pricing logic icon for UI display.
     */
    public function getPricingLogicIcon(): string
    {
        return $this->pricing_logic->icon();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Get a plain-language summary of the pricing logic.
     *
     * @param  string  $currency  The currency for display
     */
    public function getPricingSummary(string $currency = 'EUR'): string
    {
        return match ($this->pricing_logic) {
            BundlePricingLogic::SumComponents => 'Sum of component prices',
            BundlePricingLogic::FixedPrice => $this->fixed_price !== null
                ? 'Fixed price: '.$currency.' '.number_format((float) $this->fixed_price, 2)
                : 'Fixed price (not set)',
            BundlePricingLogic::PercentageOffSum => $this->percentage_off !== null
                ? number_format((float) $this->percentage_off, 0).'% off component sum'
                : 'Percentage off (not set)',
        };
    }

    /**
     * Get a detailed description of the bundle.
     *
     * @param  string  $currency  The currency for display
     */
    public function getDetailedDescription(string $currency = 'EUR'): string
    {
        $lines = [];
        $lines[] = 'Bundle: '.$this->name;
        $lines[] = 'SKU: '.$this->bundle_sku;
        $lines[] = 'Status: '.$this->getStatusLabel();
        $lines[] = 'Components: '.$this->getComponentsCount();
        $lines[] = '';
        $lines[] = 'Pricing: '.$this->getPricingSummary($currency);

        return implode("\n", $lines);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope query to draft bundles only.
     *
     * @param  Builder<Bundle>  $query
     * @return Builder<Bundle>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', BundleStatus::Draft);
    }

    /**
     * Scope query to active bundles only.
     *
     * @param  Builder<Bundle>  $query
     * @return Builder<Bundle>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BundleStatus::Active);
    }

    /**
     * Scope query to inactive bundles only.
     *
     * @param  Builder<Bundle>  $query
     * @return Builder<Bundle>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', BundleStatus::Inactive);
    }

    /**
     * Scope query by pricing logic.
     *
     * @param  Builder<Bundle>  $query
     * @return Builder<Bundle>
     */
    public function scopeWithPricingLogic(Builder $query, BundlePricingLogic $logic): Builder
    {
        return $query->where('pricing_logic', $logic);
    }
}
