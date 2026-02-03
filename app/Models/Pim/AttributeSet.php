<?php

namespace App\Models\Pim;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeSet extends Model
{
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
        'code',
        'description',
        'sort_order',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the attribute groups for this set.
     *
     * @return HasMany<AttributeGroup, $this>
     */
    public function attributeGroups(): HasMany
    {
        return $this->hasMany(AttributeGroup::class)->orderBy('sort_order');
    }

    /**
     * Get the default attribute set.
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->first();
    }
}
