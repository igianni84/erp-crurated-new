<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\DiscountRuleStatus;
use App\Enums\Commercial\DiscountRuleType;
use App\Filament\Resources\DiscountRuleResource\Pages;
use App\Models\Commercial\DiscountRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiscountRuleResource extends Resource
{
    protected static ?string $model = DiscountRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Discount Rules';

    protected static ?string $modelLabel = 'Discount Rule';

    protected static ?string $pluralModelLabel = 'Discount Rules';

    protected static ?string $navigationParentItem = 'Discounts & Rules';

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rule Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Summer Sale 15%, Volume Discount 6+'),
                        Forms\Components\Select::make('rule_type')
                            ->label('Rule Type')
                            ->options(collect(DiscountRuleType::cases())->mapWithKeys(fn (DiscountRuleType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->native(false)
                            ->helperText(fn (?string $state): ?string => $state !== null
                                ? DiscountRuleType::tryFrom($state)?->description()
                                : 'Select a rule type to see its description'),
                        Forms\Components\Select::make('status')
                            ->options(collect(DiscountRuleStatus::cases())->mapWithKeys(fn (DiscountRuleStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(DiscountRuleStatus::Active->value)
                            ->native(false)
                            ->disabled(fn (?DiscountRule $record) => $record !== null && ! $record->canBeEdited())
                            ->helperText(fn (?DiscountRule $record) => $record !== null && ! $record->canBeEdited()
                                ? 'Status cannot be changed while active Offers are using this rule.'
                                : null),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('rule_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (DiscountRuleType $state): string => $state->label())
                    ->color(fn (DiscountRuleType $state): string => $state->color())
                    ->icon(fn (DiscountRuleType $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Summary')
                    ->getStateUsing(fn (DiscountRule $record): string => $record->getSummary())
                    ->color('gray')
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DiscountRuleStatus $state): string => $state->label())
                    ->color(fn (DiscountRuleStatus $state): string => $state->color())
                    ->icon(fn (DiscountRuleStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('offers_using_count')
                    ->label('Offers Using')
                    ->getStateUsing(fn (DiscountRule $record): int => $record->getOffersUsingCount())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('offerBenefits')
                            ->orderBy('offer_benefits_count', $direction);
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rule_type')
                    ->label('Rule Type')
                    ->options(collect(DiscountRuleType::cases())->mapWithKeys(fn (DiscountRuleType $type) => [
                        $type->value => $type->label(),
                    ])),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DiscountRuleStatus::cases())->mapWithKeys(fn (DiscountRuleStatus $status) => [
                        $status->value => $status->label(),
                    ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (DiscountRule $record): bool => $record->canBeEdited()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate Rules')
                        ->modalDescription('Are you sure you want to activate the selected discount rules?')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $activated = 0;
                            foreach ($records as $record) {
                                /** @var DiscountRule $record */
                                if ($record->isInactive()) {
                                    $record->status = DiscountRuleStatus::Active;
                                    $record->save();
                                    $activated++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("{$activated} rule(s) activated")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Rules')
                        ->modalDescription('Are you sure you want to deactivate the selected discount rules? Rules with active Offers will be skipped.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $deactivated = 0;
                            $skipped = 0;
                            foreach ($records as $record) {
                                /** @var DiscountRule $record */
                                if ($record->canBeDeactivated()) {
                                    $record->status = DiscountRuleStatus::Inactive;
                                    $record->save();
                                    $deactivated++;
                                } elseif ($record->isActive()) {
                                    $skipped++;
                                }
                            }
                            $message = "{$deactivated} rule(s) deactivated";
                            if ($skipped > 0) {
                                $message .= ", {$skipped} skipped (have active Offers)";
                            }
                            \Filament\Notifications\Notification::make()
                                ->title($message)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action): void {
                            $hasReferencedRules = false;
                            foreach ($records as $record) {
                                /** @var DiscountRule $record */
                                if ($record->isReferencedByAnyOffer()) {
                                    $hasReferencedRules = true;
                                    break;
                                }
                            }
                            if ($hasReferencedRules) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot delete')
                                    ->body('Some rules are referenced by Offers and cannot be deleted.')
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscountRules::route('/'),
            'create' => Pages\CreateDiscountRule::route('/create'),
            'view' => Pages\ViewDiscountRule::route('/{record}'),
            'edit' => Pages\EditDiscountRule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
