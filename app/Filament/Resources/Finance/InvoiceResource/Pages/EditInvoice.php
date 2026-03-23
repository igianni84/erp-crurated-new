<?php

namespace App\Filament\Resources\Finance\InvoiceResource\Pages;

use App\Filament\Resources\Finance\InvoiceResource;
use App\Models\Finance\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

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

        /** @var Invoice $record */
        $record = $this->getRecord();

        if (! $record->isDraft()) {
            $this->redirect(InvoiceResource::getUrl('view', ['record' => $record]));
        }
    }
}
