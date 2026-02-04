<?php

namespace App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;

use App\Filament\Resources\Procurement\ProcurementIntentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcurementIntents extends ListRecords
{
    protected static string $resource = ProcurementIntentResource::class;

    /**
     * Get the header actions for the list page.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
