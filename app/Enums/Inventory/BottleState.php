<?php

namespace App\Enums\Inventory;

/**
 * Enum BottleState
 *
 * Physical states of serialized bottles.
 */
enum BottleState: string
{
    case Stored = 'stored';
    case ReservedForPicking = 'reserved_for_picking';
    case Shipped = 'shipped';
    case Consumed = 'consumed';
    case Destroyed = 'destroyed';
    case Missing = 'missing';
    case MisSerialized = 'mis_serialized';

    /**
     * Get the human-readable label for this state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Stored => 'Stored',
            self::ReservedForPicking => 'Reserved for Picking',
            self::Shipped => 'Shipped',
            self::Consumed => 'Consumed',
            self::Destroyed => 'Destroyed',
            self::Missing => 'Missing',
            self::MisSerialized => 'Mis-serialized',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Stored => 'success',
            self::ReservedForPicking => 'warning',
            self::Shipped => 'info',
            self::Consumed, self::Destroyed => 'gray',
            self::Missing => 'danger',
            self::MisSerialized => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Stored => 'heroicon-o-archive-box',
            self::ReservedForPicking => 'heroicon-o-hand-raised',
            self::Shipped => 'heroicon-o-truck',
            self::Consumed => 'heroicon-o-check',
            self::Destroyed => 'heroicon-o-x-circle',
            self::Missing => 'heroicon-o-question-mark-circle',
            self::MisSerialized => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Check if the bottle is available for fulfillment.
     */
    public function isAvailableForFulfillment(): bool
    {
        return $this === self::Stored;
    }

    /**
     * Check if the bottle is still physically present.
     */
    public function isPhysicallyPresent(): bool
    {
        return match ($this) {
            self::Stored, self::ReservedForPicking => true,
            self::Shipped, self::Consumed, self::Destroyed, self::Missing, self::MisSerialized => false,
        };
    }

    /**
     * Check if this is a terminal state (cannot change).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Consumed, self::Destroyed, self::Missing, self::MisSerialized => true,
            self::Stored, self::ReservedForPicking, self::Shipped => false,
        };
    }
}
