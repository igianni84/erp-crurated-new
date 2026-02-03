<?php

namespace App\Filament\Resources\Pim\WineVariantResource\Pages;

use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\WineVariantResource;
use App\Models\Pim\WineVariant;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWineVariant extends EditRecord
{
    protected static string $resource = WineVariantResource::class;

    protected function getHeaderActions(): array
    {
        /** @var WineVariant $record */
        $record = $this->record;

        return [
            Actions\ActionGroup::make([
                Actions\Action::make('submit_for_review')
                    ->label('Submit for Review')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Submit for Review')
                    ->modalDescription('Are you sure you want to submit this wine variant for review?')
                    ->visible(fn (): bool => $record->canTransitionTo(ProductLifecycleStatus::InReview))
                    ->action(function () use ($record): void {
                        $record->submitForReview();
                        Notification::make()
                            ->title('Submitted for Review')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Wine Variant')
                    ->modalDescription('Are you sure you want to approve this wine variant?')
                    ->visible(fn (): bool => $record->canTransitionTo(ProductLifecycleStatus::Approved))
                    ->action(function () use ($record): void {
                        $record->approve();
                        Notification::make()
                            ->title('Approved')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Wine Variant')
                    ->modalDescription('Are you sure you want to reject this wine variant and return it to draft?')
                    ->visible(fn (): bool => $record->canTransitionTo(ProductLifecycleStatus::Draft) && $record->isInReview())
                    ->action(function () use ($record): void {
                        $record->reject();
                        Notification::make()
                            ->title('Rejected')
                            ->warning()
                            ->send();
                    }),
                Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publish Wine Variant')
                    ->modalDescription(fn (): string => $record->hasBlockingIssues()
                        ? 'Cannot publish: there are blocking issues that must be resolved first.'
                        : 'Are you sure you want to publish this wine variant? This will make it visible.')
                    ->visible(fn (): bool => $record->canTransitionTo(ProductLifecycleStatus::Published))
                    ->disabled(fn (): bool => $record->hasBlockingIssues())
                    ->action(function () use ($record): void {
                        if ($record->hasBlockingIssues()) {
                            Notification::make()
                                ->title('Cannot Publish')
                                ->body('Resolve all blocking issues before publishing.')
                                ->danger()
                                ->send();

                            return;
                        }
                        $record->publish();
                        Notification::make()
                            ->title('Published')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Archive Wine Variant')
                    ->modalDescription('Are you sure you want to archive this wine variant? It will no longer be active.')
                    ->visible(fn (): bool => $record->canTransitionTo(ProductLifecycleStatus::Archived))
                    ->action(function () use ($record): void {
                        $record->archive();
                        Notification::make()
                            ->title('Archived')
                            ->success()
                            ->send();
                    }),
            ])->label('Lifecycle')
                ->icon('heroicon-o-arrow-path')
                ->button(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
