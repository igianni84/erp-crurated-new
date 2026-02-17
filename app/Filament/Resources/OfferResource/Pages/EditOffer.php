<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Models\Commercial\Offer;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOffer extends EditRecord
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Offer $record */
        $record = $this->getRecord();

        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn () => $record->isEditable()),
        ];
    }

    /**
     * Prevent editing non-draft offers.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Offer $offer */
        $offer = $this->getRecord();

        if (! $offer->isEditable()) {
            $this->redirect(OfferResource::getUrl('view', ['record' => $record]));
        }
    }
}
