<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Filament\Resources\PricingPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricingPolicies extends ListRecords
{
    protected static string $resource = PricingPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Pricing Policy'),
        ];
    }
}
