<?php

namespace App\Enums\Commercial;

/**
 * Enum PricingPolicyInputSource
 *
 * Input sources for pricing policy calculations.
 */
enum PricingPolicyInputSource: string
{
    case Cost = 'cost';
    case Emp = 'emp';
    case PriceBook = 'price_book';
    case ExternalIndex = 'external_index';

    /**
     * Get the human-readable label for this source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cost => 'Cost',
            self::Emp => 'EMP',
            self::PriceBook => 'Price Book',
            self::ExternalIndex => 'External Index',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Cost => 'primary',
            self::Emp => 'info',
            self::PriceBook => 'success',
            self::ExternalIndex => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cost => 'heroicon-o-currency-dollar',
            self::Emp => 'heroicon-o-globe-alt',
            self::PriceBook => 'heroicon-o-book-open',
            self::ExternalIndex => 'heroicon-o-arrow-trending-up',
        };
    }
}
