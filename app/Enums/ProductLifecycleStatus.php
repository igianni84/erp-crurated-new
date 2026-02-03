<?php

namespace App\Enums;

/**
 * Enum ProductLifecycleStatus
 *
 * Lifecycle statuses for PIM products with approval workflow.
 * Transitions: draft → in_review → approved/rejected(draft) → published → archived
 */
enum ProductLifecycleStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Approved => 'info',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil',
            self::InReview => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check',
            self::Published => 'heroicon-o-check-circle',
            self::Archived => 'heroicon-o-archive-box',
        };
    }

    /**
     * Get allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview],
            self::InReview => [self::Approved, self::Draft], // Approved or Rejected (back to draft)
            self::Approved => [self::Published],
            self::Published => [self::Archived],
            self::Archived => [], // No transitions from archived
        };
    }

    /**
     * Check if transition to given status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if this status allows editing the record.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::InReview;
    }

    /**
     * Get action label for transitioning to this status.
     */
    public function transitionActionLabel(): string
    {
        return match ($this) {
            self::Draft => 'Reject',
            self::InReview => 'Submit for Review',
            self::Approved => 'Approve',
            self::Published => 'Publish',
            self::Archived => 'Archive',
        };
    }

    /**
     * Get confirmation message for transitioning to this status.
     */
    public function transitionConfirmation(): string
    {
        return match ($this) {
            self::Draft => 'Are you sure you want to reject this and return it to draft?',
            self::InReview => 'Are you sure you want to submit this for review?',
            self::Approved => 'Are you sure you want to approve this product?',
            self::Published => 'Are you sure you want to publish this product? This will make it visible.',
            self::Archived => 'Are you sure you want to archive this product? It will no longer be active.',
        };
    }
}
