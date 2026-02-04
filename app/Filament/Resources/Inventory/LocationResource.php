<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Filament\Resources\Inventory\LocationResource\Pages;
use App\Models\Inventory\Location;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $modelLabel = 'Location';

    protected static ?string $pluralModelLabel = 'Locations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form schema will be implemented in US-B014
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Location Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('location_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (LocationType $state): string => $state->label())
                    ->color(fn (LocationType $state): string => $state->color())
                    ->icon(fn (LocationType $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('serialization_authorized')
                    ->label('Serialization')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('linked_wms_id')
                    ->label('WMS')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? 'Linked' : 'Not Linked')
                    ->color(fn (?string $state): string => $state !== null ? 'info' : 'gray')
                    ->icon(fn (?string $state): string => $state !== null ? 'heroicon-o-link' : 'heroicon-o-minus')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('linked_wms_id IS NULL '.$direction);
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (LocationStatus $state): string => $state->label())
                    ->color(fn (LocationStatus $state): string => $state->color())
                    ->icon(fn (LocationStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_summary')
                    ->label('Stock')
                    ->state(fn (Location $record): string => (string) $record->serialized_bottles_count)
                    ->suffix(' bottles')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('serialized_bottles_count', $direction);
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_type')
                    ->options(collect(LocationType::cases())
                        ->mapWithKeys(fn (LocationType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Type'),

                Tables\Filters\SelectFilter::make('country')
                    ->options(fn (): array => Location::query()
                        ->distinct()
                        ->pluck('country', 'country')
                        ->toArray())
                    ->searchable()
                    ->label('Country'),

                Tables\Filters\TernaryFilter::make('serialization_authorized')
                    ->label('Serialization Authorized'),

                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(LocationStatus::cases())
                        ->mapWithKeys(fn (LocationStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(LocationStatus::Active->value)
                    ->label('Status'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('serializedBottles'));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-B013
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
