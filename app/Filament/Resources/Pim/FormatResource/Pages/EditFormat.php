<?php

namespace App\Filament\Resources\Pim\FormatResource\Pages;

use App\Filament\Resources\Pim\FormatResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFormat extends EditRecord
{
    protected static string $resource = FormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
