<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Filament\Resources\PricingPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPricingPolicy extends EditRecord
{
    protected static string $resource = PricingPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
