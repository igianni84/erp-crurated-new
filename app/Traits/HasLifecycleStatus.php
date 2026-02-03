<?php

namespace App\Traits;

use App\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasLifecycleStatus
 *
 * Provides lifecycle status management for Eloquent models.
 * Includes status transitions, scopes, and validation.
 *
 * Usage:
 *   1. Add `use HasLifecycleStatus;` in your model
 *   2. Add migration column: $table->string('status')->default('draft');
 *   3. Add to $casts: 'status' => LifecycleStatus::class
 */
trait HasLifecycleStatus
{
    /**
     * Initialize the trait.
     * Sets default status if not specified.
     */
    public function initializeHasLifecycleStatus(): void
    {
        if (! isset($this->attributes['status'])) {
            $this->attributes['status'] = LifecycleStatus::Draft->value;
        }
    }

    /**
     * Scope to query only active records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LifecycleStatus::Active);
    }

    /**
     * Scope to query only draft records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', LifecycleStatus::Draft);
    }

    /**
     * Scope to query only inactive records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', LifecycleStatus::Inactive);
    }

    /**
     * Scope to query only archived records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', LifecycleStatus::Archived);
    }

    /**
     * Scope to exclude archived records (common default filter).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('status', '!=', LifecycleStatus::Archived);
    }

    /**
     * Check if the model is active.
     */
    public function isActive(): bool
    {
        return $this->status === LifecycleStatus::Active;
    }

    /**
     * Check if the model is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === LifecycleStatus::Draft;
    }

    /**
     * Check if the model is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === LifecycleStatus::Inactive;
    }

    /**
     * Check if the model is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === LifecycleStatus::Archived;
    }

    /**
     * Activate the model.
     */
    public function activate(): bool
    {
        $this->status = LifecycleStatus::Active;

        return $this->save();
    }

    /**
     * Deactivate the model.
     */
    public function deactivate(): bool
    {
        $this->status = LifecycleStatus::Inactive;

        return $this->save();
    }

    /**
     * Archive the model.
     */
    public function archive(): bool
    {
        $this->status = LifecycleStatus::Archived;

        return $this->save();
    }

    /**
     * Restore to draft status.
     */
    public function restoreToDraft(): bool
    {
        $this->status = LifecycleStatus::Draft;

        return $this->save();
    }
}
