<?php

namespace App\Filament\Resources\Pim\WineVariantResource\Pages;

use App\Filament\Resources\Pim\WineVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWineVariants extends ListRecords
{
    protected static string $resource = WineVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
