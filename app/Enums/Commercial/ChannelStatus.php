<?php

namespace App\Enums\Commercial;

/**
 * Enum ChannelStatus
 *
 * Lifecycle statuses for commercial channels.
 */
enum ChannelStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
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
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-x-circle',
        };
    }
}
