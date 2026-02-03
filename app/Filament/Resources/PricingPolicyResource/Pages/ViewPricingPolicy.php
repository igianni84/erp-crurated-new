<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Filament\Resources\PricingPolicyResource;
use App\Models\Commercial\PricingPolicy;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingPolicy extends ViewRecord
{
    protected static string $resource = PricingPolicyResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn () => $record instanceof PricingPolicy && $record->isEditable()),
        ];
    }
}
