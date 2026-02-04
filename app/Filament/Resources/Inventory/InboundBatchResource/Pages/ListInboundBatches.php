<?php

namespace App\Filament\Resources\Inventory\InboundBatchResource\Pages;

use App\Filament\Resources\Inventory\InboundBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListInboundBatches extends ListRecords
{
    protected static string $resource = InboundBatchResource::class;

    protected function getHeaderActions(): array
    {
        // Create action will be implemented in US-B018 (Manual Inbound Batch creation)
        return [];
    }
}
