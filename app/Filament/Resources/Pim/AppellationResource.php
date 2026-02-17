<?php

namespace App\Filament\Resources\Pim;

use App\Enums\Pim\AppellationSystem;
use App\Filament\Resources\Pim\AppellationResource\Pages\CreateAppellation;
use App\Filament\Resources\Pim\AppellationResource\Pages\EditAppellation;
use App\Filament\Resources\Pim\AppellationResource\Pages\ListAppellations;
use App\Models\Pim\Appellation;
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

class AppellationResource extends Resource
{
    protected static ?string $model = Appellation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Appellations';

    protected static ?string $modelLabel = 'Appellation';

    protected static ?string $pluralModelLabel = 'Appellations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Appellation Details')
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
                        Select::make('system')
                            ->options(
                                collect(AppellationSystem::cases())
                                    ->mapWithKeys(fn (AppellationSystem $s) => [$s->value => $s->label()])
                            )
                            ->required(),
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
                TextColumn::make('system')
                    ->badge()
                    ->formatStateUsing(fn (AppellationSystem $state): string => $state->label())
                    ->sortable(),
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
                SelectFilter::make('system')
                    ->options(
                        collect(AppellationSystem::cases())
                            ->mapWithKeys(fn (AppellationSystem $s) => [$s->value => $s->label()])
                    ),
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
            'index' => ListAppellations::route('/'),
            'create' => CreateAppellation::route('/create'),
            'edit' => EditAppellation::route('/{record}/edit'),
        ];
    }
}
