<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\LiquidProductResource\Pages\CreateLiquidProduct;
use App\Filament\Resources\Pim\LiquidProductResource\Pages\EditLiquidProduct;
use App\Filament\Resources\Pim\LiquidProductResource\Pages\ListLiquidProducts;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
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

class LiquidProductResource extends Resource
{
    protected static ?string $model = LiquidProduct::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Liquid Products';

    protected static ?string $modelLabel = 'Liquid Product';

    protected static ?string $pluralModelLabel = 'Liquid Products';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Product Reference')
                    ->schema([
                        Select::make('wine_variant_id')
                            ->label('Wine Variant')
                            ->relationship('wineVariant', 'id')
                            ->getOptionLabelFromRecordUsing(function (WineVariant $record): string {
                                /** @var WineMaster $wineMaster */
                                $wineMaster = $record->wineMaster;

                                return "{$wineMaster->name} {$record->vintage_year}";
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make('Equivalent Units')
                    ->schema([
                        KeyValue::make('allowed_equivalent_units')
                            ->label('Allowed Equivalent Units')
                            ->helperText('Define the equivalent units for this liquid product (e.g., liters, hectoliters)')
                            ->keyLabel('Unit')
                            ->valueLabel('Conversion Factor')
                            ->addActionLabel('Add Unit'),
                    ]),
                Section::make('Bottling Configuration')
                    ->schema([
                        KeyValue::make('allowed_final_formats')
                            ->label('Allowed Final Formats')
                            ->helperText('Specify allowed bottle formats for this liquid product')
                            ->keyLabel('Format ID')
                            ->valueLabel('Description')
                            ->addActionLabel('Add Format'),
                        KeyValue::make('allowed_case_configurations')
                            ->label('Allowed Case Configurations')
                            ->helperText('Specify allowed case configurations after bottling')
                            ->keyLabel('Configuration ID')
                            ->valueLabel('Description')
                            ->addActionLabel('Add Configuration'),
                        KeyValue::make('bottling_constraints')
                            ->label('Bottling Constraints')
                            ->helperText('Define any constraints for the bottling process')
                            ->keyLabel('Constraint')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Constraint'),
                    ])
                    ->columns(1),
                Section::make('Serialization')
                    ->schema([
                        Toggle::make('serialization_required')
                            ->label('Serialization Required')
                            ->default(true)
                            ->helperText('If enabled, individual bottles must be serialized after bottling'),
                    ]),
                Section::make('Lifecycle')
                    ->schema([
                        Select::make('lifecycle_status')
                            ->label('Lifecycle Status')
                            ->options([
                                'draft' => 'Draft',
                                'in_review' => 'In Review',
                                'approved' => 'Approved',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('wineVariant.wineMaster.name')
                    ->label('Wine')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('wineVariant.vintage_year')
                    ->label('Vintage')
                    ->sortable(),
                IconColumn::make('serialization_required')
                    ->label('Serialization')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'in_review' => 'In Review',
                        'approved' => 'Approved',
                        'published' => 'Published',
                        'archived' => 'Archived',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'in_review' => 'warning',
                        'approved' => 'info',
                        'published' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    })
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
                SelectFilter::make('wine_variant_id')
                    ->label('Wine Variant')
                    ->relationship('wineVariant', 'id')
                    ->getOptionLabelFromRecordUsing(function (WineVariant $record): string {
                        /** @var WineMaster $wineMaster */
                        $wineMaster = $record->wineMaster;

                        return "{$wineMaster->name} {$record->vintage_year}";
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'in_review' => 'In Review',
                        'approved' => 'Approved',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                TernaryFilter::make('serialization_required')
                    ->label('Serialization Required'),
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
            ->defaultSort('created_at', 'desc');
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
            'index' => ListLiquidProducts::route('/'),
            'create' => CreateLiquidProduct::route('/create'),
            'edit' => EditLiquidProduct::route('/{record}/edit'),
        ];
    }
}
