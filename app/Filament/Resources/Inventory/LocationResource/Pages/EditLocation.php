<?php

namespace App\Filament\Resources\Inventory\LocationResource\Pages;

use App\Enums\Inventory\InboundBatchStatus;
use App\Filament\Resources\Inventory\LocationResource;
use App\Models\Inventory\Location;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var Location $record */
        $record = $this->record;
        $data = $this->data;

        // Check if serialization_authorized is being disabled
        if ($record->serialization_authorized && ($data['serialization_authorized'] ?? true) === false) {
            // Check for pending serialization batches
            $pendingBatchCount = $record->inboundBatches()
                ->whereIn('serialization_status', [
                    InboundBatchStatus::PendingSerialization->value,
                    InboundBatchStatus::PartiallySerialized->value,
                ])
                ->count();

            if ($pendingBatchCount > 0) {
                Notification::make()
                    ->warning()
                    ->title('Serialization Disabled')
                    ->body("This location has {$pendingBatchCount} inbound batch(es) pending serialization. These batches will not be able to be serialized at this location until serialization is re-enabled.")
                    ->persistent()
                    ->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
