<?php

namespace App\Filament\Resources\Inventory\CaseResource\Pages;

use App\Filament\Resources\Inventory\CaseResource;
use Filament\Resources\Pages\ListRecords;

class ListCases extends ListRecords
{
    protected static string $resource = CaseResource::class;

    protected function getHeaderActions(): array
    {
        // No create action - cases are created through serialization process
        return [];
    }
}
