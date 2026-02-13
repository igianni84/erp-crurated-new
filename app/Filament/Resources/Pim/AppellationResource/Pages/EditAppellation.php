<?php

namespace App\Filament\Resources\Pim\AppellationResource\Pages;

use App\Filament\Resources\Pim\AppellationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppellation extends EditRecord
{
    protected static string $resource = AppellationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
