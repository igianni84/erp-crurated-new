<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Activate the SKU.
     */
    public function activate(): void
    {
        $this->lifecycle_status = self::STATUS_ACTIVE;
        $this->save();
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
}
