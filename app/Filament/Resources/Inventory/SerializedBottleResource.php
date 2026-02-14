<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\SerializedBottleResource\Pages;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SerializedBottleResource extends Resource
{
    protected static ?string $model = SerializedBottle::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Bottle Registry';

    protected static ?string $modelLabel = 'Serialized Bottle';

    protected static ?string $pluralModelLabel = 'Serialized Bottles';

    public static function form(Form $form): Form
    {
        // SerializedBottles are immutable after creation - no edit form
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Serial number copied')
                    ->weight('bold')
                    ->icon('heroicon-o-qr-code'),

                Tables\Columns\TextColumn::make('wine_format')
                    ->label('Wine + Format')
                    ->state(function (SerializedBottle $record): string {
                        $wineVariant = $record->wineVariant;
                        $format = $record->format;

                        $wineName = 'Unknown Wine';
                        if ($wineVariant !== null) {
                            $wineMaster = $wineVariant->wineMaster;
                            $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                            $vintage = $wineVariant->vintage_year ?? 'NV';
                            $wineName = "{$wineName} {$vintage}";
                        }

                        $formatName = $format !== null ? $format->name : 'Standard';

                        return "{$wineName} ({$formatName})";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('wineVariant.wineMaster', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('allocation_lineage')
                    ->label('Allocation Lineage')
                    ->state(function (SerializedBottle $record): string {
                        $allocation = $record->allocation;
                        if ($allocation === null) {
                            return 'N/A';
                        }

                        return $allocation->getBottleSkuLabel();
                    })
                    ->description(fn (SerializedBottle $record): ?string => $record->allocation !== null ? 'ID: '.substr($record->allocation_id, 0, 8).'...' : null)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('allocation_id', $direction);
                    })
                    ->icon('heroicon-o-link')
                    ->limit(30),

                Tables\Columns\TextColumn::make('currentLocation.name')
                    ->label('Current Location')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->limit(20),

                Tables\Columns\TextColumn::make('custody_holder')
                    ->label('Custody Holder')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (BottleState $state): string => $state->label())
                    ->color(fn (BottleState $state): string => $state->color())
                    ->icon(fn (BottleState $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('ownership_type')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                    ->color(fn (OwnershipType $state): string => $state->color())
                    ->icon(fn (OwnershipType $state): string => $state->icon())
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('nft_status')
                    ->label('NFT')
                    ->state(fn (SerializedBottle $record): bool => $record->hasNft())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (SerializedBottle $record): string => $record->hasNft() ? 'NFT Minted' : 'NFT Pending')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serialized_at')
                    ->label('Serialized At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('allocation_id')
                    ->label('Allocation Lineage')
                    ->options(function (): array {
                        return Allocation::query()
                            ->with(['wineVariant.wineMaster', 'format'])
                            ->orderBy('created_at', 'desc')
                            ->get()
                            ->mapWithKeys(function (Allocation $allocation): array {
                                return [$allocation->id => $allocation->getBottleSkuLabel().' (ID: '.substr($allocation->id, 0, 8).'...)'];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('current_location_id')
                    ->label('Location')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('state')
                    ->options(collect(BottleState::cases())
                        ->mapWithKeys(fn (BottleState $state) => [$state->value => $state->label()])
                        ->toArray())
                    ->multiple()
                    ->label('State'),

                Tables\Filters\SelectFilter::make('ownership_type')
                    ->options(collect(OwnershipType::cases())
                        ->mapWithKeys(fn (OwnershipType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Ownership Type'),

                Tables\Filters\TernaryFilter::make('has_nft')
                    ->label('NFT Status')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('nft_reference'),
                        false: fn (Builder $query) => $query->whereNull('nft_reference'),
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // SerializedBottles should not be bulk deleted - they are immutable audit records
            ])
            ->defaultSort('serialized_at', 'desc')
            ->recordClasses(function (SerializedBottle $record): string {
                // Visual indicators for state
                return match ($record->state) {
                    BottleState::Missing => 'bg-danger-50 dark:bg-danger-950/20',
                    BottleState::ReservedForPicking => 'bg-warning-50 dark:bg-warning-950/20',
                    BottleState::Consumed, BottleState::Destroyed => 'opacity-60',
                    default => '',
                };
            });
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['serial_number'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['wineVariant.wineMaster', 'currentLocation']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var SerializedBottle $record */
        return $record->serial_number;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var SerializedBottle $record */
        $wineName = 'Unknown Wine';
        $wineVariant = $record->wineVariant;
        if ($wineVariant !== null) {
            $wineMaster = $wineVariant->wineMaster;
            $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
            $vintage = $wineVariant->vintage_year ?? 'NV';
            $wineName = "{$wineName} {$vintage}";
        }

        return [
            'Wine' => $wineName,
            'Location' => $record->currentLocation !== null ? $record->currentLocation->name : '-',
            'State' => $record->state->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-B026
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSerializedBottles::route('/'),
            'view' => Pages\ViewSerializedBottle::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['wineVariant.wineMaster', 'format', 'allocation', 'currentLocation'])
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
