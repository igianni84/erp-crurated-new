<?php

namespace App\Enums\Allocation;

/**
 * Enum AllocationSourceType
 *
 * Defines the source type of an allocation.
 */
enum AllocationSourceType: string
{
    case ProducerAllocation = 'producer_allocation';
    case OwnedStock = 'owned_stock';
    case PassiveConsignment = 'passive_consignment';
    case ThirdPartyCustody = 'third_party_custody';

    /**
     * Get the human-readable label for this source type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProducerAllocation => 'Producer Allocation',
            self::OwnedStock => 'Owned Stock',
            self::PassiveConsignment => 'Passive Consignment',
            self::ThirdPartyCustody => 'Third Party Custody',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::ProducerAllocation => 'primary',
            self::OwnedStock => 'success',
            self::PassiveConsignment => 'warning',
            self::ThirdPartyCustody => 'info',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::ProducerAllocation => 'heroicon-o-building-office',
            self::OwnedStock => 'heroicon-o-archive-box',
            self::PassiveConsignment => 'heroicon-o-arrows-right-left',
            self::ThirdPartyCustody => 'heroicon-o-building-library',
        };
    }
}
