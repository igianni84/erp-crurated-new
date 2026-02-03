<?php

namespace App\Models\Pim;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeDefinition extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Valid attribute types.
     *
     * @var list<string>
     */
    public const TYPES = [
        'text',
        'textarea',
        'number',
        'select',
        'multiselect',
        'boolean',
        'date',
        'json',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'attribute_group_id',
        'name',
        'code',
        'type',
        'options',
        'validation_rules',
        'is_required',
        'is_lockable_from_livex',
        'completeness_weight',
        'unit',
        'help_text',
        'placeholder',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'is_required' => 'boolean',
            'is_lockable_from_livex' => 'boolean',
            'completeness_weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the attribute group that owns this definition.
     *
     * @return BelongsTo<AttributeGroup, $this>
     */
    public function attributeGroup(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class);
    }

    /**
     * Get the attribute values for this definition.
     *
     * @return HasMany<AttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * Get the value for a specific wine variant.
     */
    public function getValueFor(WineVariant $wineVariant): ?AttributeValue
    {
        return $this->attributeValues()
            ->where('wine_variant_id', $wineVariant->id)
            ->first();
    }

    /**
     * Get human-readable label for the type.
     */
    public function getTypeLabel(): string
    {
        /** @var 'text'|'textarea'|'number'|'select'|'multiselect'|'boolean'|'date'|'json' $type */
        $type = $this->type;

        return match ($type) {
            'text' => 'Text',
            'textarea' => 'Text Area',
            'number' => 'Number',
            'select' => 'Dropdown',
            'multiselect' => 'Multi-select',
            'boolean' => 'Yes/No',
            'date' => 'Date',
            'json' => 'Key-Value',
        };
    }
}
