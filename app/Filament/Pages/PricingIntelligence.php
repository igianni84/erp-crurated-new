<?php

namespace App\Filament\Pages;

use App\Enums\Commercial\EmpConfidenceLevel;
use App\Models\Commercial\EstimatedMarketPrice;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PricingIntelligence extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Pricing Intelligence';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Pricing Intelligence';

    protected static string $view = 'filament.pages.pricing-intelligence';

    /**
     * Deviation threshold for highlighting (percentage).
     */
    protected const DEVIATION_THRESHOLD = 15.0;

    public function table(Table $table): Table
    {
        return $table
            ->query(EstimatedMarketPrice::query()->with(['sellableSku.wineVariant.wineMaster', 'sellableSku.format', 'sellableSku.caseConfiguration']))
            ->columns([
                Tables\Columns\TextColumn::make('sellable_sku_label')
                    ->label('Sellable SKU')
                    ->getStateUsing(function (EstimatedMarketPrice $record): string {
                        $sku = $record->sellableSku;
                        if ($sku === null) {
                            return 'Unknown SKU';
                        }

                        $wineVariant = $sku->wineVariant;
                        $wineName = $wineVariant !== null && $wineVariant->wineMaster !== null
                            ? $wineVariant->wineMaster->name
                            : 'Unknown Wine';
                        $vintage = $wineVariant !== null ? (string) $wineVariant->vintage_year : '';
                        $format = $sku->format?->volume_ml ? ($sku->format->volume_ml.'ml') : '';
                        $caseConfig = $sku->caseConfiguration;
                        $packaging = '';
                        if ($caseConfig !== null) {
                            /** @var 'owc'|'oc'|'none' $caseType */
                            $caseType = $caseConfig->case_type ?? 'none';
                            $packaging = $caseConfig->bottles_per_case.'x '.match ($caseType) {
                                'owc' => 'OWC',
                                'oc' => 'OC',
                                'none' => 'Loose',
                            };
                        }

                        return collect([$wineName, $vintage, $format, $packaging])
                            ->filter()
                            ->implode(' · ');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('sellableSku', function (Builder $skuQuery) use ($search): void {
                            $skuQuery->where('sku_code', 'like', "%{$search}%")
                                ->orWhereHas('wineVariant', function (Builder $variantQuery) use ($search): void {
                                    $variantQuery->whereHas('wineMaster', function (Builder $wineQuery) use ($search): void {
                                        $wineQuery->where('name', 'like', "%{$search}%");
                                    });
                                });
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->join('sellable_skus', 'estimated_market_prices.sellable_sku_id', '=', 'sellable_skus.id')
                            ->leftJoin('wine_variants', 'sellable_skus.wine_variant_id', '=', 'wine_variants.id')
                            ->leftJoin('wine_masters', 'wine_variants.wine_master_id', '=', 'wine_masters.id')
                            ->orderBy('wine_masters.name', $direction)
                            ->select('estimated_market_prices.*');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('market')
                    ->label('Market')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('emp_value')
                    ->label('EMP Value')
                    ->money(fn (EstimatedMarketPrice $record): string => 'EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('confidence_level')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(fn (EmpConfidenceLevel $state): string => $state->label())
                    ->color(fn (EmpConfidenceLevel $state): string => $state->color())
                    ->icon(fn (EmpConfidenceLevel $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_price_book_price')
                    ->label('Price Book Price')
                    ->getStateUsing(fn (EstimatedMarketPrice $record): string => '—')
                    ->color('gray')
                    ->tooltip('Price Book integration coming in US-009+'),
                Tables\Columns\TextColumn::make('active_offer_price')
                    ->label('Offer Price')
                    ->getStateUsing(fn (EstimatedMarketPrice $record): string => '—')
                    ->color('gray')
                    ->tooltip('Offer integration coming in US-033+'),
                Tables\Columns\TextColumn::make('delta_vs_emp')
                    ->label('Delta vs EMP')
                    ->getStateUsing(fn (EstimatedMarketPrice $record): string => '—')
                    ->color('gray')
                    ->tooltip('Delta calculation available when Price Book is implemented'),
                Tables\Columns\TextColumn::make('freshness_indicator')
                    ->label('Freshness')
                    ->badge()
                    ->getStateUsing(fn (EstimatedMarketPrice $record): string => ucfirst($record->getFreshnessIndicator()))
                    ->color(fn (EstimatedMarketPrice $record): string => $record->getFreshnessColor())
                    ->icon(fn (EstimatedMarketPrice $record): string => match ($record->getFreshnessIndicator()) {
                        'fresh' => 'heroicon-o-check-circle',
                        'recent' => 'heroicon-o-clock',
                        'stale' => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-question-mark-circle',
                    }),
                Tables\Columns\TextColumn::make('fetched_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('market')
                    ->label('Market')
                    ->options(fn (): array => EstimatedMarketPrice::query()
                        ->distinct()
                        ->pluck('market', 'market')
                        ->toArray())
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('confidence_level')
                    ->label('Confidence Level')
                    ->options(collect(EmpConfidenceLevel::cases())->mapWithKeys(fn (EmpConfidenceLevel $level) => [
                        $level->value => $level->label(),
                    ]))
                    ->multiple(),
                Tables\Filters\Filter::make('deviation_high')
                    ->label('Significant Deviation (>15%)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query)
                    ->indicator('High Deviation'),
                Tables\Filters\Filter::make('stale_data')
                    ->label('Stale Data (>7 days)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('fetched_at', '<', now()->subDays(7)))
                    ->indicator('Stale Data'),
                Tables\Filters\Filter::make('missing_data')
                    ->label('Missing Fetched Date')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('fetched_at'))
                    ->indicator('Missing Date'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_detail')
                    ->label('View Detail')
                    ->icon('heroicon-o-eye')
                    ->url(fn (EstimatedMarketPrice $record): string => route('filament.admin.pages.pricing-intelligence-detail', ['record' => $record->id]))
                    ->openUrlInNewTab(false)
                    ->visible(false),
            ])
            ->emptyStateHeading('No EMP Data Found')
            ->emptyStateDescription('Estimated Market Prices are imported from external sources. No data has been imported yet.')
            ->emptyStateIcon('heroicon-o-presentation-chart-line')
            ->defaultSort('fetched_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Get statistics for the page header.
     *
     * @return array{total_records: int, markets_covered: int, stale_count: int, low_confidence_count: int}
     */
    public function getStatistics(): array
    {
        $query = EstimatedMarketPrice::query();

        return [
            'total_records' => $query->count(),
            'markets_covered' => $query->distinct('market')->count('market'),
            'stale_count' => $query->where('fetched_at', '<', now()->subDays(7))->orWhereNull('fetched_at')->count(),
            'low_confidence_count' => $query->where('confidence_level', EmpConfidenceLevel::Low->value)->count(),
        ];
    }
}
