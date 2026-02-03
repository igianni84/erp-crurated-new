<?php

namespace App\Filament\Resources\Pim\LiquidProductResource\Pages;

use App\Filament\Resources\Pim\LiquidProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLiquidProducts extends ListRecords
{
    protected static string $resource = LiquidProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
