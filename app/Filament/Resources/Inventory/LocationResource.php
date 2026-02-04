<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Filament\Resources\Inventory\LocationResource\Pages;
use App\Models\Inventory\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

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
                // Warning banner for pending serialization when disabling serialization_authorized
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('serialization_warning')
                            ->label('')
                            ->content('⚠️ WARNING: This location has inbound batches pending serialization. Disabling serialization authorization will prevent these batches from being serialized at this location.')
                            ->extraAttributes(['class' => 'text-danger-600 font-semibold']),
                    ])
                    ->extraAttributes(['class' => 'bg-danger-50 border-danger-300'])
                    ->visible(function (?Location $record, Forms\Get $get): bool {
                        // Only show warning in edit mode when:
                        // 1. Location currently has serialization_authorized = true
                        // 2. User is trying to set it to false
                        // 3. There are pending serialization batches
                        if ($record === null) {
                            return false;
                        }

                        $currentValue = $get('serialization_authorized');
                        $hasPendingBatches = $record->inboundBatches()
                            ->whereIn('serialization_status', [
                                InboundBatchStatus::PendingSerialization->value,
                                InboundBatchStatus::PartiallySerialized->value,
                            ])
                            ->exists();

                        return $record->serialization_authorized
                            && $currentValue === false
                            && $hasPendingBatches;
                    }),

                Forms\Components\Section::make('Location Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Location Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Main Warehouse Italy, London Consignment')
                            ->unique(
                                table: Location::class,
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->withoutTrashed()
                            ),

                        Forms\Components\Select::make('location_type')
                            ->label('Location Type')
                            ->required()
                            ->options(collect(LocationType::cases())
                                ->mapWithKeys(fn (LocationType $type) => [$type->value => $type->label()])
                                ->toArray())
                            ->native(false),

                        Forms\Components\TextInput::make('country')
                            ->label('Country')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Italy, United Kingdom, France'),

                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->nullable()
                            ->rows(3)
                            ->placeholder('Full postal address'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('serialization_authorized')
                            ->label('Serialization Authorized')
                            ->helperText('Allow serialization of bottles at this location. Only authorized locations can perform serialization.')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('linked_wms_id')
                            ->label('WMS ID')
                            ->nullable()
                            ->maxLength(255)
                            ->placeholder('External WMS system identifier')
                            ->helperText('Link to external Warehouse Management System'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options(collect(LocationStatus::cases())
                                ->mapWithKeys(fn (LocationStatus $status) => [$status->value => $status->label()])
                                ->toArray())
                            ->default(LocationStatus::Active->value)
                            ->native(false),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->nullable()
                            ->rows(4)
                            ->placeholder('Additional notes about this location'),
                    ])
                    ->collapsible()
                    ->collapsed(),
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
