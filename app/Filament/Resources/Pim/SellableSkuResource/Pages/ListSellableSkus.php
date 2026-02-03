<?php

namespace App\Filament\Resources\Pim\SellableSkuResource\Pages;

use App\Filament\Resources\Pim\SellableSkuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellableSkus extends ListRecords
{
    protected static string $resource = SellableSkuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
