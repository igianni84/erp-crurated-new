<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Filament\Resources\Inventory\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\Inventory\LocationResource\Pages\EditLocation;
use App\Filament\Resources\Inventory\LocationResource\Pages\ListLocations;
use App\Filament\Resources\Inventory\LocationResource\Pages\ViewLocation;
use App\Filament\Resources\Inventory\LocationResource\RelationManagers;
use App\Models\Inventory\Location;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $modelLabel = 'Location';

    protected static ?string $pluralModelLabel = 'Locations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Warning banner for pending serialization when disabling serialization_authorized
                Section::make()
                    ->schema([
                        Placeholder::make('serialization_warning')
                            ->label('')
                            ->content('⚠️ WARNING: This location has inbound batches pending serialization. Disabling serialization authorization will prevent these batches from being serialized at this location.')
                            ->extraAttributes(['class' => 'text-danger-600 font-semibold']),
                    ])
                    ->extraAttributes(['class' => 'bg-danger-50 border-danger-300'])
                    ->visible(function (?Location $record, Get $get): bool {
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

                Section::make('Location Details')
                    ->schema([
                        TextInput::make('name')
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

                        Select::make('location_type')
                            ->label('Location Type')
                            ->required()
                            ->options(collect(LocationType::cases())
                                ->mapWithKeys(fn (LocationType $type) => [$type->value => $type->label()])
                                ->toArray())
                            ->native(false),

                        TextInput::make('country')
                            ->label('Country')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Italy, United Kingdom, France'),

                        Textarea::make('address')
                            ->label('Address')
                            ->nullable()
                            ->rows(3)
                            ->placeholder('Full postal address'),
                    ])
                    ->columns(2),

                Section::make('Settings')
                    ->schema([
                        Toggle::make('serialization_authorized')
                            ->label('Serialization Authorized')
                            ->helperText('Allow serialization of bottles at this location. Only authorized locations can perform serialization.')
                            ->default(false)
                            ->live(),

                        TextInput::make('linked_wms_id')
                            ->label('WMS ID')
                            ->nullable()
                            ->maxLength(255)
                            ->placeholder('External WMS system identifier')
                            ->helperText('Link to external Warehouse Management System'),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options(collect(LocationStatus::cases())
                                ->mapWithKeys(fn (LocationStatus $status) => [$status->value => $status->label()])
                                ->toArray())
                            ->default(LocationStatus::Active->value)
                            ->native(false),
                    ])
                    ->columns(3),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
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
                TextColumn::make('name')
                    ->label('Location Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('location_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (LocationType $state): string => $state->label())
                    ->color(fn (LocationType $state): string => $state->color())
                    ->icon(fn (LocationType $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('country')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('serialization_authorized')
                    ->label('Serialization')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('linked_wms_id')
                    ->label('WMS')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? 'Linked' : 'Not Linked')
                    ->color(fn (?string $state): string => $state !== null ? 'info' : 'gray')
                    ->icon(fn (?string $state): string => $state !== null ? 'heroicon-o-link' : 'heroicon-o-minus')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('linked_wms_id IS NULL '.$direction);
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (LocationStatus $state): string => $state->label())
                    ->color(fn (LocationStatus $state): string => $state->color())
                    ->icon(fn (LocationStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('stock_summary')
                    ->label('Stock')
                    ->state(fn (Location $record): string => (string) $record->serialized_bottles_count)
                    ->suffix(' bottles')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('serialized_bottles_count', $direction);
                    }),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('location_type')
                    ->options(collect(LocationType::cases())
                        ->mapWithKeys(fn (LocationType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Type'),

                SelectFilter::make('country')
                    ->options(fn (): array => Location::query()
                        ->distinct()
                        ->pluck('country', 'country')
                        ->toArray())
                    ->searchable()
                    ->label('Country'),

                TernaryFilter::make('serialization_authorized')
                    ->label('Serialization Authorized'),

                SelectFilter::make('status')
                    ->options(collect(LocationStatus::cases())
                        ->mapWithKeys(fn (LocationStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(LocationStatus::Active->value)
                    ->label('Status'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('serializedBottles'));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SerializedBottlesRelationManager::class,
            RelationManagers\CasesRelationManager::class,
            RelationManagers\InboundBatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'view' => ViewLocation::route('/{record}'),
            'edit' => EditLocation::route('/{record}/edit'),
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
