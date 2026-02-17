<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOffers extends ListRecords
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Offer'),
            Action::make('bulk_create')
                ->label('Bulk Create Offers')
                ->icon('heroicon-o-squares-plus')
                ->color('success')
                ->url(fn (): string => OfferResource::getUrl('bulk-create')),
        ];
    }
}
