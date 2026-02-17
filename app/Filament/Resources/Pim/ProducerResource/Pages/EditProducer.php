<?php

namespace App\Filament\Resources\Pim\ProducerResource\Pages;

use App\Filament\Resources\Pim\ProducerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProducer extends EditRecord
{
    protected static string $resource = ProducerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
