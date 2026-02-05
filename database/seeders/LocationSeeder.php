<?php

namespace Database\Seeders;

use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Models\Inventory\Location;
use Illuminate\Database\Seeder;

/**
 * LocationSeeder - Creates wine storage locations
 */
class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            // Main warehouses
            [
                'name' => 'Milano Central Warehouse',
                'location_type' => LocationType::MainWarehouse,
                'country' => 'Italy',
                'address' => 'Via della Logistica 15, 20138 Milano MI',
                'serialization_authorized' => true,
                'linked_wms_id' => 'WMS-MIL-001',
                'status' => LocationStatus::Active,
                'notes' => 'Main distribution hub for Northern Italy. Climate-controlled storage at 14°C.',
            ],
            [
                'name' => 'London Docklands Warehouse',
                'location_type' => LocationType::MainWarehouse,
                'country' => 'United Kingdom',
                'address' => 'Unit 7, Royal Victoria Dock, E16 1XL London',
                'serialization_authorized' => true,
                'linked_wms_id' => 'WMS-LON-001',
                'status' => LocationStatus::Active,
                'notes' => 'UK distribution center. Bonded warehouse for duty-free storage.',
            ],
            // Satellite warehouses
            [
                'name' => 'Roma Distribution Center',
                'location_type' => LocationType::SatelliteWarehouse,
                'country' => 'Italy',
                'address' => 'Via Tiburtina 852, 00159 Roma RM',
                'serialization_authorized' => true,
                'linked_wms_id' => 'WMS-ROM-001',
                'status' => LocationStatus::Active,
                'notes' => 'Central/Southern Italy distribution point.',
            ],
            [
                'name' => 'Geneva Free Port',
                'location_type' => LocationType::SatelliteWarehouse,
                'country' => 'Switzerland',
                'address' => 'Chemin du Grand-Puits 36, 1217 Meyrin',
                'serialization_authorized' => true,
                'linked_wms_id' => 'WMS-GVA-001',
                'status' => LocationStatus::Active,
                'notes' => 'Tax-free storage for high-value wine collections.',
            ],
            [
                'name' => 'Hong Kong Wine Vault',
                'location_type' => LocationType::SatelliteWarehouse,
                'country' => 'Hong Kong',
                'address' => 'Crown Wine Cellars, Deep Water Bay',
                'serialization_authorized' => true,
                'linked_wms_id' => 'WMS-HKG-001',
                'status' => LocationStatus::Active,
                'notes' => 'Asia Pacific hub. Temperature and humidity controlled.',
            ],
            // Third party storage
            [
                'name' => 'Octavian Vaults',
                'location_type' => LocationType::ThirdPartyStorage,
                'country' => 'United Kingdom',
                'address' => 'Corsham, Wiltshire',
                'serialization_authorized' => false,
                'linked_wms_id' => null,
                'status' => LocationStatus::Active,
                'notes' => 'Partner storage facility. Underground limestone caves.',
            ],
            [
                'name' => 'Berry Bros & Rudd Reserves',
                'location_type' => LocationType::ThirdPartyStorage,
                'country' => 'United Kingdom',
                'address' => '3 St James\'s Street, London SW1A 1EG',
                'serialization_authorized' => false,
                'linked_wms_id' => null,
                'status' => LocationStatus::Active,
                'notes' => 'Historic wine merchant storage.',
            ],
            // Consignee locations
            [
                'name' => 'Château Margaux Cellars',
                'location_type' => LocationType::Consignee,
                'country' => 'France',
                'address' => 'Château Margaux, 33460 Margaux',
                'serialization_authorized' => false,
                'linked_wms_id' => null,
                'status' => LocationStatus::Active,
                'notes' => 'Producer consignment - wines held at château.',
            ],
            [
                'name' => 'Tenuta San Guido',
                'location_type' => LocationType::Consignee,
                'country' => 'Italy',
                'address' => 'Località Capanne 27, 57022 Bolgheri LI',
                'serialization_authorized' => false,
                'linked_wms_id' => null,
                'status' => LocationStatus::Active,
                'notes' => 'Sassicaia producer consignment.',
            ],
            // Event locations
            [
                'name' => 'Vinitaly Event Space',
                'location_type' => LocationType::EventLocation,
                'country' => 'Italy',
                'address' => 'Viale del Lavoro 8, 37135 Verona VR',
                'serialization_authorized' => false,
                'linked_wms_id' => null,
                'status' => LocationStatus::Active,
                'notes' => 'Annual Vinitaly wine fair venue.',
            ],
            [
                'name' => 'Palazzo Versace Tasting Room',
                'location_type' => LocationType::EventLocation,
                'country' => 'Italy',
                'address' => 'Via Gesù 12, 20121 Milano MI',
                'serialization_authorized' => false,
                'linked_wms_id' => null,
                'status' => LocationStatus::Active,
                'notes' => 'Premium tasting event venue in Milan.',
            ],
            // Inactive location
            [
                'name' => 'Paris Warehouse (Closed)',
                'location_type' => LocationType::SatelliteWarehouse,
                'country' => 'France',
                'address' => 'Zone Industrielle, 93200 Saint-Denis',
                'serialization_authorized' => false,
                'linked_wms_id' => null,
                'status' => LocationStatus::Inactive,
                'notes' => 'Closed in 2024. All inventory transferred to Geneva.',
            ],
        ];

        foreach ($locations as $locationData) {
            Location::firstOrCreate(
                ['name' => $locationData['name']],
                $locationData
            );
        }
    }
}
