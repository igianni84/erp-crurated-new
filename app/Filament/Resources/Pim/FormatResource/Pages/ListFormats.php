<?php

namespace App\Filament\Resources\Pim\FormatResource\Pages;

use App\Filament\Resources\Pim\FormatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormats extends ListRecords
{
    protected static string $resource = FormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
