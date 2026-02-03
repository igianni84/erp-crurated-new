<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Trait Auditable
 *
 * Provides automatic audit logging for model changes.
 * Tracks who created and last updated the model, with timestamps.
 *
 * Usage:
 *   1. Add `use Auditable;` in your model
 *   2. Add migration columns:
 *      - $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
 *      - $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
 */
trait Auditable
{
    /**
     * Boot the trait.
     * Automatically sets created_by and updated_by when saving.
     */
    public static function bootAuditable(): void
    {
        static::creating(function ($model): void {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model): void {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Get the user who created this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the user who last updated this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
