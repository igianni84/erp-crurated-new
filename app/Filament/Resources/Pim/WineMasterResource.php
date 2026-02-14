<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\WineMasterResource\Pages;
use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use App\Models\Pim\WineMaster;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WineMasterResource extends Resource
{
    protected static ?string $model = WineMaster::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Wine Masters';

    protected static ?string $modelLabel = 'Wine Master';

    protected static ?string $pluralModelLabel = 'Wine Masters';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Core Identity')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('producer_id')
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
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('country_id')
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
                                Forms\Components\Select::make('region_id')
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

                        Forms\Components\TextInput::make('classification')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\Select::make('country_id')
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

                        Forms\Components\Select::make('region_id')
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

                        Forms\Components\Select::make('appellation_id')
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
                Forms\Components\Hidden::make('producer'),
                Forms\Components\Hidden::make('country'),
                Forms\Components\Hidden::make('region'),
                Forms\Components\Hidden::make('appellation'),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('liv_ex_code')
                            ->label('Liv-ex Code')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\KeyValue::make('regulatory_attributes')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('producerRelation.name')
                    ->label('Producer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('appellationRelation.name')
                    ->label('Appellation')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('countryRelation.name')
                    ->label('Country')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('regionRelation.name')
                    ->label('Region')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('classification')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('liv_ex_code')
                    ->label('Liv-ex Code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('countryRelation', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('producer_id')
                    ->label('Producer')
                    ->relationship('producerRelation', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListWineMasters::route('/'),
            'create' => Pages\CreateWineMaster::route('/create'),
            'edit' => Pages\EditWineMaster::route('/{record}/edit'),
        ];
    }
}
