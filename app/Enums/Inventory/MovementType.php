<?php

namespace App\Enums\Inventory;

/**
 * Enum MovementType
 *
 * Types of inventory movements.
 */
enum MovementType: string
{
    case InternalTransfer = 'internal_transfer';
    case ConsignmentPlacement = 'consignment_placement';
    case ConsignmentReturn = 'consignment_return';
    case EventShipment = 'event_shipment';
    case EventConsumption = 'event_consumption';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::InternalTransfer => 'Internal Transfer',
            self::ConsignmentPlacement => 'Consignment Placement',
            self::ConsignmentReturn => 'Consignment Return',
            self::EventShipment => 'Event Shipment',
            self::EventConsumption => 'Event Consumption',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::InternalTransfer => 'info',
            self::ConsignmentPlacement => 'warning',
            self::ConsignmentReturn => 'success',
            self::EventShipment => 'primary',
            self::EventConsumption => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::InternalTransfer => 'heroicon-o-arrows-right-left',
            self::ConsignmentPlacement => 'heroicon-o-arrow-up-tray',
            self::ConsignmentReturn => 'heroicon-o-arrow-down-tray',
            self::EventShipment => 'heroicon-o-truck',
            self::EventConsumption => 'heroicon-o-fire',
        };
    }

    /**
     * Check if this movement type changes custody.
     */
    public function changesCustody(): bool
    {
        return match ($this) {
            self::ConsignmentPlacement, self::ConsignmentReturn => true,
            self::InternalTransfer, self::EventShipment, self::EventConsumption => false,
        };
    }

    /**
     * Check if this movement type reduces available inventory.
     */
    public function reducesAvailableInventory(): bool
    {
        return $this === self::EventConsumption;
    }
}
