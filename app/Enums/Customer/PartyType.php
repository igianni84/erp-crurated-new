<?php

namespace App\Enums\Customer;

/**
 * Enum PartyType
 *
 * Types of parties in the system.
 */
enum PartyType: string
{
    case Individual = 'individual';
    case LegalEntity = 'legal_entity';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::LegalEntity => 'Legal Entity',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Individual => 'info',
            self::LegalEntity => 'primary',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Individual => 'heroicon-o-user',
            self::LegalEntity => 'heroicon-o-building-office',
        };
    }
}
