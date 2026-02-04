<?php

namespace App\Filament\Resources\Inventory\InboundBatchResource\Pages;

use App\Filament\Resources\Inventory\InboundBatchResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInboundBatch extends ViewRecord
{
    protected static string $resource = InboundBatchResource::class;

    protected function getHeaderActions(): array
    {
        // Actions will be implemented in US-B016 (Inbound Batch Detail)
        return [];
    }
}
