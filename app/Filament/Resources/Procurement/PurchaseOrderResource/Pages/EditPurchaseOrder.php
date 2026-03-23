<?php

namespace App\Filament\Resources\Procurement\PurchaseOrderResource\Pages;

use App\Filament\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

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

        /** @var PurchaseOrder $record */
        $record = $this->getRecord();

        if (! $record->isDraft()) {
            $this->redirect(PurchaseOrderResource::getUrl('view', ['record' => $record]));
        }
    }
}
