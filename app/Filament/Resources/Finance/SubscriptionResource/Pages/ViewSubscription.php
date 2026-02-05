<?php

namespace App\Filament\Resources\Finance\SubscriptionResource\Pages;

use App\Filament\Resources\Finance\SubscriptionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Actions will be implemented in US-E084
        ];
    }
}
