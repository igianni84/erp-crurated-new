<?php

namespace App\Enums\Commercial;

/**
 * Enum PriceSource
 *
 * Defines how a price in a PriceBookEntry was set.
 */
enum PriceSource: string
{
    case Manual = 'manual';
    case PolicyGenerated = 'policy_generated';

    /**
     * Get the human-readable label for this source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::PolicyGenerated => 'Policy Generated',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Manual => 'primary',
            self::PolicyGenerated => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Manual => 'heroicon-o-pencil',
            self::PolicyGenerated => 'heroicon-o-cog-6-tooth',
        };
    }
}
