<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiquidProduct extends Model
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
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wine_variant_id',
        'allowed_equivalent_units',
        'allowed_final_formats',
        'allowed_case_configurations',
        'bottling_constraints',
        'serialization_required',
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
            'allowed_equivalent_units' => 'array',
            'allowed_final_formats' => 'array',
            'allowed_case_configurations' => 'array',
            'bottling_constraints' => 'array',
            'serialization_required' => 'boolean',
            'lifecycle_status' => 'string',
        ];
    }

    /**
     * Get the wine variant that this liquid product belongs to.
     *
     * @return BelongsTo<WineVariant, $this>
     */
    public function wineVariant(): BelongsTo
    {
        return $this->belongsTo(WineVariant::class);
    }
}
