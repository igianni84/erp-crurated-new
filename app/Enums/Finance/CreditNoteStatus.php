<?php

namespace App\Enums\Finance;

/**
 * Enum CreditNoteStatus
 *
 * Lifecycle states for credit notes.
 *
 * Allowed transitions:
 * - draft → issued
 * - issued → applied
 * - applied → terminal
 */
enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Applied = 'applied';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Applied => 'Applied',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'info',
            self::Applied => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Issued => 'heroicon-o-document-text',
            self::Applied => 'heroicon-o-check-circle',
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
            self::Draft => [self::Issued],
            self::Issued => [self::Applied],
            self::Applied => [],
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
        return $this === self::Applied;
    }

    /**
     * Check if this status allows editing.
     */
    public function allowsEditing(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if Xero sync should be triggered.
     */
    public function requiresXeroSync(): bool
    {
        return $this === self::Issued;
    }
}
