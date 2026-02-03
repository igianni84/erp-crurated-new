<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\FormatResource\Pages;
use App\Models\Pim\Format;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FormatResource extends Resource
{
    protected static ?string $model = Format::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Formats';

    protected static ?string $modelLabel = 'Format';

    protected static ?string $pluralModelLabel = 'Formats';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Format Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Standard Bottle, Magnum'),
                        Forms\Components\TextInput::make('volume_ml')
                            ->label('Volume (ml)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('e.g., 750'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_standard')
                            ->label('Standard Format')
                            ->helperText('Mark as a standard bottle format'),
                        Forms\Components\Toggle::make('allowed_for_liquid_conversion')
                            ->label('Allowed for Liquid Conversion')
                            ->helperText('Can be used as final format for liquid products'),
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
                Tables\Columns\TextColumn::make('volume_ml')
                    ->label('Volume (ml)')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => number_format($state).' ml'),
                Tables\Columns\IconColumn::make('is_standard')
                    ->label('Standard')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('allowed_for_liquid_conversion')
                    ->label('Liquid Conversion')
                    ->boolean()
                    ->sortable(),
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
                Tables\Filters\TernaryFilter::make('is_standard')
                    ->label('Standard Format'),
                Tables\Filters\TernaryFilter::make('allowed_for_liquid_conversion')
                    ->label('Liquid Conversion'),
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
            ->defaultSort('volume_ml');
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
            'index' => Pages\ListFormats::route('/'),
            'create' => Pages\CreateFormat::route('/create'),
            'edit' => Pages\EditFormat::route('/{record}/edit'),
        ];
    }
}
