<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\LiquidProductResource\Pages;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LiquidProductResource extends Resource
{
    protected static ?string $model = LiquidProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Liquid Products';

    protected static ?string $modelLabel = 'Liquid Product';

    protected static ?string $pluralModelLabel = 'Liquid Products';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product Reference')
                    ->schema([
                        Forms\Components\Select::make('wine_variant_id')
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
                Forms\Components\Section::make('Equivalent Units')
                    ->schema([
                        Forms\Components\KeyValue::make('allowed_equivalent_units')
                            ->label('Allowed Equivalent Units')
                            ->helperText('Define the equivalent units for this liquid product (e.g., liters, hectoliters)')
                            ->keyLabel('Unit')
                            ->valueLabel('Conversion Factor')
                            ->addActionLabel('Add Unit'),
                    ]),
                Forms\Components\Section::make('Bottling Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('allowed_final_formats')
                            ->label('Allowed Final Formats')
                            ->helperText('Specify allowed bottle formats for this liquid product')
                            ->keyLabel('Format ID')
                            ->valueLabel('Description')
                            ->addActionLabel('Add Format'),
                        Forms\Components\KeyValue::make('allowed_case_configurations')
                            ->label('Allowed Case Configurations')
                            ->helperText('Specify allowed case configurations after bottling')
                            ->keyLabel('Configuration ID')
                            ->valueLabel('Description')
                            ->addActionLabel('Add Configuration'),
                        Forms\Components\KeyValue::make('bottling_constraints')
                            ->label('Bottling Constraints')
                            ->helperText('Define any constraints for the bottling process')
                            ->keyLabel('Constraint')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Constraint'),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Serialization')
                    ->schema([
                        Forms\Components\Toggle::make('serialization_required')
                            ->label('Serialization Required')
                            ->default(true)
                            ->helperText('If enabled, individual bottles must be serialized after bottling'),
                    ]),
                Forms\Components\Section::make('Lifecycle')
                    ->schema([
                        Forms\Components\Select::make('lifecycle_status')
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
                Tables\Columns\TextColumn::make('wineVariant.wineMaster.name')
                    ->label('Wine')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wineVariant.vintage_year')
                    ->label('Vintage')
                    ->sortable(),
                Tables\Columns\IconColumn::make('serialization_required')
                    ->label('Serialization')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lifecycle_status')
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
                Tables\Filters\SelectFilter::make('wine_variant_id')
                    ->label('Wine Variant')
                    ->relationship('wineVariant', 'id')
                    ->getOptionLabelFromRecordUsing(function (WineVariant $record): string {
                        /** @var WineMaster $wineMaster */
                        $wineMaster = $record->wineMaster;

                        return "{$wineMaster->name} {$record->vintage_year}";
                    })
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'in_review' => 'In Review',
                        'approved' => 'Approved',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\TernaryFilter::make('serialization_required')
                    ->label('Serialization Required'),
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
            'index' => Pages\ListLiquidProducts::route('/'),
            'create' => Pages\CreateLiquidProduct::route('/create'),
            'edit' => Pages\EditLiquidProduct::route('/{record}/edit'),
        ];
    }
}
