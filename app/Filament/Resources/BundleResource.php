<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\BundlePricingLogic;
use App\Enums\Commercial\BundleStatus;
use App\Filament\Resources\BundleResource\Pages\CreateBundle;
use App\Filament\Resources\BundleResource\Pages\EditBundle;
use App\Filament\Resources\BundleResource\Pages\ListBundles;
use App\Filament\Resources\BundleResource\Pages\ViewBundle;
use App\Filament\Resources\BundleResource\RelationManagers\ComponentsRelationManager;
use App\Models\Commercial\Bundle;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Bundles';

    protected static ?string $modelLabel = 'Bundle';

    protected static ?string $pluralModelLabel = 'Bundles';

    protected static ?string $navigationParentItem = 'Bundles';

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bundle Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Premium Wine Collection, Holiday Gift Set'),
                        TextInput::make('bundle_sku')
                            ->label('Bundle SKU')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., BDL-WINE-001')
                            ->helperText('Unique identifier for this bundle'),
                    ])
                    ->columns(2),

                Section::make('Pricing Configuration')
                    ->schema([
                        Select::make('pricing_logic')
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
                        TextInput::make('fixed_price')
                            ->label('Fixed Price')
                            ->numeric()
                            ->prefix('€')
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('e.g., 150.00')
                            ->visible(fn ($get): bool => $get('pricing_logic') === BundlePricingLogic::FixedPrice->value)
                            ->required(fn ($get): bool => $get('pricing_logic') === BundlePricingLogic::FixedPrice->value),
                        TextInput::make('percentage_off')
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

                Section::make('Status')
                    ->schema([
                        Select::make('status')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('bundle_sku')
                    ->label('Bundle SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('gray'),
                TextColumn::make('pricing_logic')
                    ->label('Pricing Logic')
                    ->badge()
                    ->formatStateUsing(fn (BundlePricingLogic $state): string => $state->label())
                    ->color(fn (BundlePricingLogic $state): string => $state->color())
                    ->icon(fn (BundlePricingLogic $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('components_count')
                    ->label('Components')
                    ->counts('components')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BundleStatus $state): string => $state->label())
                    ->color(fn (BundleStatus $state): string => $state->color())
                    ->icon(fn (BundleStatus $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('computed_price')
                    ->label('Computed Price')
                    ->getStateUsing(function (Bundle $record): string {
                        return self::getComputedPriceDisplay($record);
                    })
                    ->color('gray'),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BundleStatus::cases())->mapWithKeys(fn (BundleStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                SelectFilter::make('pricing_logic')
                    ->label('Pricing Logic')
                    ->options(collect(BundlePricingLogic::cases())->mapWithKeys(fn (BundlePricingLogic $logic) => [
                        $logic->value => $logic->label(),
                    ])),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Bundle $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate Bundles')
                        ->modalDescription('Are you sure you want to activate the selected bundles? Only draft bundles with at least one component will be activated.')
                        ->action(function (Collection $records): void {
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
                            Notification::make()
                                ->title($message)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Bundles')
                        ->modalDescription('Are you sure you want to deactivate the selected bundles?')
                        ->action(function (Collection $records): void {
                            $deactivated = 0;
                            foreach ($records as $record) {
                                /** @var Bundle $record */
                                if ($record->canBeDeactivated()) {
                                    $record->status = BundleStatus::Inactive;
                                    $record->save();
                                    $deactivated++;
                                }
                            }
                            Notification::make()
                                ->title("{$deactivated} bundle(s) deactivated")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            ComponentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBundles::route('/'),
            'create' => CreateBundle::route('/create'),
            'view' => ViewBundle::route('/{record}'),
            'edit' => EditBundle::route('/{record}/edit'),
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
