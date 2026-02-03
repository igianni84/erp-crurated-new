<?php

namespace App\Enums;

/**
 * Enum DataSource
 *
 * Source of product data - either imported from Liv-ex or entered manually.
 */
enum DataSource: string
{
    case LivEx = 'liv_ex';
    case Manual = 'manual';

    /**
     * Get the human-readable label for this source.
     */
    public function label(): string
    {
        return match ($this) {
            self::LivEx => 'Liv-ex',
            self::Manual => 'Manual',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::LivEx => 'info',
            self::Manual => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::LivEx => 'heroicon-o-cloud-arrow-down',
            self::Manual => 'heroicon-o-pencil-square',
        };
    }
}
