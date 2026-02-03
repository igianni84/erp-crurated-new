<?php

namespace App\Enums\Commercial;

/**
 * Enum ChannelType
 *
 * Types of commercial channels for sales.
 */
enum ChannelType: string
{
    case B2c = 'b2c';
    case B2b = 'b2b';
    case PrivateClub = 'private_club';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::B2c => 'B2C',
            self::B2b => 'B2B',
            self::PrivateClub => 'Private Club',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::B2c => 'info',
            self::B2b => 'primary',
            self::PrivateClub => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::B2c => 'heroicon-o-shopping-cart',
            self::B2b => 'heroicon-o-building-office',
            self::PrivateClub => 'heroicon-o-user-group',
        };
    }
}
