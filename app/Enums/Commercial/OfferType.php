<?php

namespace App\Enums\Commercial;

/**
 * Enum OfferType
 *
 * Types of commercial offers.
 */
enum OfferType: string
{
    case Standard = 'standard';
    case Promotion = 'promotion';
    case Bundle = 'bundle';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Promotion => 'Promotion',
            self::Bundle => 'Bundle',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Standard => 'primary',
            self::Promotion => 'success',
            self::Bundle => 'info',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Standard => 'heroicon-o-tag',
            self::Promotion => 'heroicon-o-gift',
            self::Bundle => 'heroicon-o-squares-2x2',
        };
    }
}
