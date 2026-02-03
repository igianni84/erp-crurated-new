<?php

namespace App\Filament\Resources\Pim\WineMasterResource\Pages;

use App\Filament\Resources\Pim\WineMasterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWineMaster extends EditRecord
{
    protected static string $resource = WineMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
