<?php

namespace App\Filament\Resources\Finance\StorageBillingResource\Pages;

use App\Filament\Resources\Finance\StorageBillingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewStorageBilling extends ViewRecord
{
    protected static string $resource = StorageBillingResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Actions will be implemented in US-E088
        ];
    }
}
