<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingOrders extends ListRecords
{
    protected static string $resource = ShippingOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Shipping Order')
                ->icon('heroicon-o-plus'),
        ];
    }
}
