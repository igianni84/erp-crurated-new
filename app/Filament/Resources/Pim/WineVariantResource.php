<?php

namespace App\Filament\Resources\Pim;

use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\WineVariantResource\Pages;
use App\Filament\Resources\Pim\WineVariantResource\RelationManagers;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WineVariantResource extends Resource
{
    protected static ?string $model = WineVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Wine Variants';

    protected static ?string $modelLabel = 'Wine Variant';

    protected static ?string $pluralModelLabel = 'Wine Variants';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Wine Master')
                    ->schema([
                        Forms\Components\Select::make('wine_master_id')
                            ->label('Wine Master')
                            ->relationship('wineMaster', 'name')
                            ->getOptionLabelFromRecordUsing(fn (WineMaster $record): string => "{$record->name} ({$record->producer})")
                            ->searchable(['name', 'producer'])
                            ->preload()
                            ->required(),
                    ]),
                Forms\Components\Section::make('Vintage Information')
                    ->schema([
                        Forms\Components\TextInput::make('vintage_year')
                            ->label('Vintage Year')
                            ->required()
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(date('Y') + 1),
                        Forms\Components\TextInput::make('alcohol_percentage')
                            ->label('Alcohol %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Drinking Window')
                    ->schema([
                        Forms\Components\TextInput::make('drinking_window_start')
                            ->label('Start Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2200),
                        Forms\Components\TextInput::make('drinking_window_end')
                            ->label('End Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2200)
                            ->gte('drinking_window_start'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\KeyValue::make('critic_scores')
                            ->label('Critic Scores')
                            ->keyLabel('Critic')
                            ->valueLabel('Score')
                            ->reorderable()
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('production_notes')
                            ->label('Production Notes')
                            ->keyLabel('Note Type')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wineMaster.name')
                    ->label('Wine Master')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wineMaster.producer')
                    ->label('Producer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vintage_year')
                    ->label('Vintage')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('alcohol_percentage')
                    ->label('Alcohol %')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProductLifecycleStatus $state): string => $state->label())
                    ->color(fn (ProductLifecycleStatus $state): string => $state->color())
                    ->icon(fn (ProductLifecycleStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('completeness')
                    ->label('Completeness')
                    ->badge()
                    ->getStateUsing(fn (WineVariant $record): string => $record->getCompletenessPercentage().'%')
                    ->color(fn (WineVariant $record): string => $record->getCompletenessColor())
                    ->sortable(query: function ($query, string $direction): void {
                        // Sort by completeness by ordering by filled fields
                        // This is an approximation since we can't easily sort by computed value
                        $query->orderByRaw('
                            (CASE WHEN alcohol_percentage IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN drinking_window_start IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN drinking_window_end IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN critic_scores IS NOT NULL AND critic_scores != "[]" AND critic_scores != "null" THEN 1 ELSE 0 END +
                             CASE WHEN production_notes IS NOT NULL AND production_notes != "[]" AND production_notes != "null" THEN 1 ELSE 0 END)
                            '.$direction
                        );
                    }),
                Tables\Columns\TextColumn::make('drinking_window_start')
                    ->label('Drink From')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('drinking_window_end')
                    ->label('Drink To')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('wine_master_id')
                    ->label('Wine Master')
                    ->relationship('wineMaster', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options(
                        collect(ProductLifecycleStatus::cases())
                            ->mapWithKeys(fn (ProductLifecycleStatus $status): array => [
                                $status->value => $status->label(),
                            ])
                            ->toArray()
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('submit_for_review')
                        ->label('Submit for Review')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Submit for Review')
                        ->modalDescription('Are you sure you want to submit this wine variant for review?')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::InReview))
                        ->action(function (WineVariant $record): void {
                            $record->submitForReview();
                            Notification::make()
                                ->title('Submitted for Review')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Wine Variant')
                        ->modalDescription('Are you sure you want to approve this wine variant?')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Approved))
                        ->action(function (WineVariant $record): void {
                            $record->approve();
                            Notification::make()
                                ->title('Approved')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reject Wine Variant')
                        ->modalDescription('Are you sure you want to reject this wine variant and return it to draft?')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Draft) && $record->isInReview())
                        ->action(function (WineVariant $record): void {
                            $record->reject();
                            Notification::make()
                                ->title('Rejected')
                                ->warning()
                                ->send();
                        }),
                    Tables\Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Publish Wine Variant')
                        ->modalDescription(fn (WineVariant $record): string => $record->hasBlockingIssues()
                            ? 'Cannot publish: there are blocking issues that must be resolved first.'
                            : 'Are you sure you want to publish this wine variant? This will make it visible.')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Published))
                        ->disabled(fn (WineVariant $record): bool => $record->hasBlockingIssues())
                        ->action(function (WineVariant $record): void {
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
                    Tables\Actions\Action::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Archive Wine Variant')
                        ->modalDescription('Are you sure you want to archive this wine variant? It will no longer be active.')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Archived))
                        ->action(function (WineVariant $record): void {
                            $record->archive();
                            Notification::make()
                                ->title('Archived')
                                ->success()
                                ->send();
                        }),
                ])->label('Lifecycle')
                    ->icon('heroicon-o-arrow-path')
                    ->button(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('vintage_year', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SellableSkusRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWineVariants::route('/'),
            'create' => Pages\CreateWineVariant::route('/create'),
            'view' => Pages\ViewWineVariant::route('/{record}'),
            'edit' => Pages\EditWineVariant::route('/{record}/edit'),
        ];
    }
}
