<?php

namespace App\Filament\Resources\Pim\CaseConfigurationResource\Pages;

use App\Filament\Resources\Pim\CaseConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCaseConfiguration extends EditRecord
{
    protected static string $resource = CaseConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
