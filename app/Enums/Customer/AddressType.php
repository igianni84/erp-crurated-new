<?php

namespace App\Enums\Customer;

/**
 * Enum AddressType
 *
 * Type of address (billing or shipping).
 */
enum AddressType: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Billing => 'Billing',
            self::Shipping => 'Shipping',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Billing => 'primary',
            self::Shipping => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Billing => 'heroicon-o-credit-card',
            self::Shipping => 'heroicon-o-truck',
        };
    }
}
