<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\WineMasterResource\Pages\CreateWineMaster;
use App\Filament\Resources\Pim\WineMasterResource\Pages\EditWineMaster;
use App\Filament\Resources\Pim\WineMasterResource\Pages\ListWineMasters;
use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use App\Models\Pim\WineMaster;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WineMasterResource extends Resource
{
    protected static ?string $model = WineMaster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Wine Masters';

    protected static ?string $modelLabel = 'Wine Master';

    protected static ?string $pluralModelLabel = 'Wine Masters';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Core Identity')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Select::make('producer_id')
                            ->label('Producer')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->options(
                                Producer::where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Producer $p): array => [$p->id => $p->name])
                                    ->toArray()
                            )
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== null) {
                                    $producer = Producer::find($state);
                                    if ($producer !== null) {
                                        $set('country_id', $producer->country_id);
                                        $set('region_id', $producer->region_id);
                                        $set('producer', $producer->name);
                                        $country = $producer->country;
                                        $set('country', $country !== null ? $country->name : '');
                                        $region = $producer->region;
                                        $set('region', $region !== null ? $region->name : '');
                                    }
                                }
                                $set('appellation_id', null);
                                $set('appellation', null);
                            })
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('country_id')
                                    ->label('Country')
                                    ->options(
                                        Country::where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn (Country $c): array => [$c->id => $c->name])
                                            ->toArray()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                Select::make('region_id')
                                    ->label('Region')
                                    ->options(function (Get $get): array {
                                        $countryId = $get('country_id');
                                        if ($countryId === null) {
                                            return [];
                                        }

                                        return Region::where('is_active', true)
                                            ->where('country_id', $countryId)
                                            ->orderBy('sort_order')
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn (Region $r): array => [$r->id => $r->name])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $producer = Producer::create([
                                    'name' => $data['name'],
                                    'country_id' => $data['country_id'] ?? null,
                                    'region_id' => $data['region_id'] ?? null,
                                    'is_active' => true,
                                    'sort_order' => 0,
                                ]);

                                return $producer->id;
                            }),

                        TextInput::make('classification')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Location')
                    ->schema([
                        Select::make('country_id')
                            ->label('Country')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->options(
                                Country::where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Country $c): array => [$c->id => $c->name])
                                    ->toArray()
                            )
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('region_id', null);
                                $set('appellation_id', null);
                                $set('appellation', null);
                                if ($state !== null) {
                                    $country = Country::find($state);
                                    $set('country', $country !== null ? $country->name : '');
                                } else {
                                    $set('country', '');
                                }
                            }),

                        Select::make('region_id')
                            ->label('Region')
                            ->searchable()
                            ->live()
                            ->options(function (Get $get): array {
                                $countryId = $get('country_id');
                                if ($countryId === null) {
                                    return [];
                                }

                                return Region::where('is_active', true)
                                    ->where('country_id', $countryId)
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function (Region $r): array {
                                        $parent = $r->parentRegion;
                                        $label = $parent !== null
                                            ? $parent->name.' > '.$r->name
                                            : $r->name;

                                        return [$r->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('appellation_id', null);
                                $set('appellation', null);
                                if ($state !== null) {
                                    $region = Region::find($state);
                                    $set('region', $region !== null ? $region->name : '');
                                } else {
                                    $set('region', null);
                                }
                            }),

                        Select::make('appellation_id')
                            ->label('Appellation')
                            ->searchable()
                            ->options(function (Get $get): array {
                                $countryId = $get('country_id');
                                if ($countryId === null) {
                                    return [];
                                }

                                $query = Appellation::where('is_active', true)
                                    ->where('country_id', $countryId);

                                $regionId = $get('region_id');
                                if ($regionId !== null) {
                                    $query->where(function ($q) use ($regionId): void {
                                        $q->where('region_id', $regionId)
                                            ->orWhereNull('region_id');
                                    });
                                }

                                return $query
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Appellation $a): array => [$a->id => $a->name])
                                    ->toArray();
                            })
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== null) {
                                    $appellation = Appellation::find($state);
                                    $set('appellation', $appellation !== null ? $appellation->name : null);
                                } else {
                                    $set('appellation', null);
                                }
                            }),
                    ])
                    ->columns(3),

                // Hidden legacy string fields kept in sync via afterStateUpdated callbacks
                Hidden::make('producer'),
                Hidden::make('country'),
                Hidden::make('region'),
                Hidden::make('appellation'),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('liv_ex_code')
                            ->label('Liv-ex Code')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        KeyValue::make('regulatory_attributes')
                            ->label('Regulatory Attributes')
                            ->keyLabel('Attribute')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('producerRelation.name')
                    ->label('Producer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('appellationRelation.name')
                    ->label('Appellation')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('countryRelation.name')
                    ->label('Country')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('regionRelation.name')
                    ->label('Region')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('classification')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('liv_ex_code')
                    ->label('Liv-ex Code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('countryRelation', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('producer_id')
                    ->label('Producer')
                    ->relationship('producerRelation', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'producerRelation.name', 'appellationRelation.name', 'liv_ex_code'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['producerRelation', 'appellationRelation']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var WineMaster $record */
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var WineMaster $record */
        return [
            'Producer' => $record->producerRelation !== null ? $record->producerRelation->name : '-',
            'Appellation' => $record->appellationRelation !== null ? $record->appellationRelation->name : '-',
            'Liv-ex' => $record->liv_ex_code ?? '-',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWineMasters::route('/'),
            'create' => CreateWineMaster::route('/create'),
            'edit' => EditWineMaster::route('/{record}/edit'),
        ];
    }
}
