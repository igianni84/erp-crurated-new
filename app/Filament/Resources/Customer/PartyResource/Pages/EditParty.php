<?php

namespace App\Filament\Resources\Customer\PartyResource\Pages;

use App\Filament\Resources\Customer\PartyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParty extends EditRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
