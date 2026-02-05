<?php

namespace App\Enums\Commercial;

/**
 * Enum ExecutionType
 *
 * Defines how a PricingPolicy execution was triggered.
 */
enum ExecutionType: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case DryRun = 'dry_run';

    /**
     * Get the human-readable label for this execution type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Scheduled => 'Scheduled',
            self::DryRun => 'Dry Run',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Manual => 'primary',
            self::Scheduled => 'success',
            self::DryRun => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Manual => 'heroicon-o-hand-raised',
            self::Scheduled => 'heroicon-o-clock',
            self::DryRun => 'heroicon-o-beaker',
        };
    }
}
