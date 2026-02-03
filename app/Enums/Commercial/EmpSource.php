<?php

namespace App\Enums\Commercial;

/**
 * Enum EmpSource
 *
 * Source types for Estimated Market Prices.
 */
enum EmpSource: string
{
    case Livex = 'livex';
    case Internal = 'internal';
    case Composite = 'composite';

    /**
     * Get the human-readable label for this source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Livex => 'Liv-ex',
            self::Internal => 'Internal',
            self::Composite => 'Composite',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Livex => 'info',
            self::Internal => 'primary',
            self::Composite => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Livex => 'heroicon-o-globe-alt',
            self::Internal => 'heroicon-o-building-office',
            self::Composite => 'heroicon-o-squares-2x2',
        };
    }
}
