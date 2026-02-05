<?php

namespace App\Enums\Commercial;

/**
 * Enum BundlePricingLogic
 *
 * Defines how the price of a Bundle is calculated.
 * Bundles can use component sum, fixed prices, or percentage discounts.
 */
enum BundlePricingLogic: string
{
    case SumComponents = 'sum_components';
    case FixedPrice = 'fixed_price';
    case PercentageOffSum = 'percentage_off_sum';

    /**
     * Get the human-readable label for this pricing logic.
     */
    public function label(): string
    {
        return match ($this) {
            self::SumComponents => 'Sum of Components',
            self::FixedPrice => 'Fixed Price',
            self::PercentageOffSum => 'Percentage Off Sum',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::SumComponents => 'info',
            self::FixedPrice => 'warning',
            self::PercentageOffSum => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::SumComponents => 'heroicon-o-calculator',
            self::FixedPrice => 'heroicon-o-currency-euro',
            self::PercentageOffSum => 'heroicon-o-receipt-percent',
        };
    }

    /**
     * Get a description of how this pricing logic works.
     */
    public function description(): string
    {
        return match ($this) {
            self::SumComponents => 'Bundle price equals the sum of all component prices',
            self::FixedPrice => 'Bundle has a manually set fixed price regardless of components',
            self::PercentageOffSum => 'Bundle price is the sum of components minus a percentage discount',
        };
    }

    /**
     * Get an example of this pricing logic.
     */
    public function example(): string
    {
        return match ($this) {
            self::SumComponents => 'Component A (€50) + Component B (€30) = €80',
            self::FixedPrice => 'Components total €80, Bundle price fixed at €65',
            self::PercentageOffSum => 'Components total €80, 10% off = €72',
        };
    }

    /**
     * Check if this pricing logic requires a fixed price value.
     */
    public function requiresFixedPrice(): bool
    {
        return $this === self::FixedPrice;
    }

    /**
     * Check if this pricing logic requires a percentage value.
     */
    public function requiresPercentage(): bool
    {
        return $this === self::PercentageOffSum;
    }

    /**
     * Check if this pricing logic is automatic (based on components).
     */
    public function isAutomatic(): bool
    {
        return $this === self::SumComponents;
    }
}
