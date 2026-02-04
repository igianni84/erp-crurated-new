<?php

namespace App\Enums\Inventory;

/**
 * Enum ConsumptionReason
 *
 * Reasons for consuming inventory.
 */
enum ConsumptionReason: string
{
    case EventConsumption = 'event_consumption';
    case Sampling = 'sampling';
    case DamageWriteoff = 'damage_writeoff';

    /**
     * Get the human-readable label for this reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::EventConsumption => 'Event Consumption',
            self::Sampling => 'Sampling',
            self::DamageWriteoff => 'Damage Write-off',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::EventConsumption => 'primary',
            self::Sampling => 'info',
            self::DamageWriteoff => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::EventConsumption => 'heroicon-o-calendar',
            self::Sampling => 'heroicon-o-beaker',
            self::DamageWriteoff => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if this consumption is planned/intentional.
     */
    public function isPlannedConsumption(): bool
    {
        return match ($this) {
            self::EventConsumption, self::Sampling => true,
            self::DamageWriteoff => false,
        };
    }
}
