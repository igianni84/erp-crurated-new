<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Filament\Resources\OfferResource\Pages\BulkCreateOffers;
use App\Filament\Resources\OfferResource\Pages\CreateOffer;
use App\Filament\Resources\OfferResource\Pages\EditOffer;
use App\Filament\Resources\OfferResource\Pages\ListOffers;
use App\Filament\Resources\OfferResource\Pages\ViewOffer;
use App\Models\Commercial\Offer;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Offers';

    protected static ?string $modelLabel = 'Offer';

    protected static ?string $pluralModelLabel = 'Offers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Offer Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('sellable_sku_id')
                            ->label('Sellable SKU')
                            ->relationship('sellableSku', 'sku_code')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('price_book_id')
                            ->label('Price Book')
                            ->relationship('priceBook', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Offer Type & Visibility')
                    ->schema([
                        Select::make('offer_type')
                            ->label('Offer Type')
                            ->options(collect(OfferType::cases())->mapWithKeys(fn (OfferType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->default(OfferType::Standard->value)
                            ->native(false),
                        Select::make('visibility')
                            ->options(collect(OfferVisibility::cases())->mapWithKeys(fn (OfferVisibility $visibility) => [
                                $visibility->value => $visibility->label(),
                            ]))
                            ->required()
                            ->default(OfferVisibility::Public->value)
                            ->native(false),
                        TextInput::make('campaign_tag')
                            ->label('Campaign Tag')
                            ->maxLength(255)
                            ->placeholder('e.g., summer-2026, black-friday')
                            ->helperText('Optional tag for grouping related offers'),
                    ])
                    ->columns(3),
                Section::make('Validity Period')
                    ->schema([
                        DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false),
                        DateTimePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->helperText('Leave empty for indefinite validity')
                            ->after('valid_from'),
                    ])
                    ->columns(2),
                Section::make('Status')
                    ->schema([
                        Select::make('status')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'Expiring soon' : null)
                    ->icon(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('warning'),
                TextColumn::make('sellable_sku_display')
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
                TextColumn::make('offer_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (OfferType $state): string => $state->label())
                    ->color(fn (OfferType $state): string => $state->color())
                    ->icon(fn (OfferType $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OfferStatus $state): string => $state->label())
                    ->color(fn (OfferStatus $state): string => $state->color())
                    ->icon(fn (OfferStatus $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('valid_from')
                    ->label('Valid From')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('valid_to')
                    ->label('Valid To')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Indefinite')
                    ->color(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'warning' : null)
                    ->weight(fn (Offer $record): ?string => $record->isExpiringSoon() ? 'bold' : null)
                    ->toggleable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (OfferVisibility $state): string => $state->label())
                    ->color(fn (OfferVisibility $state): string => $state->color())
                    ->icon(fn (OfferVisibility $state): string => $state->icon())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('campaign_tag')
                    ->label('Campaign')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(OfferStatus::cases())->mapWithKeys(fn (OfferStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                SelectFilter::make('offer_type')
                    ->label('Offer Type')
                    ->options(collect(OfferType::cases())->mapWithKeys(fn (OfferType $type) => [
                        $type->value => $type->label(),
                    ])),
                SelectFilter::make('channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('visibility')
                    ->options(collect(OfferVisibility::cases())->mapWithKeys(fn (OfferVisibility $visibility) => [
                        $visibility->value => $visibility->label(),
                    ])),
                TernaryFilter::make('expiring_soon')
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
                TernaryFilter::make('active_validity')
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Offer $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('pause')
                        ->label('Pause Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Pause Offers')
                        ->modalDescription('Are you sure you want to pause the selected offers? Only active offers can be paused.')
                        ->action(function (Collection $records): void {
                            $paused = 0;
                            foreach ($records as $record) {
                                /** @var Offer $record */
                                if ($record->canBePaused()) {
                                    $record->status = OfferStatus::Paused;
                                    $record->save();
                                    $paused++;
                                }
                            }
                            Notification::make()
                                ->title("{$paused} offer(s) paused")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('cancel')
                        ->label('Cancel Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Offers')
                        ->modalDescription('Are you sure you want to cancel the selected offers? This action cannot be undone.')
                        ->action(function (Collection $records): void {
                            $cancelled = 0;
                            foreach ($records as $record) {
                                /** @var Offer $record */
                                if ($record->canBeCancelled()) {
                                    $record->status = OfferStatus::Cancelled;
                                    $record->save();
                                    $cancelled++;
                                }
                            }
                            Notification::make()
                                ->title("{$cancelled} offer(s) cancelled")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'campaign_tag', 'sellableSku.sku_code'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['sellableSku', 'channel']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Offer $record */
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Offer $record */
        return [
            'SKU' => $record->sellableSku !== null ? $record->sellableSku->sku_code : '-',
            'Channel' => $record->channel !== null ? $record->channel->name : '-',
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
            'create' => CreateOffer::route('/create'),
            'bulk-create' => BulkCreateOffers::route('/bulk-create'),
            'view' => ViewOffer::route('/{record}'),
            'edit' => EditOffer::route('/{record}/edit'),
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
