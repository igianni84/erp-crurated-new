<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateShippingOrder extends CreateRecord
{
    protected static string $resource = ShippingOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Create Shipping Order';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Create a new shipping order in draft status';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure new orders start in draft status
        $data['status'] = \App\Enums\Fulfillment\ShippingOrderStatus::Draft->value;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
