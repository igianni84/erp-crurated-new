<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Fulfillment\ShippingOrder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditShippingOrder extends EditRecord
{
    protected static string $resource = ShippingOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var ShippingOrder $record */
        $record = $this->record;

        return "Edit Shipping Order: #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Only draft orders can be edited';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (ShippingOrder $record): bool => $record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Prevent editing non-draft orders.
     */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var ShippingOrder $record */
        $record = $this->record;

        if (! $record->isDraft()) {
            abort(403, 'Only draft shipping orders can be edited.');
        }
    }
}
