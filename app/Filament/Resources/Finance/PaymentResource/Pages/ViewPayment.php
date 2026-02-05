<?php

namespace App\Filament\Resources\Finance\PaymentResource\Pages;

use App\Filament\Resources\Finance\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Payment actions will be implemented in later stories (US-E054, US-E055, US-E056)
        ];
    }
}
