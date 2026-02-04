<?php

namespace App\Enums\Customer;

/**
 * Enum ChannelScope
 *
 * Defines the operational channel scope for an Account.
 */
enum ChannelScope: string
{
    case B2C = 'b2c';
    case B2B = 'b2b';
    case Club = 'club';

    /**
     * Get the human-readable label for this channel scope.
     */
    public function label(): string
    {
        return match ($this) {
            self::B2C => 'B2C',
            self::B2B => 'B2B',
            self::Club => 'Club',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::B2C => 'success',
            self::B2B => 'info',
            self::Club => 'warning',
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
            self::Club => 'heroicon-o-user-group',
        };
    }
}
