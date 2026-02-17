<?php

namespace App\Filament\Resources\Pim\SellableSkuResource\Pages;

use App\Filament\Resources\Pim\SellableSkuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSellableSku extends EditRecord
{
    protected static string $resource = SellableSkuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
