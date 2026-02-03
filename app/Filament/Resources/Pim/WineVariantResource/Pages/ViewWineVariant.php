<?php

namespace App\Filament\Resources\Pim\WineVariantResource\Pages;

use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\WineVariantResource;
use App\Models\AuditLog;
use App\Models\Pim\WineVariant;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewWineVariant extends ViewRecord
{
    protected static string $resource = WineVariantResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var WineVariant $record */
        $record = $this->record;

        $wineMaster = $record->wineMaster;
        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';

        return "{$wineName} ({$record->vintage_year})";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Product Overview')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('wineMaster.name')
                                        ->label('Wine'),
                                    TextEntry::make('vintage_year')
                                        ->label('Vintage'),
                                    TextEntry::make('wineMaster.producer')
                                        ->label('Producer'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('lifecycle_status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (ProductLifecycleStatus $state): string => $state->label())
                                        ->color(fn (ProductLifecycleStatus $state): string => $state->color())
                                        ->icon(fn (ProductLifecycleStatus $state): string => $state->icon()),
                                    TextEntry::make('completeness')
                                        ->label('Completeness')
                                        ->badge()
                                        ->getStateUsing(fn (WineVariant $record): string => $record->getCompletenessPercentage().'%')
                                        ->color(fn (WineVariant $record): string => $record->getCompletenessColor()),
                                    TextEntry::make('sellable_skus_count')
                                        ->label('Sellable SKUs')
                                        ->getStateUsing(fn (WineVariant $record): string => (string) $record->sellableSkus()->count()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Blocking Issues')
                    ->description('These issues must be resolved before publishing')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->collapsible()
                    ->visible(fn (WineVariant $record): bool => $record->hasBlockingIssues())
                    ->schema([
                        RepeatableEntry::make('blocking_issues')
                            ->label('')
                            ->getStateUsing(fn (WineVariant $record): array => $record->getBlockingIssues())
                            ->schema([
                                TextEntry::make('message')
                                    ->label('')
                                    ->icon('heroicon-o-x-circle')
                                    ->iconColor('danger')
                                    ->weight(FontWeight::Medium)
                                    ->columnSpan(2),
                                TextEntry::make('tab')
                                    ->label('')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn (string $state): string => self::formatTabName($state))
                                    ->suffix(' →')
                                    ->columnSpan(1),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Warnings')
                    ->description('Recommended improvements for better product data')
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('warning')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (WineVariant $record): bool => count($record->getWarnings()) > 0)
                    ->schema([
                        RepeatableEntry::make('warnings')
                            ->label('')
                            ->getStateUsing(fn (WineVariant $record): array => $record->getWarnings())
                            ->schema([
                                TextEntry::make('message')
                                    ->label('')
                                    ->icon('heroicon-o-information-circle')
                                    ->iconColor('warning')
                                    ->weight(FontWeight::Medium)
                                    ->columnSpan(2),
                                TextEntry::make('tab')
                                    ->label('')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn (string $state): string => self::formatTabName($state))
                                    ->suffix(' →')
                                    ->columnSpan(1),
                            ])
                            ->columns(3),
                    ]),
                Section::make('No Issues')
                    ->description('This product has no blocking issues and is ready for the next workflow step')
                    ->icon('heroicon-o-check-circle')
                    ->iconColor('success')
                    ->visible(fn (WineVariant $record): bool => ! $record->hasBlockingIssues() && count($record->getWarnings()) === 0),
                Section::make('Audit History')
                    ->description('Timeline of all changes made to this product')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('auditLogs')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('event')
                                            ->label('')
                                            ->badge()
                                            ->formatStateUsing(fn (AuditLog $record): string => $record->getEventLabel())
                                            ->color(fn (AuditLog $record): string => $record->getEventColor())
                                            ->icon(fn (AuditLog $record): string => $record->getEventIcon())
                                            ->columnSpan(1),
                                        TextEntry::make('user.name')
                                            ->label('')
                                            ->default('System')
                                            ->icon('heroicon-o-user')
                                            ->columnSpan(1),
                                        TextEntry::make('created_at')
                                            ->label('')
                                            ->dateTime()
                                            ->icon('heroicon-o-calendar')
                                            ->columnSpan(1),
                                        TextEntry::make('changes')
                                            ->label('')
                                            ->getStateUsing(fn (AuditLog $record): string => self::formatAuditChanges($record))
                                            ->html()
                                            ->columnSpan(1),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var WineVariant $record */
        $record = $this->record;

        return [
            Actions\EditAction::make(),
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
                        $this->refreshFormData(['lifecycle_status']);
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
                        $this->refreshFormData(['lifecycle_status']);
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
                        $this->refreshFormData(['lifecycle_status']);
                    }),
                Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publish Wine Variant')
                    ->modalDescription(function () use ($record): string {
                        if ($record->hasBlockingIssues()) {
                            return 'Cannot publish: there are blocking issues that must be resolved first.';
                        }

                        return 'Are you sure you want to publish this wine variant? This will make it visible.';
                    })
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
                        $this->refreshFormData(['lifecycle_status']);
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
                        $this->refreshFormData(['lifecycle_status']);
                    }),
            ])->label('Lifecycle')
                ->icon('heroicon-o-arrow-path')
                ->button(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    /**
     * Format tab name for display.
     */
    protected static function formatTabName(string $tab): string
    {
        return match ($tab) {
            'core_info' => 'Core Info',
            'attributes' => 'Attributes',
            'sellable_skus' => 'Sellable SKUs',
            'media' => 'Media',
            'lifecycle' => 'Lifecycle',
            default => ucfirst(str_replace('_', ' ', $tab)),
        };
    }

    /**
     * Format audit log changes for display.
     */
    protected static function formatAuditChanges(AuditLog $log): string
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];

        if ($log->event === AuditLog::EVENT_CREATED) {
            $fieldCount = count($newValues);

            return "<span class='text-sm text-gray-500'>{$fieldCount} field(s) set</span>";
        }

        if ($log->event === AuditLog::EVENT_DELETED) {
            return "<span class='text-sm text-gray-500'>Record deleted</span>";
        }

        $changes = [];
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allFields as $field) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newValues[$field] ?? null;

            if ($oldValue !== $newValue) {
                $fieldLabel = ucfirst(str_replace('_', ' ', $field));
                $oldDisplay = self::formatValue($oldValue);
                $newDisplay = self::formatValue($newValue);
                $changes[] = "<strong>{$fieldLabel}</strong>: {$oldDisplay} → {$newDisplay}";
            }
        }

        return count($changes) > 0
            ? '<span class="text-sm">'.implode('<br>', $changes).'</span>'
            : '<span class="text-sm text-gray-500">No field changes</span>';
    }

    /**
     * Format a value for display in audit logs.
     */
    protected static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '<em class="text-gray-400">empty</em>';
        }

        if (is_array($value)) {
            return '<em class="text-gray-500">['.count($value).' items]</em>';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $stringValue = (string) $value;
        if (strlen($stringValue) > 50) {
            return htmlspecialchars(substr($stringValue, 0, 47)).'...';
        }

        return htmlspecialchars($stringValue);
    }
}
