<?php

namespace App\Filament\Resources\Pim\LiquidProductResource\Pages;

use App\Filament\Resources\Pim\LiquidProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLiquidProduct extends EditRecord
{
    protected static string $resource = LiquidProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
