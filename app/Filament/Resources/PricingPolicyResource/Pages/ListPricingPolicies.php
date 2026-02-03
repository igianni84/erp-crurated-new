<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Filament\Resources\PricingPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPricingPolicies extends ListRecords
{
    protected static string $resource = PricingPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Pricing Policy'),
        ];
    }
}
