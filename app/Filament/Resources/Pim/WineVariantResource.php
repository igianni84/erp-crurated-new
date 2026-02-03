<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\WineVariantResource\Pages;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WineVariantResource extends Resource
{
    protected static ?string $model = WineVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Wine Variants';

    protected static ?string $modelLabel = 'Wine Variant';

    protected static ?string $pluralModelLabel = 'Wine Variants';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Wine Master')
                    ->schema([
                        Forms\Components\Select::make('wine_master_id')
                            ->label('Wine Master')
                            ->relationship('wineMaster', 'name')
                            ->getOptionLabelFromRecordUsing(fn (WineMaster $record): string => "{$record->name} ({$record->producer})")
                            ->searchable(['name', 'producer'])
                            ->preload()
                            ->required(),
                    ]),
                Forms\Components\Section::make('Vintage Information')
                    ->schema([
                        Forms\Components\TextInput::make('vintage_year')
                            ->label('Vintage Year')
                            ->required()
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(date('Y') + 1),
                        Forms\Components\TextInput::make('alcohol_percentage')
                            ->label('Alcohol %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Drinking Window')
                    ->schema([
                        Forms\Components\TextInput::make('drinking_window_start')
                            ->label('Start Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2200),
                        Forms\Components\TextInput::make('drinking_window_end')
                            ->label('End Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2200)
                            ->gte('drinking_window_start'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\KeyValue::make('critic_scores')
                            ->label('Critic Scores')
                            ->keyLabel('Critic')
                            ->valueLabel('Score')
                            ->reorderable()
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('production_notes')
                            ->label('Production Notes')
                            ->keyLabel('Note Type')
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
                Tables\Columns\TextColumn::make('wineMaster.name')
                    ->label('Wine Master')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wineMaster.producer')
                    ->label('Producer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vintage_year')
                    ->label('Vintage')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('alcohol_percentage')
                    ->label('Alcohol %')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('drinking_window_start')
                    ->label('Drink From')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('drinking_window_end')
                    ->label('Drink To')
                    ->sortable()
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
                Tables\Filters\SelectFilter::make('wine_master_id')
                    ->label('Wine Master')
                    ->relationship('wineMaster', 'name')
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
            ->defaultSort('vintage_year', 'desc');
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
            'index' => Pages\ListWineVariants::route('/'),
            'create' => Pages\CreateWineVariant::route('/create'),
            'edit' => Pages\EditWineVariant::route('/{record}/edit'),
        ];
    }
}
