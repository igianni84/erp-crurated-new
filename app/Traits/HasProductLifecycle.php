<?php

namespace App\Traits;

use App\Enums\ProductLifecycleStatus;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Trait HasProductLifecycle
 *
 * Provides product lifecycle status management for PIM models.
 * Includes status transitions, scopes, validation, and sensitive field handling.
 *
 * Usage:
 *   1. Add `use HasProductLifecycle;` in your model
 *   2. Add migration column: $table->string('lifecycle_status')->default('draft');
 *   3. Add to $casts: 'lifecycle_status' => ProductLifecycleStatus::class
 *   4. Add 'lifecycle_status' to $fillable
 *   5. Optionally define SENSITIVE_FIELDS constant for auto-review trigger
 */
trait HasProductLifecycle
{
    /**
     * Fields that trigger automatic status change to in_review when modified on published products.
     * Override this in your model if needed.
     *
     * @var list<string>
     */
    protected static array $defaultSensitiveFields = [
        'name',
        'vintage_year',
        'wine_master_id',
        'alcohol_percentage',
    ];

    /**
     * Initialize the trait.
     * Sets default lifecycle_status if not specified.
     */
    public function initializeHasProductLifecycle(): void
    {
        if (! isset($this->attributes['lifecycle_status'])) {
            $this->attributes['lifecycle_status'] = ProductLifecycleStatus::Draft->value;
        }
    }

    /**
     * Boot the trait.
     * Register model events for lifecycle management.
     */
    public static function bootHasProductLifecycle(): void
    {
        static::updating(function ($model): void {
            $model->handleSensitiveFieldChanges();
        });
    }

    /**
     * Get sensitive fields for this model.
     *
     * @return list<string>
     */
    public function getSensitiveFields(): array
    {
        /** @phpstan-ignore-next-line */
        if (defined('static::SENSITIVE_FIELDS')) {
            /** @var list<string> */
            return static::SENSITIVE_FIELDS;
        }

        return self::$defaultSensitiveFields;
    }

    /**
     * Handle sensitive field changes on published products.
     * Automatically moves to in_review if sensitive fields are modified.
     */
    protected function handleSensitiveFieldChanges(): void
    {
        if ($this->lifecycle_status !== ProductLifecycleStatus::Published) {
            return;
        }

        $sensitiveFields = $this->getSensitiveFields();
        $dirty = $this->getDirty();

        // Check if any sensitive field has changed
        foreach ($sensitiveFields as $field) {
            if (array_key_exists($field, $dirty)) {
                $this->lifecycle_status = ProductLifecycleStatus::InReview;

                return;
            }
        }
    }

    /**
     * Scope to query by specific status.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithLifecycleStatus(Builder $query, ProductLifecycleStatus $status): Builder
    {
        return $query->where('lifecycle_status', $status);
    }

    /**
     * Scope to query draft products.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('lifecycle_status', ProductLifecycleStatus::Draft);
    }

    /**
     * Scope to query products in review.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInReview(Builder $query): Builder
    {
        return $query->where('lifecycle_status', ProductLifecycleStatus::InReview);
    }

    /**
     * Scope to query approved products.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('lifecycle_status', ProductLifecycleStatus::Approved);
    }

    /**
     * Scope to query published products.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('lifecycle_status', ProductLifecycleStatus::Published);
    }

    /**
     * Scope to query archived products.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('lifecycle_status', ProductLifecycleStatus::Archived);
    }

    /**
     * Scope to exclude archived products.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('lifecycle_status', '!=', ProductLifecycleStatus::Archived);
    }

    /**
     * Check if the model is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->lifecycle_status === ProductLifecycleStatus::Draft;
    }

    /**
     * Check if the model is in review.
     */
    public function isInReview(): bool
    {
        return $this->lifecycle_status === ProductLifecycleStatus::InReview;
    }

    /**
     * Check if the model is approved.
     */
    public function isApproved(): bool
    {
        return $this->lifecycle_status === ProductLifecycleStatus::Approved;
    }

    /**
     * Check if the model is published.
     */
    public function isPublished(): bool
    {
        return $this->lifecycle_status === ProductLifecycleStatus::Published;
    }

    /**
     * Check if the model is archived.
     */
    public function isArchived(): bool
    {
        return $this->lifecycle_status === ProductLifecycleStatus::Archived;
    }

    /**
     * Check if the model is editable.
     */
    public function isEditable(): bool
    {
        return $this->lifecycle_status->isEditable();
    }

    /**
     * Check if transition to given status is allowed.
     */
    public function canTransitionTo(ProductLifecycleStatus $status): bool
    {
        return $this->lifecycle_status->canTransitionTo($status);
    }

    /**
     * Get allowed status transitions.
     *
     * @return list<ProductLifecycleStatus>
     */
    public function getAllowedTransitions(): array
    {
        return $this->lifecycle_status->allowedTransitions();
    }

    /**
     * Transition to a new status.
     *
     * @throws InvalidArgumentException if transition is not allowed
     */
    public function transitionTo(ProductLifecycleStatus $status): bool
    {
        if (! $this->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Cannot transition from {$this->lifecycle_status->value} to {$status->value}"
            );
        }

        $this->lifecycle_status = $status;

        return $this->save();
    }

    /**
     * Submit for review.
     */
    public function submitForReview(): bool
    {
        return $this->transitionTo(ProductLifecycleStatus::InReview);
    }

    /**
     * Approve the product.
     */
    public function approve(): bool
    {
        return $this->transitionTo(ProductLifecycleStatus::Approved);
    }

    /**
     * Reject the product (return to draft).
     */
    public function reject(): bool
    {
        return $this->transitionTo(ProductLifecycleStatus::Draft);
    }

    /**
     * Publish the product.
     */
    public function publish(): bool
    {
        return $this->transitionTo(ProductLifecycleStatus::Published);
    }

    /**
     * Archive the product.
     */
    public function archive(): bool
    {
        return $this->transitionTo(ProductLifecycleStatus::Archived);
    }
}
