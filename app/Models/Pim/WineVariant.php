<?php

namespace App\Models\Pim;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Traits\Auditable;
use App\Traits\AuditLoggable;
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
 * @property DataSource $data_source
 */
class WineVariant extends Model
{
    use Auditable;
    use AuditLoggable;
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
     * Status field to track for audit status_change events.
     */
    public const AUDIT_TRACK_STATUS_FIELD = 'lifecycle_status';

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
        'data_source',
        'lwin_code',
        'internal_code',
        'thumbnail_url',
        'description',
        'locked_fields',
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
            'data_source' => DataSource::class,
            'locked_fields' => 'array',
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
     * Get the attribute values for this wine variant.
     *
     * @return HasMany<AttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * Get the media for this wine variant.
     *
     * @return HasMany<ProductMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /**
     * Get Liv-ex media for this wine variant.
     *
     * @return HasMany<ProductMedia, $this>
     */
    public function livExMedia(): HasMany
    {
        return $this->hasMany(ProductMedia::class)
            ->where('source', 'liv_ex')
            ->orderBy('sort_order');
    }

    /**
     * Get manually uploaded media for this wine variant.
     *
     * @return HasMany<ProductMedia, $this>
     */
    public function manualMedia(): HasMany
    {
        return $this->hasMany(ProductMedia::class)
            ->where('source', 'manual')
            ->orderBy('sort_order');
    }

    /**
     * Get the primary image for this wine variant.
     */
    public function getPrimaryImage(): ?ProductMedia
    {
        return $this->media()
            ->where('type', 'image')
            ->where('is_primary', true)
            ->first();
    }

    /**
     * Check if this wine variant has a primary image.
     */
    public function hasPrimaryImage(): bool
    {
        return $this->getPrimaryImage() !== null;
    }

    /**
     * Get or create an attribute value for a specific attribute definition.
     */
    public function getOrCreateAttributeValue(AttributeDefinition $definition): AttributeValue
    {
        return $this->attributeValues()->firstOrCreate(
            ['attribute_definition_id' => $definition->id],
            [
                'value' => null,
                'source' => $this->data_source ?? DataSource::Manual,
                'is_locked' => false,
            ]
        );
    }

    /**
     * Get the attribute value for a specific attribute code.
     */
    public function getAttributeValueByCode(string $code): ?AttributeValue
    {
        return $this->attributeValues()
            ->whereHas('attributeDefinition', fn ($q) => $q->where('code', $code))
            ->first();
    }

