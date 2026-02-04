<?php

namespace App\Enums\Finance;

/**
 * Enum SubscriptionStatus
 *
 * Lifecycle states for subscriptions.
 *
 * Allowed transitions:
 * - active → suspended, cancelled
 * - suspended → active, cancelled
 * - cancelled → terminal
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Suspended => 'heroicon-o-pause-circle',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get the allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Suspended, self::Cancelled],
            self::Suspended => [self::Active, self::Cancelled],
            self::Cancelled => [],
        };
    }

    /**
     * Check if transition to target status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::Cancelled;
    }

    /**
     * Check if billing is allowed in this status.
     */
    public function allowsBilling(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if the customer has access to membership benefits.
     */
    public function hasAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if this status indicates a block condition.
     */
    public function isBlocked(): bool
    {
        return match ($this) {
            self::Suspended, self::Cancelled => true,
            self::Active => false,
        };
    }
}
