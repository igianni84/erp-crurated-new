<?php

namespace App\Filament\Resources\Pim;

use App\Filament\Resources\Pim\SellableSkuResource\Pages\CreateSellableSku;
use App\Filament\Resources\Pim\SellableSkuResource\Pages\EditSellableSku;
use App\Filament\Resources\Pim\SellableSkuResource\Pages\ListSellableSkus;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SellableSkuResource extends Resource
{
    protected static ?string $model = SellableSku::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Sellable SKUs';

    protected static ?string $modelLabel = 'Sellable SKU';

    protected static ?string $pluralModelLabel = 'Sellable SKUs';

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
                            ->required(),
                    ]),
                Section::make('SKU Configuration')
                    ->schema([
                        Select::make('format_id')
                            ->label('Format')
                            ->relationship('format', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Format $record): string => "{$record->name} ({$record->volume_ml} ml)")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('case_configuration_id')
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
                Section::make('Identifiers')
                    ->schema([
                        TextInput::make('sku_code')
                            ->label('SKU Code')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-generated based on wine, vintage, format, and case configuration'),
                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Status')
                    ->schema([
                        Select::make('lifecycle_status')
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
                TextColumn::make('sku_code')
                    ->label('SKU Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('wineVariant.wineMaster.name')
                    ->label('Wine')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('wineVariant.vintage_year')
                    ->label('Vintage')
                    ->sortable(),
                TextColumn::make('format.name')
                    ->label('Format')
                    ->sortable()
                    ->description(function (SellableSku $record): string {
                        /** @var Format $format */
                        $format = $record->format;

                        return $format->volume_ml.' ml';
                    }),
                TextColumn::make('caseConfiguration.name')
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
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lifecycle_status')
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
                SelectFilter::make('format_id')
                    ->label('Format')
                    ->relationship('format', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('case_configuration_id')
                    ->label('Case Configuration')
                    ->relationship('caseConfiguration', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'retired' => 'Retired',
                    ]),
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
            'index' => ListSellableSkus::route('/'),
            'create' => CreateSellableSku::route('/create'),
            'edit' => EditSellableSku::route('/{record}/edit'),
        ];
    }
}
