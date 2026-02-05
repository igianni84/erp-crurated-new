<?php

namespace App\Enums\Customer;

/**
 * Enum AccountStatus
 *
 * Status of an account in the system.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'danger',
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
        };
    }
}
