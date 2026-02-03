<?php

namespace App\Enums\Commercial;

/**
 * Enum BenefitType
 *
 * Types of benefits that can be applied to an offer.
 * Defines how the final price is calculated from the base price.
 */
enum BenefitType: string
{
    case None = 'none';
    case PercentageDiscount = 'percentage_discount';
    case FixedDiscount = 'fixed_discount';
    case FixedPrice = 'fixed_price';

    /**
     * Get the human-readable label for this benefit type.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'None (Price Book Price)',
            self::PercentageDiscount => 'Percentage Discount',
            self::FixedDiscount => 'Fixed Discount',
            self::FixedPrice => 'Fixed Price',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::PercentageDiscount => 'success',
            self::FixedDiscount => 'warning',
            self::FixedPrice => 'info',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::None => 'heroicon-o-minus-circle',
            self::PercentageDiscount => 'heroicon-o-receipt-percent',
            self::FixedDiscount => 'heroicon-o-currency-euro',
            self::FixedPrice => 'heroicon-o-banknotes',
        };
    }

    /**
     * Get a short description of what this benefit type does.
     */
    public function description(): string
    {
        return match ($this) {
            self::None => 'Use the Price Book price without any modifications',
            self::PercentageDiscount => 'Apply a percentage discount off the base price',
            self::FixedDiscount => 'Subtract a fixed amount from the base price',
            self::FixedPrice => 'Override the base price with a fixed amount',
        };
    }

    /**
     * Check if this benefit type requires a value.
     */
    public function requiresValue(): bool
    {
        return match ($this) {
            self::None => false,
            self::PercentageDiscount, self::FixedDiscount, self::FixedPrice => true,
        };
    }

    /**
     * Get the value unit for display (%, currency symbol, etc.).
     */
    public function valueUnit(): string
    {
        return match ($this) {
            self::None => '',
            self::PercentageDiscount => '%',
            self::FixedDiscount => 'currency',
            self::FixedPrice => 'currency',
        };
    }
}
