<?php

namespace App\Enums\Commercial;

/**
 * Enum PricingPolicyType
 *
 * Types of pricing policies for automated price generation.
 */
enum PricingPolicyType: string
{
    case CostPlusMargin = 'cost_plus_margin';
    case ReferencePriceBook = 'reference_price_book';
    case IndexBased = 'index_based';
    case FixedAdjustment = 'fixed_adjustment';
    case Rounding = 'rounding';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::CostPlusMargin => 'Cost + Margin',
            self::ReferencePriceBook => 'Reference Price Book',
            self::IndexBased => 'Index Based',
            self::FixedAdjustment => 'Fixed Adjustment',
            self::Rounding => 'Rounding',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::CostPlusMargin => 'success',
            self::ReferencePriceBook => 'info',
            self::IndexBased => 'warning',
            self::FixedAdjustment => 'primary',
            self::Rounding => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::CostPlusMargin => 'heroicon-o-calculator',
            self::ReferencePriceBook => 'heroicon-o-book-open',
            self::IndexBased => 'heroicon-o-chart-bar',
            self::FixedAdjustment => 'heroicon-o-adjustments-horizontal',
            self::Rounding => 'heroicon-o-arrows-pointing-in',
        };
    }
}
