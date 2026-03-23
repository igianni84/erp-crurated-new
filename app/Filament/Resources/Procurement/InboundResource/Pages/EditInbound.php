<?php

namespace App\Filament\Resources\Procurement\InboundResource\Pages;

use App\Filament\Resources\Procurement\InboundResource;
use App\Models\Procurement\Inbound;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInbound extends EditRecord
{
    protected static string $resource = InboundResource::class;

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

        /** @var Inbound $record */
        $record = $this->getRecord();

        if ($record->isCompleted()) {
            $this->redirect(InboundResource::getUrl('view', ['record' => $record]));
        }
    }
}
