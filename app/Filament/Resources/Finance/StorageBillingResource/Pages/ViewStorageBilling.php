<?php

namespace App\Filament\Resources\Finance\StorageBillingResource\Pages;

use App\Filament\Resources\Finance\StorageBillingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStorageBilling extends ViewRecord
{
    protected static string $resource = StorageBillingResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Actions will be implemented in US-E088
        ];
    }
}
