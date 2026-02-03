<?php

namespace App\Filament\Resources\DiscountRuleResource\Pages;

use App\Filament\Resources\DiscountRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiscountRule extends EditRecord
{
    protected static string $resource = DiscountRuleResource::class;

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\Commercial\DiscountRule $record */
        $record = $this->getRecord();

        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $record->canBeDeleted())
                ->before(function (Actions\DeleteAction $action) use ($record): void {
                    if ($record->isReferencedByAnyOffer()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete')
                            ->body('This rule is referenced by Offers and cannot be deleted.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
