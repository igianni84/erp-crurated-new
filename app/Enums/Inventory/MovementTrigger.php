<?php

namespace App\Enums\Inventory;

/**
 * Enum MovementTrigger
 *
 * Sources that can trigger inventory movements.
 */
enum MovementTrigger: string
{
    case WmsEvent = 'wms_event';
    case ErpOperator = 'erp_operator';
    case SystemAutomatic = 'system_automatic';

    /**
     * Get the human-readable label for this trigger.
     */
    public function label(): string
    {
        return match ($this) {
            self::WmsEvent => 'WMS Event',
            self::ErpOperator => 'ERP Operator',
            self::SystemAutomatic => 'System Automatic',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::WmsEvent => 'info',
            self::ErpOperator => 'success',
            self::SystemAutomatic => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::WmsEvent => 'heroicon-o-cloud-arrow-down',
            self::ErpOperator => 'heroicon-o-user',
            self::SystemAutomatic => 'heroicon-o-cog-6-tooth',
        };
    }

    /**
     * Check if this trigger requires human operator.
     */
    public function requiresOperator(): bool
    {
        return $this === self::ErpOperator;
    }

    /**
     * Check if this trigger is external (from WMS).
     */
    public function isExternal(): bool
    {
        return $this === self::WmsEvent;
    }
}
