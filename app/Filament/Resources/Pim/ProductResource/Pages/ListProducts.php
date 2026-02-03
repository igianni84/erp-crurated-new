<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Filament\Resources\Pim\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Product')
                ->icon('heroicon-o-plus')
                ->url(ProductResource::getUrl('choose-category')),
        ];
    }
}
