<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\ProducerResource\Pages\CreateProducer;
use App\Filament\Resources\Pim\ProducerResource\Pages\EditProducer;
use App\Filament\Resources\Pim\ProducerResource\Pages\ListProducers;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProducerResource extends Resource
{
    protected static ?string $model = Producer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Producers';

    protected static ?string $modelLabel = 'Producer';

    protected static ?string $pluralModelLabel = 'Producers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Producer Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('country_id')
                            ->relationship('country', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('region_id', null);
                            }),
                        Select::make('region_id')
                            ->label('Region')
                            ->options(function (Get $get): array {
                                $countryId = $get('country_id');
                                if ($countryId === null) {
                                    return [];
                                }

                                return Region::where('country_id', $countryId)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Region $r) => [$r->id => $r->name])
                                    ->toArray();
                            })
                            ->searchable(),
                        TextInput::make('website')
                            ->url(),
                        Toggle::make('is_active')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->numeric(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable(),
                TextColumn::make('region.name')
                    ->label('Region')
                    ->toggleable(),
                TextColumn::make('wine_masters_count')
                    ->counts('wineMasters')
                    ->label('Wines'),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country')
                    ->searchable()
                    ->preload(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListProducers::route('/'),
            'create' => CreateProducer::route('/create'),
            'edit' => EditProducer::route('/{record}/edit'),
        ];
    }
}