    /**
     * Calculate completeness percentage including dynamic attributes.
     */
    public function getDynamicCompletenessPercentage(): int
    {
        $totalWeight = 0;
        $completedWeight = 0;

        // Calculate from static COMPLETENESS_FIELDS
        foreach (self::COMPLETENESS_FIELDS as $config) {
            $totalWeight += $config['weight'];
        }

        foreach (self::COMPLETENESS_FIELDS as $field => $config) {
            if ($this->isFieldComplete($field)) {
                $completedWeight += $config['weight'];
            }
        }

        // Calculate from dynamic attributes
        $attributeSet = AttributeSet::getDefault();
        if ($attributeSet !== null) {
            foreach ($attributeSet->attributeGroups as $group) {
                foreach ($group->attributeDefinitions as $definition) {
                    $totalWeight += $definition->completeness_weight;
                    $value = $this->attributeValues()
                        ->where('attribute_definition_id', $definition->id)
                        ->first();
                    if ($value !== null && $value->isFilled()) {
                        $completedWeight += $definition->completeness_weight;
                    }
                }
            }
        }

        if ($totalWeight === 0) {
            return 100;
        }

        return (int) round(($completedWeight / $totalWeight) * 100);
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
            'has_primary_image' => $this->hasPrimaryImage(),
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

    /**
     * Blocking issue definition: field, tab, message, severity.
     *
     * @var array<string, array{tab: string, message: string, priority: int}>
     */
    public const BLOCKING_ISSUES_RULES = [
        'wine_master_id' => [
            'tab' => 'core_info',
            'message' => 'Wine Master is required',
            'priority' => 1,
        ],
        'vintage_year' => [
            'tab' => 'core_info',
            'message' => 'Vintage year is required',
            'priority' => 2,
        ],
        'has_sellable_skus' => [
            'tab' => 'sellable_skus',
            'message' => 'At least one Sellable SKU is required for publication',
            'priority' => 3,
        ],
        'has_primary_image' => [
            'tab' => 'media',
            'message' => 'A primary image is required for publication',
            'priority' => 4,
        ],
    ];

    /**
     * Warning issue definition: field, tab, message.
     *
     * @var array<string, array{tab: string, message: string, priority: int}>
     */
    public const WARNING_RULES = [
        'alcohol_percentage' => [
            'tab' => 'core_info',
            'message' => 'Alcohol percentage is recommended',
            'priority' => 1,
        ],
        'drinking_window_start' => [
            'tab' => 'core_info',
            'message' => 'Drinking window start year is recommended',
            'priority' => 2,
        ],
        'drinking_window_end' => [
            'tab' => 'core_info',
            'message' => 'Drinking window end year is recommended',
            'priority' => 3,
        ],
        'critic_scores' => [
            'tab' => 'attributes',
            'message' => 'Critic scores improve product discoverability',
            'priority' => 4,
        ],
        'production_notes' => [
            'tab' => 'attributes',
            'message' => 'Production notes provide valuable context',
            'priority' => 5,
        ],
    ];

    /**
     * Get blocking issues that prevent publication.
     *
     * @return list<array{field: string, tab: string, message: string, priority: int}>
     */
    public function getBlockingIssues(): array
    {
        $issues = [];

        foreach (self::BLOCKING_ISSUES_RULES as $field => $config) {
            if (! $this->isFieldComplete($field)) {
                $issues[] = [
                    'field' => $field,
                    'tab' => $config['tab'],
                    'message' => $config['message'],
                    'priority' => $config['priority'],
                ];
            }
        }

        // Sort by priority
        usort($issues, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $issues;
    }

    /**
     * Get warnings (non-blocking issues).
     *
     * @return list<array{field: string, tab: string, message: string, priority: int}>
     */
    public function getWarnings(): array
    {
        $warnings = [];

        foreach (self::WARNING_RULES as $field => $config) {
            if (! $this->isFieldComplete($field)) {
                $warnings[] = [
                    'field' => $field,
                    'tab' => $config['tab'],
                    'message' => $config['message'],
                    'priority' => $config['priority'],
                ];
            }
        }

        // Sort by priority
        usort($warnings, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $warnings;
    }

    /**
     * Check if the wine variant has any blocking issues.
     */
    public function hasBlockingIssues(): bool
    {
        return count($this->getBlockingIssues()) > 0;
    }

    /**
     * Check if the wine variant can be published (no blocking issues).
     */
    public function canPublish(): bool
    {
        return ! $this->hasBlockingIssues();
    }

    /**
     * Check if a specific field is locked (imported from Liv-ex and not overridable).
     */
    public function isFieldLocked(string $field): bool
    {
        if ($this->data_source !== DataSource::LivEx) {
            return false;
        }

        $lockedFields = $this->locked_fields ?? [];

        return in_array($field, $lockedFields, true);
    }

    /**
     * Get the list of locked fields.
     *
     * @return list<string>
     */
    public function getLockedFields(): array
    {
        if ($this->data_source !== DataSource::LivEx) {
            return [];
        }

        return $this->locked_fields ?? [];
    }

    /**
     * Check if the product was imported from Liv-ex.
     */
    public function isFromLivEx(): bool
    {
        return $this->data_source === DataSource::LivEx;
    }

    /**
     * Set locked fields from Liv-ex import.
     *
     * @param  list<string>  $fields
     */
    public function setLockedFields(array $fields): void
    {
        $this->setAttribute('locked_fields', $fields);
    }

    /**
     * Unlock a specific field (admin/manager override).
     */
    public function unlockField(string $field): void
    {
        /** @var list<string> $lockedFields */
        $lockedFields = $this->getAttribute('locked_fields') ?? [];
        $filtered = [];
        foreach ($lockedFields as $f) {
            if ($f !== $field) {
                $filtered[] = $f;
            }
        }
        $this->setAttribute('locked_fields', $filtered);
    }
}
