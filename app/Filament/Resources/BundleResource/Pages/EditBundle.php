<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Filament\Resources\BundleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBundle extends EditRecord
{
    protected static string $resource = BundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var \App\Models\Commercial\Bundle $record */
        $record = $this->getRecord();

        // Redirect to view page if bundle is not editable
        if (! $record->isEditable()) {
            $this->redirect(BundleResource::getUrl('view', ['record' => $record]));
        }
    }
}
