<?php

namespace App\Filament\Resources\Allocation\AllocationResource\Pages;

use App\Filament\Resources\Allocation\AllocationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAllocation extends EditRecord
{
    protected static string $resource = AllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
