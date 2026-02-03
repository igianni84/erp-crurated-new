<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Enums\Commercial\OfferStatus;
use App\Filament\Resources\OfferResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure new offers are created as draft
        $data['status'] = OfferStatus::Draft->value;

        return $data;
    }
}
