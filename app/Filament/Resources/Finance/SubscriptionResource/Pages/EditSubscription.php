<?php

namespace App\Filament\Resources\Finance\SubscriptionResource\Pages;

use App\Filament\Resources\Finance\SubscriptionResource;
use App\Models\Finance\Subscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

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

        /** @var Subscription $record */
        $record = $this->getRecord();

        if ($record->isCancelled() || $record->isTerminal()) {
            $this->redirect(SubscriptionResource::getUrl('view', ['record' => $record]));
        }
    }
}
