<?php

namespace App\Enums\Procurement;

/**
 * Enum InboundStatus
 *
 * Lifecycle statuses for inbound records.
 * Transitions: recorded -> routed -> completed
 */
enum InboundStatus: string
{
    case Recorded = 'recorded';
    case Routed = 'routed';
    case Completed = 'completed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Routed => 'Routed',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Recorded => 'gray',
            self::Routed => 'warning',
            self::Completed => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Recorded => 'heroicon-o-clipboard-document-check',
            self::Routed => 'heroicon-o-arrow-path',
            self::Completed => 'heroicon-o-check-circle',
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
            self::Recorded => [self::Routed],
            self::Routed => [self::Completed],
            self::Completed => [],
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
     * Check if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Check if hand-off to Module B is allowed.
     */
    public function allowsHandOff(): bool
    {
        return $this === self::Completed;
    }
}
