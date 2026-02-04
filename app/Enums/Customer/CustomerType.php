<?php

namespace App\Enums\Customer;

/**
 * Enum CustomerType
 *
 * Type of customer in the system.
 */
enum CustomerType: string
{
    case B2C = 'b2c';
    case B2B = 'b2b';
    case Partner = 'partner';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::B2C => 'B2C',
            self::B2B => 'B2B',
            self::Partner => 'Partner',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::B2C => 'info',
            self::B2B => 'primary',
            self::Partner => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::B2C => 'heroicon-o-user',
            self::B2B => 'heroicon-o-building-office',
            self::Partner => 'heroicon-o-user-group',
        };
    }
}
