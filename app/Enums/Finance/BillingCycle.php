<?php

namespace App\Enums\Finance;

/**
 * Enum BillingCycle
 *
 * The billing cycle for subscriptions.
 */
enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annual = 'annual';

    /**
     * Get the human-readable label for this cycle.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Annual => 'Annual',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Monthly => 'gray',
            self::Quarterly => 'info',
            self::Annual => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Monthly => 'heroicon-o-calendar',
            self::Quarterly => 'heroicon-o-calendar-days',
            self::Annual => 'heroicon-o-calendar-date-range',
        };
    }

    /**
     * Get the number of months in this billing cycle.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Annual => 12,
        };
    }

    /**
     * Get the number of days (approximate) in this billing cycle.
     */
    public function approximateDays(): int
    {
        return match ($this) {
            self::Monthly => 30,
            self::Quarterly => 91,
            self::Annual => 365,
        };
    }
}
