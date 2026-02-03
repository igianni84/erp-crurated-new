<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Filament\Resources\Pim\ProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No direct create action - users should choose category first
            // This will be implemented in US-012 (Create Product flow)
        ];
    }
}
