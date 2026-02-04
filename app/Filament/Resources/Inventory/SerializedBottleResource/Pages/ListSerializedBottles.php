<?php

namespace App\Filament\Resources\Inventory\SerializedBottleResource\Pages;

use App\Filament\Resources\Inventory\SerializedBottleResource;
use Filament\Resources\Pages\ListRecords;

class ListSerializedBottles extends ListRecords
{
    protected static string $resource = SerializedBottleResource::class;

    protected function getHeaderActions(): array
    {
        // SerializedBottles are created through the serialization process, not manually
        return [];
    }
}
