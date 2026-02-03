<?php

namespace App\Filament\Resources\Pim\SellableSkuResource\Pages;

use App\Filament\Resources\Pim\SellableSkuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSellableSku extends EditRecord
{
    protected static string $resource = SellableSkuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
