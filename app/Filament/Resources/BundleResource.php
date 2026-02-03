<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\BundlePricingLogic;
use App\Enums\Commercial\BundleStatus;
use App\Filament\Resources\BundleResource\Pages;
use App\Models\Commercial\Bundle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Bundles';

    protected static ?string $modelLabel = 'Bundle';

    protected static ?string $pluralModelLabel = 'Bundles';

    protected static ?string $navigationParentItem = 'Bundles';

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bundle Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Premium Wine Collection, Holiday Gift Set'),
                        Forms\Components\TextInput::make('bundle_sku')
                            ->label('Bundle SKU')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., BDL-WINE-001')
                            ->helperText('Unique identifier for this bundle'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing Configuration')
                    ->schema([
                        Forms\Components\Select::make('pricing_logic')
                            ->label('Pricing Logic')
                            ->options(collect(BundlePricingLogic::cases())->mapWithKeys(fn (BundlePricingLogic $logic) => [
                                $logic->value => $logic->label(),
                            ]))
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText(fn ($state): string => $state !== null
                                ? BundlePricingLogic::tryFrom($state)?->description() ?? ''
                                : 'Select how the bundle price should be calculated'),
                        Forms\Components\TextInput::make('fixed_price')
                            ->label('Fixed Price')
                            ->numeric()
                            ->prefix('€')
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('e.g., 150.00')
                            ->visible(fn ($get): bool => $get('pricing_logic') === BundlePricingLogic::FixedPrice->value)
                            ->required(fn ($get): bool => $get('pricing_logic') === BundlePricingLogic::FixedPrice->value),
                        Forms\Components\TextInput::make('percentage_off')
                            ->label('Percentage Off')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.1)
                            ->placeholder('e.g., 10')
                            ->helperText('Discount percentage applied to the sum of component prices')
                            ->visible(fn ($get): bool => $get('pricing_logic') === BundlePricingLogic::PercentageOffSum->value)
                            ->required(fn ($get): bool => $get('pricing_logic') === BundlePricingLogic::PercentageOffSum->value),
                    ]),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(collect(BundleStatus::cases())->mapWithKeys(fn (BundleStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(BundleStatus::Draft->value)
                            ->native(false)
                            ->disabled(fn (?Bundle $record) => $record !== null && ! $record->isEditable())
                            ->helperText(fn (?Bundle $record) => $record !== null && ! $record->isEditable()
                                ? 'Status cannot be changed because the bundle is not in draft.'
                                : null),
                    ]),
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
                Tables\Columns\TextColumn::make('bundle_sku')
                    ->label('Bundle SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('pricing_logic')
                    ->label('Pricing Logic')
                    ->badge()
                    ->formatStateUsing(fn (BundlePricingLogic $state): string => $state->label())
                    ->color(fn (BundlePricingLogic $state): string => $state->color())
                    ->icon(fn (BundlePricingLogic $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('components_count')
                    ->label('Components')
                    ->counts('components')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BundleStatus $state): string => $state->label())
                    ->color(fn (BundleStatus $state): string => $state->color())
                    ->icon(fn (BundleStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('computed_price')
                    ->label('Computed Price')
                    ->getStateUsing(function (Bundle $record): string {
                        return self::getComputedPriceDisplay($record);
                    })
                    ->color('gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(BundleStatus::cases())->mapWithKeys(fn (BundleStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                Tables\Filters\SelectFilter::make('pricing_logic')
                    ->label('Pricing Logic')
                    ->options(collect(BundlePricingLogic::cases())->mapWithKeys(fn (BundlePricingLogic $logic) => [
                        $logic->value => $logic->label(),
                    ])),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Bundle $record): bool => $record->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate Bundles')
                        ->modalDescription('Are you sure you want to activate the selected bundles? Only draft bundles with at least one component will be activated.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $activated = 0;
                            $skipped = 0;
                            foreach ($records as $record) {
                                /** @var Bundle $record */
                                if ($record->canBeActivated()) {
                                    $record->status = BundleStatus::Active;
                                    $record->save();
                                    $activated++;
                                } elseif ($record->isDraft()) {
                                    $skipped++;
                                }
                            }
                            $message = "{$activated} bundle(s) activated";
                            if ($skipped > 0) {
                                $message .= ", {$skipped} skipped (no components)";
                            }
                            \Filament\Notifications\Notification::make()
                                ->title($message)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Bundles')
                        ->modalDescription('Are you sure you want to deactivate the selected bundles?')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $deactivated = 0;
                            foreach ($records as $record) {
                                /** @var Bundle $record */
                                if ($record->canBeDeactivated()) {
                                    $record->status = BundleStatus::Inactive;
                                    $record->save();
                                    $deactivated++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("{$deactivated} bundle(s) deactivated")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * Get the computed price display for a bundle.
     */
    public static function getComputedPriceDisplay(Bundle $record): string
    {
        return match ($record->pricing_logic) {
            BundlePricingLogic::SumComponents => 'Sum of components',
            BundlePricingLogic::FixedPrice => $record->fixed_price !== null
                ? '€ '.number_format((float) $record->fixed_price, 2)
                : 'Not set',
            BundlePricingLogic::PercentageOffSum => $record->percentage_off !== null
                ? number_format((float) $record->percentage_off, 0).'% off sum'
                : 'Not set',
        };
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
            'index' => Pages\ListBundles::route('/'),
            'create' => Pages\CreateBundle::route('/create'),
            'view' => Pages\ViewBundle::route('/{record}'),
            'edit' => Pages\EditBundle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
