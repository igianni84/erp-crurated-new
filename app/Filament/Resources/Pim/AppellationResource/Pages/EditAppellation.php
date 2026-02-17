<?php

namespace App\Filament\Resources\Pim\AppellationResource\Pages;

use App\Filament\Resources\Pim\AppellationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAppellation extends EditRecord
{
    protected static string $resource = AppellationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
