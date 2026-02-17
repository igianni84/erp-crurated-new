<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\CaseConfigurationResource\Pages\CreateCaseConfiguration;
use App\Filament\Resources\Pim\CaseConfigurationResource\Pages\EditCaseConfiguration;
use App\Filament\Resources\Pim\CaseConfigurationResource\Pages\ListCaseConfigurations;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CaseConfigurationResource extends Resource
{
    protected static ?string $model = CaseConfiguration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Case Configurations';

    protected static ?string $modelLabel = 'Case Configuration';

    protected static ?string $pluralModelLabel = 'Case Configurations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Configuration Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., 6x750ml OWC'),
                        Select::make('format_id')
                            ->label('Format')
                            ->relationship('format', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Format $record): string => "{$record->name} ({$record->volume_ml} ml)")
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('bottles_per_case')
                            ->label('Bottles per Case')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('e.g., 6, 12'),
                        Select::make('case_type')
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
                Section::make('Packaging Flags')
                    ->schema([
                        Toggle::make('is_original_from_producer')
                            ->label('Original from Producer')
                            ->helperText('This case configuration comes directly from the producer'),
                        Toggle::make('is_breakable')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('format.name')
                    ->label('Format')
                    ->sortable()
                    ->description(function (CaseConfiguration $record): string {
                        /** @var Format $format */
                        $format = $record->format;

                        return $format->volume_ml.' ml';
                    }),
                TextColumn::make('bottles_per_case')
                    ->label('Bottles')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('case_type')
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
                IconColumn::make('is_original_from_producer')
                    ->label('Original')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_breakable')
                    ->label('Breakable')
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
                SelectFilter::make('format_id')
                    ->label('Format')
                    ->relationship('format', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('case_type')
                    ->label('Case Type')
                    ->options([
                        'owc' => 'OWC (Original Wooden Case)',
                        'oc' => 'OC (Original Carton)',
                        'none' => 'None (Loose)',
                    ]),
                TernaryFilter::make('is_original_from_producer')
                    ->label('Original from Producer'),
                TernaryFilter::make('is_breakable')
                    ->label('Breakable'),
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
            'index' => ListCaseConfigurations::route('/'),
            'create' => CreateCaseConfiguration::route('/create'),
            'edit' => EditCaseConfiguration::route('/{record}/edit'),
        ];
    }
}
