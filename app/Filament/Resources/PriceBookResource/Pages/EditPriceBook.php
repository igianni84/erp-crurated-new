<?php

namespace App\Filament\Resources\PriceBookResource\Pages;

use App\Filament\Resources\PriceBookResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPriceBook extends EditRecord
{
    protected static string $resource = PriceBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
