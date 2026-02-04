<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource\Pages;

use App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource;
use Filament\Resources\Pages\ListRecords;

class ListShippingOrderExceptions extends ListRecords
{
    protected static string $resource = ShippingOrderExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
