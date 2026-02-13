<?php

namespace App\Models\Pim;

use App\Traits\Auditable;
use App\Traits\AuditLoggable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
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
        'iso_code',
        'iso_code_3',
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
     * @return HasMany<Region, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
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
}
