<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'appellation',
        'classification',
        'country',
        'region',
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

    /**
     * Get the wine variants for this wine master.
     *
     * @return HasMany<WineVariant, $this>
     */
    public function wineVariants(): HasMany
    {
        return $this->hasMany(WineVariant::class);
    }
}
