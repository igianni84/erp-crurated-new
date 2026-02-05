<?php

namespace App\Enums\Procurement;

/**
 * Enum BottlingInstructionStatus
 *
 * Lifecycle statuses for bottling instructions.
 * Transitions: draft -> active -> executed
 */
enum BottlingInstructionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Executed = 'executed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Executed => 'Executed',
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
            self::Executed => 'info',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil',
            self::Active => 'heroicon-o-play',
            self::Executed => 'heroicon-o-check-badge',
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
            self::Active => [self::Executed],
            self::Executed => [],
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
        return $this === self::Executed;
    }

    /**
     * Check if preferences can still be collected.
     */
    public function allowsPreferenceCollection(): bool
    {
        return $this === self::Active;
    }
}
