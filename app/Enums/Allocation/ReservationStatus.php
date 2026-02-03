<?php

namespace App\Enums\Allocation;

/**
 * Enum ReservationStatus
 *
 * Lifecycle statuses for temporary reservations.
 * Transitions: active -> expired (automatic), active -> cancelled, active -> converted
 */
enum ReservationStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Converted = 'converted';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Converted => 'Converted',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'gray',
            self::Cancelled => 'danger',
            self::Converted => 'primary',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-clock',
            self::Expired => 'heroicon-o-x-mark',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Converted => 'heroicon-o-check-circle',
        };
    }

    /**
     * Check if this is a terminal status (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled, self::Converted], true);
    }

    /**
     * Check if the reservation is still active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Get allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Expired, self::Cancelled, self::Converted],
            self::Expired, self::Cancelled, self::Converted => [],
        };
    }

    /**
     * Check if transition to given status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
