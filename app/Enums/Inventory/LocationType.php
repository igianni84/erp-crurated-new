<?php

namespace App\Enums\Inventory;

/**
 * Enum LocationType
 *
 * Types of physical locations where wine can be stored.
 */
enum LocationType: string
{
    case MainWarehouse = 'main_warehouse';
    case SatelliteWarehouse = 'satellite_warehouse';
    case Consignee = 'consignee';
    case ThirdPartyStorage = 'third_party_storage';
    case EventLocation = 'event_location';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MainWarehouse => 'Main Warehouse',
            self::SatelliteWarehouse => 'Satellite Warehouse',
            self::Consignee => 'Consignee',
            self::ThirdPartyStorage => 'Third Party Storage',
            self::EventLocation => 'Event Location',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::MainWarehouse => 'success',
            self::SatelliteWarehouse => 'info',
            self::Consignee => 'warning',
            self::ThirdPartyStorage => 'gray',
            self::EventLocation => 'primary',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::MainWarehouse => 'heroicon-o-building-office-2',
            self::SatelliteWarehouse => 'heroicon-o-building-storefront',
            self::Consignee => 'heroicon-o-user-group',
            self::ThirdPartyStorage => 'heroicon-o-cube',
            self::EventLocation => 'heroicon-o-calendar',
        };
    }

    /**
     * Check if this location type typically supports serialization.
     */
    public function typicallySupportsSerialiation(): bool
    {
        return match ($this) {
            self::MainWarehouse, self::SatelliteWarehouse => true,
            self::Consignee, self::ThirdPartyStorage, self::EventLocation => false,
        };
    }
}
