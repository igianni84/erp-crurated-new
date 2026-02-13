<?php

namespace App\Filament\Resources\Pim\ProducerResource\Pages;

use App\Filament\Resources\Pim\ProducerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProducer extends EditRecord
{
    protected static string $resource = ProducerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
