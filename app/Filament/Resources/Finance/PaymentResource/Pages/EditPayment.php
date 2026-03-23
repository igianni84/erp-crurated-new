<?php

namespace App\Filament\Resources\Finance\PaymentResource\Pages;

use App\Filament\Resources\Finance\PaymentResource;
use App\Models\Finance\Payment;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

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

        /** @var Payment $record */
        $record = $this->getRecord();

        if (! $record->isPending()) {
            $this->redirect(PaymentResource::getUrl('view', ['record' => $record]));
        }
    }
}
