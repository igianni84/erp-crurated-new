<?php

namespace App\Filament\Resources\Finance\StorageBillingResource\Pages;

use App\Filament\Resources\Finance\StorageBillingResource;
use App\Models\Finance\StorageBillingPeriod;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStorageBilling extends EditRecord
{
    protected static string $resource = StorageBillingResource::class;

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

        /** @var StorageBillingPeriod $record */
        $record = $this->getRecord();

        if (! $record->isPending()) {
            $this->redirect(StorageBillingResource::getUrl('view', ['record' => $record]));
        }
    }
}
