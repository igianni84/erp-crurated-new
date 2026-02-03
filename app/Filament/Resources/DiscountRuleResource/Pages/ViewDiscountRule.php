<?php

namespace App\Filament\Resources\DiscountRuleResource\Pages;

use App\Filament\Resources\DiscountRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDiscountRule extends ViewRecord
{
    protected static string $resource = DiscountRuleResource::class;

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\Commercial\DiscountRule $record */
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => $record->canBeEdited()),
        ];
    }
}
