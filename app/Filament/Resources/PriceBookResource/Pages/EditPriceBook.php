<?php

namespace App\Filament\Resources\PriceBookResource\Pages;

use App\Filament\Resources\PriceBookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPriceBook extends EditRecord
{
    protected static string $resource = PriceBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
