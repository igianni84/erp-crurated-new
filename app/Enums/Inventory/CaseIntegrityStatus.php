<?php

namespace App\Enums\Inventory;

/**
 * Enum CaseIntegrityStatus
 *
 * Integrity statuses for physical cases.
 */
enum CaseIntegrityStatus: string
{
    case Intact = 'intact';
    case Broken = 'broken';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Intact => 'Intact',
            self::Broken => 'Broken',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Intact => 'success',
            self::Broken => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Intact => 'heroicon-o-cube',
            self::Broken => 'heroicon-o-scissors',
        };
    }

    /**
     * Check if the case can be handled as a unit.
     */
    public function canHandleAsUnit(): bool
    {
        return $this === self::Intact;
    }
}
