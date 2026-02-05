<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOffers extends ListRecords
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Offer'),
            Actions\Action::make('bulk_create')
                ->label('Bulk Create Offers')
                ->icon('heroicon-o-squares-plus')
                ->color('success')
                ->url(fn (): string => OfferResource::getUrl('bulk-create')),
        ];
    }
}
