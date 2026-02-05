<?php

namespace App\Filament\Resources\Finance\CreditNoteResource\Pages;

use App\Filament\Resources\Finance\CreditNoteResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCreditNote extends ViewRecord
{
    protected static string $resource = CreditNoteResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Header actions will be implemented in US-E064 and US-E066
        ];
    }
}
