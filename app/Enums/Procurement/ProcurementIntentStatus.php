<?php

namespace App\Enums\Procurement;

/**
 * Enum ProcurementIntentStatus
 *
 * Lifecycle statuses for procurement intents.
 * Transitions: draft -> approved -> executed -> closed
 */
enum ProcurementIntentStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Executed = 'executed';
    case Closed = 'closed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Executed => 'Executed',
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
            self::Approved => 'success',
            self::Executed => 'info',
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
            self::Approved => 'heroicon-o-check-circle',
            self::Executed => 'heroicon-o-play',
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
            self::Draft => [self::Approved],
            self::Approved => [self::Executed],
            self::Executed => [self::Closed],
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
     * Check if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Check if this status allows creating linked objects (PO, Bottling Instructions).
     */
    public function allowsLinkedObjectCreation(): bool
    {
        return in_array($this, [self::Approved, self::Executed], true);
    }
}
