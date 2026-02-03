<?php

namespace App\Enums\Allocation;

/**
 * Enum AllocationStatus
 *
 * Lifecycle statuses for allocations.
 * Transitions: draft -> active -> exhausted (automatic) -> closed
 *              active -> closed
 */
enum AllocationStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Closed = 'closed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Exhausted => 'Exhausted',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Exhausted => 'warning',
            self::Closed => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil',
            self::Active => 'heroicon-o-check-circle',
            self::Exhausted => 'heroicon-o-exclamation-circle',
            self::Closed => 'heroicon-o-x-circle',
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
            self::Draft => [self::Active],
            self::Active => [self::Exhausted, self::Closed],
            self::Exhausted => [self::Closed],
            self::Closed => [],
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
     * Check if this status allows editing constraints.
     */
    public function allowsConstraintEditing(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if this status allows consumption (selling).
     */
    public function allowsConsumption(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
