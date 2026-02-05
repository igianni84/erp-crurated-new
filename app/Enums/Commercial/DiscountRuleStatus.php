<?php

namespace App\Enums\Commercial;

/**
 * Enum DiscountRuleStatus
 *
 * Status values for DiscountRule entities.
 * Controls whether a discount rule can be used by Offers.
 */
enum DiscountRuleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-pause-circle',
        };
    }

    /**
     * Get a short description of this status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Active => 'Discount rule can be used by Offers',
            self::Inactive => 'Discount rule cannot be assigned to new Offers',
        };
    }
}
