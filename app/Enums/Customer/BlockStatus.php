<?php

namespace App\Enums\Customer;

/**
 * Enum BlockStatus
 *
 * Status of an operational block.
 */
enum BlockStatus: string
{
    case Active = 'active';
    case Removed = 'removed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Removed => 'Removed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'danger',
            self::Removed => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-exclamation-triangle',
            self::Removed => 'heroicon-o-check-circle',
        };
    }
}
