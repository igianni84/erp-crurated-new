<?php

namespace App\Enums\Commercial;

/**
 * Enum PricingPolicyStatus
 *
 * Lifecycle statuses for Pricing Policies.
 */
enum PricingPolicyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
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
            self::Paused => 'warning',
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
            self::Paused => 'heroicon-o-pause-circle',
            self::Archived => 'heroicon-o-archive-box',
        };
    }
}
