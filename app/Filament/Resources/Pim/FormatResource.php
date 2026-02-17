<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\FormatResource\Pages\CreateFormat;
use App\Filament\Resources\Pim\FormatResource\Pages\EditFormat;
use App\Filament\Resources\Pim\FormatResource\Pages\ListFormats;
use App\Models\Pim\Format;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FormatResource extends Resource
{
    protected static ?string $model = Format::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Formats';

    protected static ?string $modelLabel = 'Format';

    protected static ?string $pluralModelLabel = 'Formats';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Format Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Standard Bottle, Magnum'),
                        TextInput::make('volume_ml')
                            ->label('Volume (ml)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('e.g., 750'),
                    ])
                    ->columns(2),
                Section::make('Settings')
                    ->schema([
                        Toggle::make('is_standard')
                            ->label('Standard Format')
                            ->helperText('Mark as a standard bottle format'),
                        Toggle::make('allowed_for_liquid_conversion')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('volume_ml')
                    ->label('Volume (ml)')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => number_format($state).' ml'),
                IconColumn::make('is_standard')
                    ->label('Standard')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('allowed_for_liquid_conversion')
                    ->label('Liquid Conversion')
                    ->boolean()
                    ->sortable(),
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
                TernaryFilter::make('is_standard')
                    ->label('Standard Format'),
                TernaryFilter::make('allowed_for_liquid_conversion')
                    ->label('Liquid Conversion'),
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
            'index' => ListFormats::route('/'),
            'create' => CreateFormat::route('/create'),
            'edit' => EditFormat::route('/{record}/edit'),
        ];
    }
}
