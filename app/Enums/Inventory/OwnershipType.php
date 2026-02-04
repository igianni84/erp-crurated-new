<?php

namespace App\Enums\Inventory;

/**
 * Enum OwnershipType
 *
 * Ownership types for inventory items.
 */
enum OwnershipType: string
{
    case CururatedOwned = 'crurated_owned';
    case InCustody = 'in_custody';
    case ThirdPartyOwned = 'third_party_owned';

    /**
     * Get the human-readable label for this ownership type.
     */
    public function label(): string
    {
        return match ($this) {
            self::CururatedOwned => 'Crurated Owned',
            self::InCustody => 'In Custody',
            self::ThirdPartyOwned => 'Third Party Owned',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::CururatedOwned => 'success',
            self::InCustody => 'warning',
            self::ThirdPartyOwned => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::CururatedOwned => 'heroicon-o-building-office',
            self::InCustody => 'heroicon-o-hand-raised',
            self::ThirdPartyOwned => 'heroicon-o-user-group',
        };
    }

    /**
     * Check if Crurated has full ownership rights.
     */
    public function hasFullOwnership(): bool
    {
        return $this === self::CururatedOwned;
    }

    /**
     * Check if the item can be consumed for events.
     */
    public function canConsumeForEvents(): bool
    {
        return $this === self::CururatedOwned;
    }
}
