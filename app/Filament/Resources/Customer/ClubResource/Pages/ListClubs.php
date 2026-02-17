<?php

namespace App\Filament\Resources\Customer\ClubResource\Pages;

use App\Filament\Resources\Customer\ClubResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClubs extends ListRecords
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
