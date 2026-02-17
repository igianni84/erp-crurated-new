<?php

namespace App\Filament\Resources\DiscountRuleResource\Pages;

use App\Filament\Resources\DiscountRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiscountRules extends ListRecords
{
    protected static string $resource = DiscountRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Discount Rule'),
        ];
    }
}
