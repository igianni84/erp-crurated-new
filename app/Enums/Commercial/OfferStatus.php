<?php

namespace App\Enums\Commercial;

/**
 * Enum OfferStatus
 *
 * Lifecycle statuses for Offers.
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Paused => 'warning',
            self::Expired => 'info',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Active => 'heroicon-o-check-circle',
            self::Paused => 'heroicon-o-pause-circle',
            self::Expired => 'heroicon-o-clock',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }
}
