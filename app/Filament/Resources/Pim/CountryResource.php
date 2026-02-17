<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\CountryResource\Pages\CreateCountry;
use App\Filament\Resources\Pim\CountryResource\Pages\EditCountry;
use App\Filament\Resources\Pim\CountryResource\Pages\ListCountries;
use App\Models\Pim\Country;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Countries';

    protected static ?string $modelLabel = 'Country';

    protected static ?string $pluralModelLabel = 'Countries';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Country Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('iso_code')
                            ->label('ISO Code (2-letter)')
                            ->required()
                            ->maxLength(2)
                            ->unique(ignoreRecord: true),
                        TextInput::make('iso_code_3')
                            ->label('ISO Code (3-letter)')
                            ->maxLength(3),
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
                TextColumn::make('iso_code')
                    ->label('ISO')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('regions_count')
                    ->counts('regions')
                    ->label('Regions'),
                TextColumn::make('producers_count')
                    ->counts('producers')
                    ->label('Producers'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            ->defaultSort('sort_order');
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
            'index' => ListCountries::route('/'),
            'create' => CreateCountry::route('/create'),
            'edit' => EditCountry::route('/{record}/edit'),
        ];
    }
}
