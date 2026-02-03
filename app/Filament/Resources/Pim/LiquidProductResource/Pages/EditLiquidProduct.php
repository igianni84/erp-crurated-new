<?php

namespace App\Filament\Resources\Pim\LiquidProductResource\Pages;

use App\Filament\Resources\Pim\LiquidProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLiquidProduct extends EditRecord
{
    protected static string $resource = LiquidProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
