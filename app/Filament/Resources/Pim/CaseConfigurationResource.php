<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\CaseConfigurationResource\Pages;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CaseConfigurationResource extends Resource
{
    protected static ?string $model = CaseConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Case Configurations';

    protected static ?string $modelLabel = 'Case Configuration';

    protected static ?string $pluralModelLabel = 'Case Configurations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuration Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., 6x750ml OWC'),
                        Forms\Components\Select::make('format_id')
                            ->label('Format')
                            ->relationship('format', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Format $record): string => "{$record->name} ({$record->volume_ml} ml)")
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('bottles_per_case')
                            ->label('Bottles per Case')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('e.g., 6, 12'),
                        Forms\Components\Select::make('case_type')
                            ->label('Case Type')
                            ->options([
                                'owc' => 'OWC (Original Wooden Case)',
                                'oc' => 'OC (Original Carton)',
                                'none' => 'None (Loose)',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Packaging Flags')
                    ->schema([
                        Forms\Components\Toggle::make('is_original_from_producer')
                            ->label('Original from Producer')
                            ->helperText('This case configuration comes directly from the producer'),
                        Forms\Components\Toggle::make('is_breakable')
                            ->label('Breakable')
                            ->helperText('The case can be broken apart for individual bottle sales')
                            ->default(true),
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
                Tables\Columns\TextColumn::make('format.name')
                    ->label('Format')
                    ->sortable()
                    ->description(function (CaseConfiguration $record): string {
                        /** @var Format $format */
                        $format = $record->format;

                        return $format->volume_ml.' ml';
                    }),
                Tables\Columns\TextColumn::make('bottles_per_case')
                    ->label('Bottles')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('case_type')
                    ->label('Case Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owc' => 'OWC',
                        'oc' => 'OC',
                        'none' => 'Loose',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'owc' => 'success',
                        'oc' => 'info',
                        'none' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_original_from_producer')
                    ->label('Original')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_breakable')
                    ->label('Breakable')
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
                Tables\Filters\SelectFilter::make('format_id')
                    ->label('Format')
                    ->relationship('format', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('case_type')
                    ->label('Case Type')
                    ->options([
                        'owc' => 'OWC (Original Wooden Case)',
                        'oc' => 'OC (Original Carton)',
                        'none' => 'None (Loose)',
                    ]),
                Tables\Filters\TernaryFilter::make('is_original_from_producer')
                    ->label('Original from Producer'),
                Tables\Filters\TernaryFilter::make('is_breakable')
                    ->label('Breakable'),
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
            'index' => Pages\ListCaseConfigurations::route('/'),
            'create' => Pages\CreateCaseConfiguration::route('/create'),
            'edit' => Pages\EditCaseConfiguration::route('/{record}/edit'),
        ];
    }
}
