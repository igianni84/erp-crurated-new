<?php

namespace App\Filament\Resources\Finance\RefundResource\Pages;

use App\Filament\Resources\Finance\RefundResource;
use App\Models\Finance\Refund;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRefund extends EditRecord
{
    protected static string $resource = RefundResource::class;

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

        /** @var Refund $record */
        $record = $this->getRecord();

        if (! $record->isPending()) {
            $this->redirect(RefundResource::getUrl('view', ['record' => $record]));
        }
    }
}
