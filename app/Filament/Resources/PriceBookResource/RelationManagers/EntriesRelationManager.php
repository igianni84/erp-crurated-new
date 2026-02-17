<?php

namespace App\Filament\Resources\PriceBookResource\RelationManagers;

use App\Enums\Commercial\PriceSource;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Pim\SellableSku;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Price Entries';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price Entry')
                    ->schema([
                        Select::make('sellable_sku_id')
                            ->label('Sellable SKU')
                            ->options(function (): array {
                                return SellableSku::query()
                                    ->with('wineVariant.wineMaster', 'format', 'caseConfiguration')
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn (SellableSku $sku): array => [
                                        $sku->id => self::formatSkuLabel($sku),
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return SellableSku::query()
                                    ->with('wineVariant.wineMaster', 'format', 'caseConfiguration')
                                    ->where('sku_code', 'like', "%{$search}%")
                                    ->orWhereHas('wineVariant.wineMaster', function ($query) use ($search): void {
                                        $query->where('name', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (SellableSku $sku): array => [
                                        $sku->id => self::formatSkuLabel($sku),
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->disabled(fn (?PriceBookEntry $record): bool => $record !== null),
                        TextInput::make('base_price')
                            ->label('Base Price')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix(fn (): string => $this->getOwnerRecord() instanceof PriceBook ? $this->getOwnerRecord()->currency : 'EUR')
                            ->helperText('The base price for this SKU in this price book'),
                        Select::make('source')
                            ->label('Source')
                            ->options(collect(PriceSource::cases())->mapWithKeys(fn (PriceSource $source): array => [
                                $source->value => $source->label(),
                            ]))
                            ->default(PriceSource::Manual->value)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Source is automatically set based on how the price was entered'),
                    ])
                    ->columns(1),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var PriceBook $priceBook */
        $priceBook = $this->getOwnerRecord();
        $isEditable = $priceBook->isEditable();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('sellableSku.sku_code')
                    ->label('SKU Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('sellableSku.wineVariant.wineMaster.name')
                    ->label('Wine')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('sellableSku.wineVariant.vintage_year')
                    ->label('Vintage')
                    ->sortable(),
                TextColumn::make('sellableSku.format.name')
                    ->label('Format')
                    ->sortable(),
                TextInputColumn::make('base_price')
                    ->label('Base Price')
                    ->rules(['required', 'numeric', 'min:0.01'])
                    ->sortable()
                    ->disabled(! $isEditable)
                    ->afterStateUpdated(function (PriceBookEntry $record): void {
                        // Update source to manual when price is edited
                        $record->source = PriceSource::Manual;
                        $record->policy_id = null;
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Price updated')
                            ->body('Price has been updated and source set to Manual.')
                            ->send();
                    }),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (PriceSource $state): string => $state->label())
                    ->color(fn (PriceSource $state): string => $state->color())
                    ->icon(fn (PriceSource $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('emp_value')
                    ->label('EMP Value')
                    ->getStateUsing(function (PriceBookEntry $record) use ($priceBook): string {
                        // Try to find EMP for this SKU in the price book's market
                        $emp = EstimatedMarketPrice::where('sellable_sku_id', $record->sellable_sku_id)
                            ->where('market', $priceBook->market)
                            ->first();

                        return $emp !== null ? number_format((float) $emp->emp_value, 2) : '—';
                    })
                    ->color('gray'),
                TextColumn::make('delta_vs_emp')
                    ->label('Delta vs EMP')
                    ->getStateUsing(function (PriceBookEntry $record) use ($priceBook): string {
                        $emp = EstimatedMarketPrice::where('sellable_sku_id', $record->sellable_sku_id)
                            ->where('market', $priceBook->market)
                            ->first();

                        $empValue = (float) $emp->emp_value;
                        if ($emp === null || $empValue <= 0) {
                            return '—';
                        }

                        $basePrice = (float) $record->base_price;
                        $delta = (($basePrice - $empValue) / $empValue) * 100;

                        return sprintf('%+.1f%%', $delta);
                    })
                    ->color(function (PriceBookEntry $record) use ($priceBook): string {
                        $emp = EstimatedMarketPrice::where('sellable_sku_id', $record->sellable_sku_id)
                            ->where('market', $priceBook->market)
                            ->first();

                        $empValue = $emp !== null ? (float) $emp->emp_value : 0.0;
                        if ($emp === null || $empValue <= 0) {
                            return 'gray';
                        }

                        $basePrice = (float) $record->base_price;
                        $delta = abs(($basePrice - $empValue) / $empValue) * 100;

                        if ($delta > 15) {
                            return 'danger';
                        }
                        if ($delta > 10) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(collect(PriceSource::cases())->mapWithKeys(fn (PriceSource $source): array => [
                        $source->value => $source->label(),
                    ])),
                TernaryFilter::make('has_emp')
                    ->label('Has EMP')
                    ->placeholder('All')
                    ->trueLabel('With EMP data')
                    ->falseLabel('Without EMP data')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('sellableSku', function ($q) use ($priceBook): void {
                            $q->whereHas('estimatedMarketPrices', function ($empQ) use ($priceBook): void {
                                $empQ->where('market', $priceBook->market);
                            });
                        }),
                        false: fn (Builder $query) => $query->whereHas('sellableSku', function ($q) use ($priceBook): void {
                            $q->whereDoesntHave('estimatedMarketPrices', function ($empQ) use ($priceBook): void {
                                $empQ->where('market', $priceBook->market);
                            });
                        }),
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Price')
                    ->icon('heroicon-o-plus')
                    ->visible($isEditable)
                    ->mutateDataUsing(function (array $data): array {
                        $data['source'] = PriceSource::Manual->value;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible($isEditable)
                    ->mutateDataUsing(function (array $data): array {
                        // Set source to manual when editing
                        $data['source'] = PriceSource::Manual->value;
                        $data['policy_id'] = null;

                        return $data;
                    }),
                DeleteAction::make()
                    ->visible($isEditable),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_adjust_percentage')
                        ->label('Adjust by %')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->color('warning')
                        ->visible($isEditable)
                        ->form([
                            TextInput::make('percentage')
                                ->label('Percentage Adjustment')
                                ->numeric()
                                ->required()
                                ->suffix('%')
                                ->helperText('Enter positive value to increase, negative to decrease (e.g., 10 or -5)'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $percentage = (float) $data['percentage'];
                            $multiplier = 1 + ($percentage / 100);
                            $updated = 0;

                            foreach ($records as $record) {
                                /** @var PriceBookEntry $record */
                                $currentPrice = (float) $record->base_price;
                                $newPrice = round($currentPrice * $multiplier, 2);
                                if ($newPrice > 0) {
                                    $record->base_price = (string) $newPrice;
                                    $record->source = PriceSource::Manual;
                                    $record->policy_id = null;
                                    $record->save();
                                    $updated++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Prices adjusted')
                                ->body("Adjusted {$updated} prices by {$percentage}%.")
                                ->send();
                        }),
                    BulkAction::make('bulk_set_price')
                        ->label('Set Fixed Price')
                        ->icon('heroicon-o-currency-euro')
                        ->color('info')
                        ->visible($isEditable)
                        ->form([
                            TextInput::make('price')
                                ->label('New Price')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->prefix(fn (): string => $this->getOwnerRecord() instanceof PriceBook ? $this->getOwnerRecord()->currency : 'EUR')
                                ->helperText('Set all selected entries to this price'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $newPrice = (float) $data['price'];
                            $updated = 0;
                            /** @var PriceBook $owner */
                            $owner = $this->getOwnerRecord();
                            $currency = $owner->currency;

                            foreach ($records as $record) {
                                /** @var PriceBookEntry $record */
                                $record->base_price = (string) $newPrice;
                                $record->source = PriceSource::Manual;
                                $record->policy_id = null;
                                $record->save();
                                $updated++;
                            }

                            Notification::make()
                                ->success()
                                ->title('Prices updated')
                                ->body("Set {$updated} prices to {$currency} {$newPrice}.")
                                ->send();
                        }),
                    DeleteBulkAction::make()
                        ->visible($isEditable),
                ]),
            ])
            ->defaultSort('sellableSku.sku_code')
            ->emptyStateHeading('No Price Entries')
            ->emptyStateDescription($isEditable
                ? 'Add price entries for Sellable SKUs in this price book.'
                : 'This price book has no price entries.')
            ->emptyStateIcon('heroicon-o-currency-euro')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Add Price')
                    ->icon('heroicon-o-plus')
                    ->visible($isEditable),
            ]);
    }

    /**
     * Format SKU label for display.
     */
    protected static function formatSkuLabel(SellableSku $sku): string
    {
        $parts = [$sku->sku_code];

        $wineVariant = $sku->wineVariant;
        if ($wineVariant !== null) {
            $wineMaster = $wineVariant->wineMaster;
            if ($wineMaster !== null) {
                $parts[] = $wineMaster->name;
            }
            $vintageYear = $wineVariant->getAttribute('vintage_year');
            if ($vintageYear !== null) {
                $parts[] = (string) $vintageYear;
            }
        }

        $format = $sku->format;
        if ($format !== null) {
            $parts[] = $format->name;
        }

        return implode(' - ', $parts);
    }
}
