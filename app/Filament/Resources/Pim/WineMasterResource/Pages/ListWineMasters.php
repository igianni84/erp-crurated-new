<?php

namespace App\Filament\Resources\Pim\WineMasterResource\Pages;

use App\Filament\Resources\Pim\WineMasterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWineMasters extends ListRecords
{
    protected static string $resource = WineMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
