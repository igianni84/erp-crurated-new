<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Filament\Resources\OfferResource\Pages;
use App\Models\Commercial\Offer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Offers';

    protected static ?string $modelLabel = 'Offer';

    protected static ?string $pluralModelLabel = 'Offers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Offer Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('sellable_sku_id')
                            ->label('Sellable SKU')
                            ->relationship('sellableSku', 'sku_code')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('price_book_id')
                            ->label('Price Book')
                            ->relationship('priceBook', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Offer Type & Visibility')
                    ->schema([
                        Forms\Components\Select::make('offer_type')
                            ->label('Offer Type')
                            ->options(collect(OfferType::cases())->mapWithKeys(fn (OfferType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->default(OfferType::Standard->value)
                            ->native(false),
                        Forms\Components\Select::make('visibility')
                            ->options(collect(OfferVisibility::cases())->mapWithKeys(fn (OfferVisibility $visibility) => [
                                $visibility->value => $visibility->label(),
                            ]))
                            ->required()
                            ->default(OfferVisibility::Public->value)
                            ->native(false),
                        Forms\Components\TextInput::make('campaign_tag')
                            ->label('Campaign Tag')
                            ->maxLength(255)
                            ->placeholder('e.g., summer-2026, black-friday')
                            ->helperText('Optional tag for grouping related offers'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Validity Period')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false),
                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->helperText('Leave empty for indefinite validity')
                            ->after('valid_from'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(collect(OfferStatus::cases())->mapWithKeys(fn (OfferStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(OfferStatus::Draft->value)
                            ->native(false)
                            ->disabled(fn (?Offer $record) => $record !== null && ! $record->isEditable())
                            ->helperText(fn (?Offer $record) => $record !== null && ! $record->isEditable() ? 'Status can only be changed through actions.' : null),
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
                    ->description(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'Expiring soon' : null)
                    ->icon(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('warning'),
                Tables\Columns\TextColumn::make('sellable_sku_display')
                    ->label('Sellable SKU')
                    ->getStateUsing(function (Offer $record): string {
                        $sku = $record->sellableSku;
                        if ($sku === null) {
                            return '-';
                        }
                        $wineVariant = $sku->wineVariant;
                        if ($wineVariant === null) {
                            return $sku->sku_code;
                        }
                        $wineMaster = $wineVariant->wineMaster;
                        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                        $vintage = $wineVariant->vintage_year ?? '';
                        $format = $sku->format !== null ? $sku->format->name : '';
                        $caseConfig = $sku->caseConfiguration;
                        $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.'x' : '';

                        return "{$wineName} {$vintage} ({$format} {$packaging})";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            // Search by SKU code
                            $q->whereHas('sellableSku', function (Builder $skuQuery) use ($search): void {
                                $skuQuery->where('sku_code', 'like', "%{$search}%");
                            })
                            // Search by wine name
                                ->orWhereHas('sellableSku.wineVariant.wineMaster', function (Builder $wineQuery) use ($search): void {
                                    $wineQuery->where('name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('offer_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (OfferType $state): string => $state->label())
                    ->color(fn (OfferType $state): string => $state->color())
                    ->icon(fn (OfferType $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OfferStatus $state): string => $state->label())
                    ->color(fn (OfferStatus $state): string => $state->color())
                    ->icon(fn (OfferStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Valid From')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valid_to')
                    ->label('Valid To')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Indefinite')
                    ->color(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'warning' : null)
                    ->weight(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'bold' : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (OfferVisibility $state): string => $state->label())
                    ->color(fn (OfferVisibility $state): string => $state->color())
                    ->icon(fn (OfferVisibility $state): string => $state->icon())
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('campaign_tag')
                    ->label('Campaign')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(OfferStatus::cases())->mapWithKeys(fn (OfferStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                Tables\Filters\SelectFilter::make('offer_type')
                    ->label('Offer Type')
                    ->options(collect(OfferType::cases())->mapWithKeys(fn (OfferType $type) => [
                        $type->value => $type->label(),
                    ])),
                Tables\Filters\SelectFilter::make('channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('visibility')
                    ->options(collect(OfferVisibility::cases())->mapWithKeys(fn (OfferVisibility $visibility) => [
                        $visibility->value => $visibility->label(),
                    ])),
                Tables\Filters\TernaryFilter::make('expiring_soon')
                    ->label('Expiring Soon')
                    ->placeholder('All')
                    ->trueLabel('Expiring within 7 days')
                    ->falseLabel('Not expiring soon')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('valid_to')
                            ->where('valid_to', '<=', now()->addDays(7))
                            ->where('valid_to', '>=', now()),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNull('valid_to')
                                ->orWhere('valid_to', '>', now()->addDays(7));
                        }),
                    ),
                Tables\Filters\TernaryFilter::make('active_validity')
                    ->label('Validity Status')
                    ->placeholder('All')
                    ->trueLabel('Currently valid')
                    ->falseLabel('Not yet valid or expired')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->where('valid_from', '<=', now())
                            ->where(function ($q) {
                                $q->whereNull('valid_to')
                                    ->orWhere('valid_to', '>=', now());
                            }),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->where('valid_from', '>', now())
                                ->orWhere(function ($q2) {
                                    $q2->whereNotNull('valid_to')
                                        ->where('valid_to', '<', now());
                                });
                        }),
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Offer $record): bool => $record->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('pause')
                        ->label('Pause Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Pause Offers')
                        ->modalDescription('Are you sure you want to pause the selected offers? Only active offers can be paused.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $paused = 0;
                            foreach ($records as $record) {
                                /** @var Offer $record */
                                if ($record->canBePaused()) {
                                    $record->status = OfferStatus::Paused;
                                    $record->save();
                                    $paused++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("{$paused} offer(s) paused")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('cancel')
                        ->label('Cancel Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Offers')
                        ->modalDescription('Are you sure you want to cancel the selected offers? This action cannot be undone.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $cancelled = 0;
                            foreach ($records as $record) {
                                /** @var Offer $record */
                                if ($record->canBeCancelled()) {
                                    $record->status = OfferStatus::Cancelled;
                                    $record->save();
                                    $cancelled++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("{$cancelled} offer(s) cancelled")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'view' => Pages\ViewOffer::route('/{record}'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sellableSku.wineVariant.wineMaster', 'sellableSku.format', 'sellableSku.caseConfiguration', 'channel', 'priceBook'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
