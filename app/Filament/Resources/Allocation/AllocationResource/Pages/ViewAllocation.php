<?php

namespace App\Filament\Resources\Allocation\AllocationResource\Pages;

use App\Filament\Resources\Allocation\AllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAllocation extends ViewRecord
{
    protected static string $resource = AllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
