<?php

namespace App\Enums\Commercial;

/**
 * Enum DiscountRuleType
 *
 * Types of reusable discount rules.
 * Defines the logic structure for how discounts are calculated.
 */
enum DiscountRuleType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case Tiered = 'tiered';
    case VolumeBased = 'volume_based';

    /**
     * Get the human-readable label for this discount rule type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Discount',
            self::FixedAmount => 'Fixed Amount Discount',
            self::Tiered => 'Tiered Discount',
            self::VolumeBased => 'Volume-Based Discount',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Percentage => 'success',
            self::FixedAmount => 'warning',
            self::Tiered => 'info',
            self::VolumeBased => 'primary',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Percentage => 'heroicon-o-receipt-percent',
            self::FixedAmount => 'heroicon-o-currency-euro',
            self::Tiered => 'heroicon-o-chart-bar',
            self::VolumeBased => 'heroicon-o-cube',
        };
    }

    /**
     * Get a short description of what this discount rule type does.
     */
    public function description(): string
    {
        return match ($this) {
            self::Percentage => 'Apply a percentage discount off the base price',
            self::FixedAmount => 'Subtract a fixed amount from the base price',
            self::Tiered => 'Apply different discounts based on price/quantity tiers',
            self::VolumeBased => 'Apply discounts based on order quantity thresholds',
        };
    }

    /**
     * Get an example of this discount type.
     */
    public function example(): string
    {
        return match ($this) {
            self::Percentage => '15% off',
            self::FixedAmount => '€10 off',
            self::Tiered => '10% for first tier, 15% for second tier',
            self::VolumeBased => '€10 off when qty >= 6',
        };
    }

    /**
     * Check if this discount type supports tiered logic.
     */
    public function supportsTiers(): bool
    {
        return match ($this) {
            self::Percentage, self::FixedAmount => false,
            self::Tiered, self::VolumeBased => true,
        };
    }

    /**
     * Check if this discount type requires a base value.
     */
    public function requiresValue(): bool
    {
        return match ($this) {
            self::Percentage, self::FixedAmount => true,
            self::Tiered, self::VolumeBased => false,
        };
    }

    /**
     * Get the value unit for display (%, currency symbol, etc.).
     */
    public function valueUnit(): string
    {
        return match ($this) {
            self::Percentage => '%',
            self::FixedAmount => 'currency',
            self::Tiered => 'tiers',
            self::VolumeBased => 'thresholds',
        };
    }
}
