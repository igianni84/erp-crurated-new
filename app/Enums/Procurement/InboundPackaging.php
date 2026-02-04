<?php

namespace App\Enums\Procurement;

/**
 * Enum InboundPackaging
 *
 * Defines the packaging type for inbound shipments.
 */
enum InboundPackaging: string
{
    case Cases = 'cases';
    case Loose = 'loose';
    case Mixed = 'mixed';

    /**
     * Get the human-readable label for this packaging type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cases => 'Cases',
            self::Loose => 'Loose',
            self::Mixed => 'Mixed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Cases => 'success',
            self::Loose => 'warning',
            self::Mixed => 'info',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cases => 'heroicon-o-cube',
            self::Loose => 'heroicon-o-squares-2x2',
            self::Mixed => 'heroicon-o-rectangle-stack',
        };
    }

    /**
     * Get the description for this packaging type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Cases => 'Wine packed in original cases',
            self::Loose => 'Individual bottles without case packaging',
            self::Mixed => 'Combination of cases and loose bottles',
        };
    }
}
