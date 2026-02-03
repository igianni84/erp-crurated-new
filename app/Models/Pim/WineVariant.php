<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WineVariant extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

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
}
