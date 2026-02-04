<?php

namespace App\Enums\Customer;

/**
 * Enum CustomerStatus
 *
 * Status of a customer in the system.
 */
enum CustomerStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Prospect => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Closed => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Prospect => 'heroicon-o-clock',
            self::Active => 'heroicon-o-check-circle',
            self::Suspended => 'heroicon-o-pause-circle',
            self::Closed => 'heroicon-o-x-circle',
        };
    }
}
