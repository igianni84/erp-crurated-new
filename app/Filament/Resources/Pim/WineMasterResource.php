<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\WineMasterResource\Pages;
use App\Models\Pim\WineMaster;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                        Forms\Components\TextInput::make('producer')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('appellation')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('classification')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\TextInput::make('country')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('region')
                            ->maxLength(255),
                    ])
                    ->columns(2),
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
                Tables\Columns\TextColumn::make('producer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('appellation')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('country')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('region')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWineMasters::route('/'),
            'create' => Pages\CreateWineMaster::route('/create'),
            'edit' => Pages\EditWineMaster::route('/{record}/edit'),
        ];
    }
}
