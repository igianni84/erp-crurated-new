<?php

namespace App\Enums\Customer;

/**
 * Enum MembershipStatus
 *
 * Lifecycle states for a customer's membership.
 * - Applied: Initial state when membership is requested
 * - UnderReview: Membership application is being reviewed
 * - Approved: Membership has been approved (active)
 * - Rejected: Membership application was rejected
 * - Suspended: Membership was suspended (can be reactivated)
 */
enum MembershipStatus: string
{
    case Applied = 'applied';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Applied => 'info',
            self::UnderReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Suspended => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Applied => 'heroicon-o-paper-airplane',
            self::UnderReview => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
            self::Suspended => 'heroicon-o-pause-circle',
        };
    }

    /**
     * Check if this status represents an active membership.
     */
    public function isActive(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Check if this status allows channel access.
     */
    public function allowsChannelAccess(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Get valid transitions from this status.
     *
     * @return array<MembershipStatus>
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::Applied => [self::UnderReview],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved => [self::Suspended],
            self::Rejected => [],
            self::Suspended => [self::Approved],
        };
    }

    /**
     * Check if transitioning to the given status is valid.
     */
    public function canTransitionTo(MembershipStatus $target): bool
    {
        return in_array($target, $this->validTransitions(), true);
    }
}
