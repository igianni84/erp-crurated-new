<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SellableSku extends Model
{
    use Auditable;
    use AuditLoggable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * Status field to track for audit status_change events.
     */
    public const AUDIT_TRACK_STATUS_FIELD = 'lifecycle_status';

    /**
     * Lifecycle status constants.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    /**
     * Source constants.
     */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LIV_EX = 'liv_ex';

    public const SOURCE_PRODUCER = 'producer';

    public const SOURCE_GENERATED = 'generated';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wine_variant_id',
        'format_id',
        'case_configuration_id',
        'sku_code',
        'barcode',
        'lifecycle_status',
        'is_intrinsic',
        'is_producer_original',
        'is_verified',
        'source',
        'notes',
        'is_composite',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_status' => 'string',
            'is_intrinsic' => 'boolean',
            'is_producer_original' => 'boolean',
            'is_verified' => 'boolean',
            'source' => 'string',
            'is_composite' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SellableSku $sku): void {
            if (empty($sku->sku_code)) {
                $sku->sku_code = $sku->generateSkuCode();
            }
        });
    }

    /**
     * Generate SKU code in format: {WINE_CODE}-{VINTAGE}-{FORMAT}-{CASE}
     * Example: SASS-2018-750-6OWC
     */
    public function generateSkuCode(): string
    {
        $wineVariant = $this->wineVariant ?? WineVariant::find($this->wine_variant_id);
        $format = $this->format ?? Format::find($this->format_id);
        $caseConfig = $this->caseConfiguration ?? CaseConfiguration::find($this->case_configuration_id);

        if (! $wineVariant || ! $format || ! $caseConfig) {
            return 'SKU-'.Str::random(8);
        }

        /** @var WineMaster $wineMaster */
        $wineMaster = $wineVariant->wineMaster;

        // Generate wine code from name (first 4 chars uppercase, alphanumeric only)
        $wineCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $wineMaster->name) ?: 'WINE', 0, 4));

        // Vintage year
        $vintage = $wineVariant->vintage_year;

        // Format volume in ml
        $formatCode = $format->volume_ml;

        // Case configuration code: {bottles}{case_type}
        /** @var 'owc'|'oc'|'none' $caseTypeValue */
        $caseTypeValue = $caseConfig->case_type;
        $caseType = match ($caseTypeValue) {
            'owc' => 'OWC',
            'oc' => 'OC',
            'none' => 'L',
        };
        $caseCode = $caseConfig->bottles_per_case.$caseType;

        return "{$wineCode}-{$vintage}-{$formatCode}-{$caseCode}";
    }

    /**
     * Get the wine variant that this SKU belongs to.
     *
     * @return BelongsTo<WineVariant, $this>
     */
    public function wineVariant(): BelongsTo
    {
        return $this->belongsTo(WineVariant::class);
    }

    /**
     * Get the format for this SKU.
     *
     * @return BelongsTo<Format, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /**
     * Get the case configuration for this SKU.
     *
     * @return BelongsTo<CaseConfiguration, $this>
     */
    public function caseConfiguration(): BelongsTo
    {
        return $this->belongsTo(CaseConfiguration::class);
    }

    /**
     * Get the estimated market prices for this SKU.
     *
     * @return HasMany<\App\Models\Commercial\EstimatedMarketPrice, $this>
     */
    public function estimatedMarketPrices(): HasMany
    {
        return $this->hasMany(\App\Models\Commercial\EstimatedMarketPrice::class);
    }

    /**
     * Get the offers for this SKU.
     *
     * @return HasMany<\App\Models\Commercial\Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(\App\Models\Commercial\Offer::class);
    }

    /**
     * Get the bundle components that reference this SKU.
     *
     * @return HasMany<\App\Models\Commercial\BundleComponent, $this>
     */
    public function bundleComponents(): HasMany
    {
        return $this->hasMany(\App\Models\Commercial\BundleComponent::class);
    }

    /**
     * Check if the SKU is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->lifecycle_status === self::STATUS_DRAFT;
    }

    /**
     * Check if the SKU is active.
     */
    public function isActive(): bool
    {
        return $this->lifecycle_status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the SKU is retired.
     */
    public function isRetired(): bool
    {
        return $this->lifecycle_status === self::STATUS_RETIRED;
    }

    /**
     * Retire the SKU.
     */
    public function retire(): void
    {
        $this->lifecycle_status = self::STATUS_RETIRED;
        $this->save();
    }

    /**
     * Reactivate a retired SKU.
     */
    public function reactivate(): void
    {
        $this->lifecycle_status = self::STATUS_ACTIVE;
        $this->save();
    }

    /**
     * Get the status label.
     */
    public function getStatusLabel(): string
    {
        /** @var 'draft'|'active'|'retired' $status */
        $status = $this->lifecycle_status ?? 'draft';

        return match ($status) {
            'draft' => 'Draft',
            'active' => 'Active',
            'retired' => 'Retired',
        };
    }

    /**
     * Get the status color.
     */
    public function getStatusColor(): string
    {
        /** @var 'draft'|'active'|'retired' $status */
        $status = $this->lifecycle_status ?? 'draft';

        return match ($status) {
            'draft' => 'gray',
            'active' => 'success',
            'retired' => 'danger',
        };
    }

    /**
     * Get the source label.
     */
    public function getSourceLabel(): string
    {
        /** @var 'manual'|'liv_ex'|'producer'|'generated' $source */
        $source = $this->source ?? 'manual';

        return match ($source) {
            'manual' => 'Manual',
            'liv_ex' => 'Liv-ex',
            'producer' => 'Producer',
            'generated' => 'Generated',
        };
    }

    /**
     * Get the source color.
     */
    public function getSourceColor(): string
    {
        /** @var 'manual'|'liv_ex'|'producer'|'generated' $source */
        $source = $this->source ?? 'manual';

        return match ($source) {
            'manual' => 'gray',
            'liv_ex' => 'info',
            'producer' => 'primary',
            'generated' => 'warning',
        };
    }

    /**
     * Get integrity flags summary.
     *
     * @return list<string>
     */
    public function getIntegrityFlags(): array
    {
        $flags = [];

        if ($this->is_intrinsic) {
            $flags[] = 'Intrinsic';
        }
        if ($this->is_producer_original) {
            $flags[] = 'Producer Original';
        }
        if ($this->is_verified) {
            $flags[] = 'Verified';
        }

        return $flags;
    }

    /**
     * Check if SKU has any integrity flags set.
     */
    public function hasIntegrityFlags(): bool
    {
        return $this->is_intrinsic || $this->is_producer_original || $this->is_verified;
    }

    /**
     * Get the composite items for this SKU (if it's a composite).
     *
     * @return HasMany<CompositeSkuItem, $this>
     */
    public function compositeItems(): HasMany
    {
        return $this->hasMany(CompositeSkuItem::class, 'composite_sku_id');
    }

    /**
     * Get the composite SKUs that contain this SKU as a component.
     *
     * @return HasMany<CompositeSkuItem, $this>
     */
    public function partOfComposites(): HasMany
    {
        return $this->hasMany(CompositeSkuItem::class, 'sellable_sku_id');
    }

    /**
     * Check if this SKU is a composite (bundle).
     */
    public function isComposite(): bool
    {
        return (bool) $this->is_composite;
    }

    /**
     * Check if this SKU is a component of another composite SKU.
     */
    public function isComponent(): bool
    {
        return $this->partOfComposites()->exists();
    }

    /**
     * Get the component SKUs for this composite.
     *
     * @return Collection<int, SellableSku>
     */
    public function getComponentSkus(): Collection
    {
        if (! $this->isComposite()) {
            return new Collection;
        }

        return $this->compositeItems()
            ->with('sellableSku')
            ->get()
            ->pluck('sellableSku');
    }

    /**
     * Check if all component SKUs are active.
     */
    public function hasAllActiveComponents(): bool
    {
        if (! $this->isComposite()) {
            return true;
        }

        $items = $this->compositeItems()->with('sellableSku')->get();

        if ($items->isEmpty()) {
            return false;
        }

        foreach ($items as $item) {
            /** @var SellableSku|null $component */
            $component = $item->sellableSku;
            if ($component === null || ! $component->isActive()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get inactive component SKUs.
     *
     * @return Collection<int, SellableSku>
     */
    public function getInactiveComponents(): Collection
    {
        if (! $this->isComposite()) {
            return new Collection;
        }

        return $this->compositeItems()
            ->with('sellableSku')
            ->get()
            ->filter(function (CompositeSkuItem $item): bool {
                /** @var SellableSku|null $component */
                $component = $item->sellableSku;

                return $component === null || ! $component->isActive();
            })
            ->pluck('sellableSku')
            ->filter();
    }

    /**
     * Get the total number of bottles in this composite.
     */
    public function getCompositeTotalBottles(): int
    {
        if (! $this->isComposite()) {
            return 0;
        }

        $total = 0;
        $items = $this->compositeItems()->with('sellableSku.caseConfiguration')->get();

        foreach ($items as $item) {
            /** @var SellableSku $component */
            $component = $item->sellableSku;
            /** @var CaseConfiguration $caseConfig */
            $caseConfig = $component->caseConfiguration;
            $total += $item->quantity * $caseConfig->bottles_per_case;
        }

        return $total;
    }

    /**
     * Get a summary description of the composite composition.
     */
    public function getCompositeDescription(): string
    {
        if (! $this->isComposite()) {
            return '';
        }

        $items = $this->compositeItems()->with('sellableSku')->get();

        if ($items->isEmpty()) {
            return 'No components defined';
        }

        $parts = [];
        foreach ($items as $item) {
            /** @var SellableSku $component */
            $component = $item->sellableSku;
            $parts[] = $item->quantity.'x '.$component->sku_code;
        }

        return implode(' + ', $parts);
    }

    /**
     * Validate that this composite can be activated.
     *
     * @return array<string, string> Validation errors keyed by field
     */
    public function validateCompositeForActivation(): array
    {
        $errors = [];

        if (! $this->isComposite()) {
            return $errors;
        }

        $items = $this->compositeItems()->with('sellableSku')->get();

        if ($items->isEmpty()) {
            $errors['components'] = 'A composite SKU must have at least one component.';

            return $errors;
        }

        $inactiveComponents = $this->getInactiveComponents();
        if ($inactiveComponents->isNotEmpty()) {
            $skuCodes = $inactiveComponents->pluck('sku_code')->implode(', ');
            $errors['components'] = "All component SKUs must be active. Inactive: {$skuCodes}";
        }

        return $errors;
    }

    /**
     * Override activate to check composite validation.
     */
    public function activate(): void
    {
        if ($this->isComposite()) {
            $errors = $this->validateCompositeForActivation();
            if (! empty($errors)) {
                throw new \RuntimeException(implode(' ', $errors));
            }
        }

        $this->lifecycle_status = self::STATUS_ACTIVE;
        $this->save();
    }
}
