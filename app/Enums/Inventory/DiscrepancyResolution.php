<?php

namespace App\Enums\Inventory;

/**
 * Enum DiscrepancyResolution
 *
 * Resolution types for inventory discrepancies.
 */
enum DiscrepancyResolution: string
{
    case Shortage = 'shortage';
    case Overage = 'overage';
    case Damage = 'damage';
    case Other = 'other';

    /**
     * Get the human-readable label for this resolution.
     */
    public function label(): string
    {
        return match ($this) {
            self::Shortage => 'Shortage',
            self::Overage => 'Overage',
            self::Damage => 'Damage',
            self::Other => 'Other',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Shortage => 'danger',
            self::Overage => 'warning',
            self::Damage => 'danger',
            self::Other => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Shortage => 'heroicon-o-minus-circle',
            self::Overage => 'heroicon-o-plus-circle',
            self::Damage => 'heroicon-o-exclamation-triangle',
            self::Other => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Check if this resolution reduces expected quantity.
     */
    public function reducesExpectedQuantity(): bool
    {
        return match ($this) {
            self::Shortage, self::Damage => true,
            self::Overage, self::Other => false,
        };
    }

    /**
     * Check if this resolution increases received quantity.
     */
    public function increasesReceivedQuantity(): bool
    {
        return $this === self::Overage;
    }
}
