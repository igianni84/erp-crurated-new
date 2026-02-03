<?php

namespace App\Filament\Resources\Pim\WineVariantResource\Pages;

use App\Filament\Resources\Pim\WineVariantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWineVariant extends EditRecord
{
    protected static string $resource = WineVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
