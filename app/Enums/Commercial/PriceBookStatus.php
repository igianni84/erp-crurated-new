<?php

namespace App\Enums\Commercial;

/**
 * Enum PriceBookStatus
 *
 * Lifecycle statuses for Price Books.
 */
enum PriceBookStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Archived = 'archived';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Archived => 'Archived',
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
            self::Expired => 'warning',
            self::Archived => 'danger',
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
            self::Expired => 'heroicon-o-clock',
            self::Archived => 'heroicon-o-archive-box',
        };
    }
}
