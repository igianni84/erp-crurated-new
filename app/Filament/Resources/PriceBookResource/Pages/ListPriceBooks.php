<?php

namespace App\Filament\Resources\PriceBookResource\Pages;

use App\Filament\Resources\PriceBookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPriceBooks extends ListRecords
{
    protected static string $resource = PriceBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
