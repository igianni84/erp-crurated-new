<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Models\Commercial\Offer;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOffer extends ViewRecord
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Offer $record */
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn () => $record->isEditable()),
        ];
    }
}
