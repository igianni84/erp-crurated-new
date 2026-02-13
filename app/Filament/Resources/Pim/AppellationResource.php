<?php

namespace App\Filament\Resources\Pim;

use App\Enums\Pim\AppellationSystem;
use App\Filament\Resources\Pim\AppellationResource\Pages;
use App\Models\Pim\Appellation;
use App\Models\Pim\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppellationResource extends Resource
{
    protected static ?string $model = Appellation::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Appellations';

    protected static ?string $modelLabel = 'Appellation';

    protected static ?string $pluralModelLabel = 'Appellations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Appellation Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('country_id')
                            ->relationship('country', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('region_id', null);
                            }),
                        Forms\Components\Select::make('region_id')
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
                        Forms\Components\Select::make('system')
                            ->options(
                                collect(AppellationSystem::cases())
                                    ->mapWithKeys(fn (AppellationSystem $s) => [$s->value => $s->label()])
                            )
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable(),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('system')
                    ->badge()
                    ->formatStateUsing(fn (AppellationSystem $state): string => $state->label())
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('country_id')
                    ->relationship('country', 'name')
                    ->label('Country')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('system')
                    ->options(
                        collect(AppellationSystem::cases())
                            ->mapWithKeys(fn (AppellationSystem $s) => [$s->value => $s->label()])
                    ),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppellations::route('/'),
            'create' => Pages\CreateAppellation::route('/create'),
            'edit' => Pages\EditAppellation::route('/{record}/edit'),
        ];
    }
}
