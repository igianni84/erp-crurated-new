<?php

namespace App\Filament\Resources\Allocation\AllocationResource\Pages;

use App\Filament\Resources\Allocation\AllocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllocations extends ListRecords
{
    protected static string $resource = AllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
