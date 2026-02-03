<?php

namespace App\Enums\Commercial;

/**
 * Enum OfferVisibility
 *
 * Visibility levels for commercial offers.
 */
enum OfferVisibility: string
{
    case Public = 'public';
    case Restricted = 'restricted';

    /**
     * Get the human-readable label for this visibility.
     */
    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Restricted => 'Restricted',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Restricted => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Public => 'heroicon-o-globe-alt',
            self::Restricted => 'heroicon-o-lock-closed',
        };
    }
}
