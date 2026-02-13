<?php

namespace App\Filament\Resources\Pim\AppellationResource\Pages;

use App\Filament\Resources\Pim\AppellationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppellations extends ListRecords
{
    protected static string $resource = AppellationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
