<?php

namespace App\Models\Pim;

use App\Enums\DataSource;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property DataSource $source
 */
class AttributeValue extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wine_variant_id',
        'attribute_definition_id',
        'value',
        'source',
        'is_locked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'source' => DataSource::class,
        ];
    }

    /**
     * Get the wine variant that owns this value.
     *
     * @return BelongsTo<WineVariant, $this>
     */
    public function wineVariant(): BelongsTo
    {
        return $this->belongsTo(WineVariant::class);
    }

    /**
     * Get the attribute definition for this value.
     *
     * @return BelongsTo<AttributeDefinition, $this>
     */
    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class);
    }

    /**
     * Get the typed value based on the attribute definition type.
     */
    public function getTypedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        $definition = $this->attributeDefinition;
        if ($definition === null) {
            return $this->value;
        }

        return match ($definition->type) {
            'number' => is_numeric($this->value) ? (float) $this->value : null,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'multiselect' => json_decode($this->value, true) ?? [],
            'date' => $this->value,
            default => $this->value,
        };
    }

    /**
     * Set the value with proper encoding based on type.
     */
    public function setTypedValue(mixed $value): void
    {
        $definition = $this->attributeDefinition;
        if ($definition === null) {
            $this->value = is_array($value) ? json_encode($value) : (string) $value;

            return;
        }

        $this->value = match ($definition->type) {
            'json', 'multiselect' => is_array($value) ? json_encode($value) : $value,
            'boolean' => $value ? '1' : '0',
            'number' => $value !== null && $value !== '' ? (string) $value : null,
            default => $value !== '' ? (string) $value : null,
        };
    }

    /**
     * Check if the value is considered filled (not empty).
     */
    public function isFilled(): bool
    {
        if ($this->value === null || $this->value === '') {
            return false;
        }

        $definition = $this->attributeDefinition;
        if ($definition === null) {
            return true;
        }

        return match ($definition->type) {
            'json', 'multiselect' => ! empty(json_decode($this->value, true)),
            default => true,
        };
    }

    /**
     * Get the source label.
     */
    public function getSourceLabel(): string
    {
        return match ($this->source) {
            DataSource::LivEx => 'Liv-ex',
            DataSource::Manual => 'Manual',
        };
    }
}
