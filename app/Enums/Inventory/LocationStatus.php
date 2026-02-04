<?php

namespace App\Enums\Inventory;

/**
 * Enum LocationStatus
 *
 * Operational statuses for physical locations.
 */
enum LocationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Suspended => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-pause-circle',
            self::Suspended => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Check if the location can receive inventory in this status.
     */
    public function canReceiveInventory(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if the location can dispatch inventory in this status.
     */
    public function canDispatchInventory(): bool
    {
        return $this === self::Active;
    }
}
