<?php

namespace App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;

use App\Filament\Resources\Procurement\ProcurementIntentResource;
use App\Models\Procurement\ProcurementIntent;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProcurementIntent extends EditRecord
{
    protected static string $resource = ProcurementIntentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var ProcurementIntent $record */
        $record = $this->getRecord();

        if (! $record->isDraft()) {
            $this->redirect(ProcurementIntentResource::getUrl('view', ['record' => $record]));
        }
    }
}
