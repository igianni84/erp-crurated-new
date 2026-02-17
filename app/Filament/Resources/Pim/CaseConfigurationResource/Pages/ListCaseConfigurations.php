<?php

namespace App\Filament\Resources\Pim\CaseConfigurationResource\Pages;

use App\Filament\Resources\Pim\CaseConfigurationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCaseConfigurations extends ListRecords
{
    protected static string $resource = CaseConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
