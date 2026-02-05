<?php

namespace App\Enums\Customer;

/**
 * Enum ClubStatus
 *
 * Status of a Club in the system.
 */
enum ClubStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Ended => 'Ended',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Ended => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Suspended => 'heroicon-o-pause-circle',
            self::Ended => 'heroicon-o-x-circle',
        };
    }
}
