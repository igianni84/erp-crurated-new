<?php

namespace App\Enums\Commercial;

/**
 * Enum BundleStatus
 *
 * Status values for Bundle entities.
 * Controls the lifecycle of a commercial bundle.
 */
enum BundleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
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
            self::Draft => 'gray',
            self::Active => 'success',
            self::Inactive => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-pause-circle',
        };
    }

    /**
     * Get a short description of this status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Bundle is being configured and is not yet available for sale',
            self::Active => 'Bundle is available for sale and can be used in Offers',
            self::Inactive => 'Bundle is no longer available for new Offers',
        };
    }
}
