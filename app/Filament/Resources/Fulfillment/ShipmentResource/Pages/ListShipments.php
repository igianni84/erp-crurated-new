<?php

namespace App\Filament\Resources\Fulfillment\ShipmentResource\Pages;

use App\Filament\Resources\Fulfillment\ShipmentResource;
use Filament\Resources\Pages\ListRecords;

class ListShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        // No create action - shipments are created from ShippingOrders
        return [];
    }
}
