<?php

namespace App\Enums\Procurement;

/**
 * Enum SourcingModel
 *
 * Defines the sourcing model for procurement.
 */
enum SourcingModel: string
{
    case Purchase = 'purchase';
    case PassiveConsignment = 'passive_consignment';
    case ThirdPartyCustody = 'third_party_custody';

    /**
     * Get the human-readable label for this sourcing model.
     */
    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
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
            self::Purchase => 'success',
            self::PassiveConsignment => 'info',
            self::ThirdPartyCustody => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Purchase => 'heroicon-o-shopping-cart',
            self::PassiveConsignment => 'heroicon-o-archive-box',
            self::ThirdPartyCustody => 'heroicon-o-building-office',
        };
    }

    /**
     * Get the description for this sourcing model.
     */
    public function description(): string
    {
        return match ($this) {
            self::Purchase => 'Ownership transfers to us on delivery',
            self::PassiveConsignment => 'We hold the product in custody without ownership',
            self::ThirdPartyCustody => 'No ownership transfer, third party holds product',
        };
    }

    /**
     * Check if this sourcing model implies ownership transfer.
     */
    public function impliesOwnershipTransfer(): bool
    {
        return $this === self::Purchase;
    }
}
