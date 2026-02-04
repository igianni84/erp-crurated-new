<?php

namespace App\Filament\Resources\Customer\PartyResource\Pages;

use App\Filament\Resources\Customer\PartyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParty extends ViewRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
