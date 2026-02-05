<?php

namespace App\Filament\Resources\Finance\RefundResource\Pages;

use App\Filament\Resources\Finance\RefundResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRefund extends ViewRecord
{
    protected static string $resource = RefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions will be implemented in US-E069
        ];
    }
}
