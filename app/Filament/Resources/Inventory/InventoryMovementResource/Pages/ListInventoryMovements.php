<?php

namespace App\Filament\Resources\Inventory\InventoryMovementResource\Pages;

use App\Filament\Resources\Inventory\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        // No create action - movements are created through services
        return [];
    }
}
