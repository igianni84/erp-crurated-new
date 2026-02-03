<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Trait HasUuid
 *
 * Provides UUID as primary key functionality for Eloquent models.
 * When using this trait, the model's primary key will be a UUID string
 * instead of an auto-incrementing integer.
 *
 * Usage:
 *   1. Add `use HasUuid;` in your model
 *   2. Create migration with `$table->uuid('id')->primary();`
 *   3. Remove any `$table->id();` from migration
 */
trait HasUuid
{
    /**
     * Boot the trait.
     * Automatically generates a UUID when creating a new model.
     */
    public static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Initialize the trait.
     * Configures the model to use UUID as primary key.
     */
    public function initializeHasUuid(): void
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
