<?php

namespace App\Enums\Commercial;

/**
 * Enum ExecutionCadence
 *
 * Execution cadences for pricing policies.
 */
enum ExecutionCadence: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case EventTriggered = 'event_triggered';

    /**
     * Get the human-readable label for this cadence.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Scheduled => 'Scheduled',
            self::EventTriggered => 'Event Triggered',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::Scheduled => 'info',
            self::EventTriggered => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Manual => 'heroicon-o-hand-raised',
            self::Scheduled => 'heroicon-o-calendar',
            self::EventTriggered => 'heroicon-o-bolt',
        };
    }
}
