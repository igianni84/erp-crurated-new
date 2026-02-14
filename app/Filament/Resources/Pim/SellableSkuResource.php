<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\SellableSkuResource\Pages;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SellableSkuResource extends Resource
{
    protected static ?string $model = SellableSku::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Sellable SKUs';

    protected static ?string $modelLabel = 'Sellable SKU';

    protected static ?string $pluralModelLabel = 'Sellable SKUs';

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
                            ->required(),
                    ]),
                Forms\Components\Section::make('SKU Configuration')
                    ->schema([
                        Forms\Components\Select::make('format_id')
                            ->label('Format')
                            ->relationship('format', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Format $record): string => "{$record->name} ({$record->volume_ml} ml)")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('case_configuration_id')
                            ->label('Case Configuration')
                            ->relationship(
                                'caseConfiguration',
                                'name',
                                fn ($query, $get) => $query->when(
                                    $get('format_id'),
                                    fn ($q, $formatId) => $q->where('format_id', $formatId)
                                )
                            )
                            ->getOptionLabelFromRecordUsing(function (CaseConfiguration $record): string {
                                /** @var 'owc'|'oc'|'none' $caseTypeValue */
                                $caseTypeValue = $record->case_type;
                                $caseType = match ($caseTypeValue) {
                                    'owc' => 'OWC',
                                    'oc' => 'OC',
                                    'none' => 'Loose',
                                };

                                return "{$record->name} ({$record->bottles_per_case}x {$caseType})";
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Identifiers')
                    ->schema([
                        Forms\Components\TextInput::make('sku_code')
                            ->label('SKU Code')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-generated based on wine, vintage, format, and case configuration'),
                        Forms\Components\TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('lifecycle_status')
                            ->label('Lifecycle Status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'retired' => 'Retired',
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
                Tables\Columns\TextColumn::make('sku_code')
                    ->label('SKU Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('wineVariant.wineMaster.name')
                    ->label('Wine')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wineVariant.vintage_year')
                    ->label('Vintage')
                    ->sortable(),
                Tables\Columns\TextColumn::make('format.name')
                    ->label('Format')
                    ->sortable()
                    ->description(function (SellableSku $record): string {
                        /** @var Format $format */
                        $format = $record->format;

                        return $format->volume_ml.' ml';
                    }),
                Tables\Columns\TextColumn::make('caseConfiguration.name')
                    ->label('Case')
                    ->sortable()
                    ->description(function (SellableSku $record): string {
                        /** @var CaseConfiguration $caseConfig */
                        $caseConfig = $record->caseConfiguration;
                        /** @var 'owc'|'oc'|'none' $caseTypeValue */
                        $caseTypeValue = $caseConfig->case_type;
                        $caseType = match ($caseTypeValue) {
                            'owc' => 'OWC',
                            'oc' => 'OC',
                            'none' => 'Loose',
                        };

                        return "{$caseConfig->bottles_per_case}x {$caseType}";
                    }),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'retired' => 'Retired',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'active' => 'success',
                        'retired' => 'danger',
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
                Tables\Filters\SelectFilter::make('format_id')
                    ->label('Format')
                    ->relationship('format', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('case_configuration_id')
                    ->label('Case Configuration')
                    ->relationship('caseConfiguration', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'retired' => 'Retired',
                    ]),
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['sku_code', 'barcode', 'wineVariant.wineMaster.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['wineVariant.wineMaster', 'format']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var SellableSku $record */
        return $record->sku_code;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var SellableSku $record */
        $wineVariant = $record->wineVariant;
        $wineName = $wineVariant !== null && $wineVariant->wineMaster !== null
            ? $wineVariant->wineMaster->name
            : 'Unknown Wine';
        $vintage = $wineVariant !== null ? ($wineVariant->vintage_year ?? '') : '';
        $formatName = $record->format !== null ? $record->format->name : '';

        return [
            'Wine' => trim("{$wineName} {$vintage}"),
            'Format' => $formatName,
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellableSkus::route('/'),
            'create' => Pages\CreateSellableSku::route('/create'),
            'edit' => Pages\EditSellableSku::route('/{record}/edit'),
        ];
    }
}
