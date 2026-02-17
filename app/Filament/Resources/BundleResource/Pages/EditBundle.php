<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Filament\Resources\BundleResource;
use App\Models\Commercial\Bundle;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBundle extends EditRecord
{
    protected static string $resource = BundleResource::class;

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

        /** @var Bundle $record */
        $record = $this->getRecord();

        // Redirect to view page if bundle is not editable
        if (! $record->isEditable()) {
            $this->redirect(BundleResource::getUrl('view', ['record' => $record]));
        }
    }
}
