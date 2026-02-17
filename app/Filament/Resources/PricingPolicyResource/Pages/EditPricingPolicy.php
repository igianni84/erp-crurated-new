<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Filament\Resources\PricingPolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingPolicy extends EditRecord
{
    protected static string $resource = PricingPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
