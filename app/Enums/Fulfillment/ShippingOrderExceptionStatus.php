<?php

namespace App\Enums\Fulfillment;

/**
 * Enum ShippingOrderExceptionStatus
 *
 * Status for shipping order exceptions.
 *
 * - active: Exception is active and needs resolution
 * - resolved: Exception has been resolved
 */
enum ShippingOrderExceptionStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'danger',
            self::Resolved => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-exclamation-circle',
            self::Resolved => 'heroicon-o-check-circle',
        };
    }
}
