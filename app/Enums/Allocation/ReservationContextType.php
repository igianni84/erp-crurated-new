<?php

namespace App\Enums\Allocation;

/**
 * Enum ReservationContextType
 *
 * Context types for temporary reservations.
 * Indicates why the reservation was created.
 */
enum ReservationContextType: string
{
    case Checkout = 'checkout';
    case Negotiation = 'negotiation';
    case ManualHold = 'manual_hold';

    /**
     * Get the human-readable label for this context type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Checkout => 'Checkout',
            self::Negotiation => 'Negotiation',
            self::ManualHold => 'Manual Hold',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Checkout => 'primary',
            self::Negotiation => 'warning',
            self::ManualHold => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Checkout => 'heroicon-o-shopping-cart',
            self::Negotiation => 'heroicon-o-chat-bubble-left-right',
            self::ManualHold => 'heroicon-o-hand-raised',
        };
    }
}
