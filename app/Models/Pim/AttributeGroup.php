<?php

namespace App\Models\Pim;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeGroup extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'attribute_set_id',
        'name',
        'code',
        'icon',
        'description',
        'sort_order',
        'is_collapsible',
        'is_collapsed_by_default',
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
            'is_collapsible' => 'boolean',
            'is_collapsed_by_default' => 'boolean',
        ];
    }

    /**
     * Get the attribute set that owns this group.
     *
     * @return BelongsTo<AttributeSet, $this>
     */
    public function attributeSet(): BelongsTo
    {
        return $this->belongsTo(AttributeSet::class);
    }

    /**
     * Get the attribute definitions for this group.
     *
     * @return HasMany<AttributeDefinition, $this>
     */
    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(AttributeDefinition::class)->orderBy('sort_order');
    }
}
