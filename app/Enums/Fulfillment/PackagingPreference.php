<?php

namespace App\Enums\Fulfillment;

/**
 * Enum PackagingPreference
 *
 * Customer packaging preferences for shipping orders.
 *
 * - Loose: Individual bottles shipped separately
 * - Cases: Bottles assembled into composite cases for shipping
 * - PreserveCases: Preserve original wooden cases (OWC) when available
 */
enum PackagingPreference: string
{
    case Loose = 'loose';
    case Cases = 'cases';
    case PreserveCases = 'preserve_cases';

    /**
     * Get the human-readable label for this preference.
     */
    public function label(): string
    {
        return match ($this) {
            self::Loose => 'Loose Bottles',
            self::Cases => 'Cases',
            self::PreserveCases => 'Preserve Cases',
        };
    }

    /**
     * Get the description for UI display.
     */
    public function description(): string
    {
        return match ($this) {
            self::Loose => 'Bottles shipped individually',
            self::Cases => 'Bottles assembled in composite cases',
            self::PreserveCases => 'Preserve original wooden cases (OWC) when available',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Loose => 'gray',
            self::Cases => 'info',
            self::PreserveCases => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Loose => 'heroicon-o-cube',
            self::Cases => 'heroicon-o-squares-2x2',
            self::PreserveCases => 'heroicon-o-archive-box',
        };
    }

    /**
     * Check if this preference may cause shipment delays.
     */
    public function mayDelayShipment(): bool
    {
        return $this === self::PreserveCases;
    }

    /**
     * Get a warning message if applicable.
     */
    public function getWarningMessage(): ?string
    {
        if ($this === self::PreserveCases) {
            return 'May delay shipment if case not available';
        }

        return null;
    }
}
