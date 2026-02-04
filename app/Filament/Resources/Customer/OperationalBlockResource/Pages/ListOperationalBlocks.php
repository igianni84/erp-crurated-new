<?php

namespace App\Filament\Resources\Customer\OperationalBlockResource\Pages;

use App\Filament\Resources\Customer\OperationalBlockResource;
use Filament\Resources\Pages\ListRecords;

class ListOperationalBlocks extends ListRecords
{
    protected static string $resource = OperationalBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - blocks are created from Customer/Account detail pages
        ];
    }
}
