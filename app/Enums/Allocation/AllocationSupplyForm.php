<?php

namespace App\Enums\Allocation;

/**
 * Enum AllocationSupplyForm
 *
 * Defines the supply form of an allocation.
 */
enum AllocationSupplyForm: string
{
    case Bottled = 'bottled';
    case Liquid = 'liquid';

    /**
     * Get the human-readable label for this supply form.
     */
    public function label(): string
    {
        return match ($this) {
            self::Bottled => 'Bottled',
            self::Liquid => 'Liquid',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Bottled => 'success',
            self::Liquid => 'info',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Bottled => 'heroicon-o-beaker',
            self::Liquid => 'heroicon-o-cube-transparent',
        };
    }
}
