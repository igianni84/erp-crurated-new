<?php

namespace App\Enums\Finance;

/**
 * Enum RefundType
 *
 * The type of refund (full or partial).
 */
enum RefundType: string
{
    case Full = 'full';
    case Partial = 'partial';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Refund',
            self::Partial => 'Partial Refund',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Full => 'danger',
            self::Partial => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Full => 'heroicon-o-arrow-uturn-left',
            self::Partial => 'heroicon-o-arrow-path',
        };
    }
}
