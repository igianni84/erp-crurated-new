<?php

namespace App\Filament\Resources\Customer\ClubResource\Pages;

use App\Filament\Resources\Customer\ClubResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClubs extends ListRecords
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
