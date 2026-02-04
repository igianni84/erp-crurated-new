<?php

namespace App\Enums\Customer;

/**
 * Enum PartyRoleType
 *
 * Types of roles that a party can have in the system.
 * A party can have multiple roles simultaneously.
 */
enum PartyRoleType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Producer = 'producer';
    case Partner = 'partner';

    /**
     * Get the human-readable label for this role type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Supplier => 'Supplier',
            self::Producer => 'Producer',
            self::Partner => 'Partner',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Customer => 'success',
            self::Supplier => 'warning',
            self::Producer => 'info',
            self::Partner => 'primary',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Customer => 'heroicon-o-user-group',
            self::Supplier => 'heroicon-o-truck',
            self::Producer => 'heroicon-o-beaker',
            self::Partner => 'heroicon-o-handshake',
        };
    }
}
