<?php

namespace App\Filament\Resources\Pim\LiquidProductResource\Pages;

use App\Filament\Resources\Pim\LiquidProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLiquidProducts extends ListRecords
{
    protected static string $resource = LiquidProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
