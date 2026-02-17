<?php

namespace App\Filament\Pages;

use App\Enums\Commercial\EmpConfidenceLevel;
use App\Enums\Commercial\EmpSource;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Pim\SellableSku;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class PricingIntelligenceDetail extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Pricing Intelligence Detail';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pricing-intelligence/{record}';

    protected string $view = 'filament.pages.pricing-intelligence-detail';

    public ?string $record = null;

    public ?SellableSku $sellableSku = null;

    /**
     * @var Collection<int, EstimatedMarketPrice>
     */
    public Collection $empRecords;

    /**
     * Filter for audit log date from.
     */
    public ?string $auditDateFrom = null;

    /**
     * Filter for audit log date until.
     */
    public ?string $auditDateUntil = null;

    /**
     * Filter for audit market.
     */
    public ?string $auditMarketFilter = null;

    public function mount(string $record): void
    {
        $this->record = $record;
        $this->sellableSku = SellableSku::with([
            'wineVariant.wineMaster',
            'format',
            'caseConfiguration',
        ])->findOrFail($record);

        $this->empRecords = EstimatedMarketPrice::where('sellable_sku_id', $record)
            ->orderBy('market')
            ->get();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pricing Intelligence: '.$this->getSkuLabel();
    }

    protected function getSkuLabel(): string
    {
        if ($this->sellableSku === null) {
            return 'Unknown SKU';
        }

        $wineVariant = $this->sellableSku->wineVariant;
        $wineName = $wineVariant !== null && $wineVariant->wineMaster !== null
            ? $wineVariant->wineMaster->name
            : 'Unknown Wine';
        $vintage = $wineVariant !== null ? (string) $wineVariant->vintage_year : '';
        $format = $this->sellableSku->format?->volume_ml ? ($this->sellableSku->format->volume_ml.'ml') : '';
        $caseConfig = $this->sellableSku->caseConfiguration;
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
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->sellableSku)
            ->components([
                Tabs::make('Pricing Intelligence Detail')
                    ->tabs([
                        $this->getEmpOverviewTab(),
                        $this->getComparisonsTab(),
                        $this->getMarketCoverageTab(),
                        $this->getSignalsAlertsTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: EMP Overview - current value, source breakdown, last update, trend chart.
     */
    protected function getEmpOverviewTab(): Tab
    {
        $primaryEmp = $this->empRecords->first();

        return Tab::make('EMP Overview')
            ->icon('heroicon-o-chart-bar')
            ->schema([
                Section::make('SKU Information')
                    ->description('Product details for this Sellable SKU')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('sku_code')
                                    ->label('SKU Code')
                                    ->copyable()
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('wine_name')
                                    ->label('Wine')
                                    ->getStateUsing(function (): string {
                                        $wineVariant = $this->sellableSku?->wineVariant;

                                        return $wineVariant !== null && $wineVariant->wineMaster !== null
                                            ? $wineVariant->wineMaster->name
                                            : 'Unknown';
                                    }),
                                TextEntry::make('vintage')
                                    ->label('Vintage')
                                    ->getStateUsing(function (): string {
                                        $wineVariant = $this->sellableSku?->wineVariant;

                                        return $wineVariant !== null ? (string) $wineVariant->vintage_year : '';
                                    }),
                                TextEntry::make('format_packaging')
                                    ->label('Format & Packaging')
                                    ->getStateUsing(function (): string {
                                        $format = $this->sellableSku?->format?->volume_ml ? ($this->sellableSku->format->volume_ml.'ml') : '';
                                        $caseConfig = $this->sellableSku?->caseConfiguration;
                                        if ($caseConfig !== null) {
                                            /** @var 'owc'|'oc'|'none' $caseType */
                                            $caseType = $caseConfig->case_type ?? 'none';
                                            $packaging = $caseConfig->bottles_per_case.'x '.match ($caseType) {
                                                'owc' => 'OWC',
                                                'oc' => 'OC',
                                                'none' => 'Loose',
                                            };

                                            return "{$format} / {$packaging}";
                                        }

                                        return $format;
                                    }),
                            ]),
                    ]),
                Section::make('Current EMP Values')
                    ->description('Estimated Market Prices by market')
                    ->schema([
                        TextEntry::make('emp_summary')
                            ->label('')
                            ->getStateUsing(function (): string {
                                if ($this->empRecords->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm py-4">No EMP data available for this SKU.</div>';
                                }

                                $html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                                foreach ($this->empRecords as $emp) {
                                    $freshnessColor = match ($emp->getFreshnessIndicator()) {
                                        'fresh' => 'text-green-600 dark:text-green-400',
                                        'recent' => 'text-yellow-600 dark:text-yellow-400',
                                        'stale' => 'text-red-600 dark:text-red-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };
                                    $freshnessIcon = match ($emp->getFreshnessIndicator()) {
                                        'fresh' => '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
                                        'recent' => '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                                        'stale' => '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                                        default => '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                                    };
                                    $confidenceColor = match ($emp->confidence_level) {
                                        EmpConfidenceLevel::High => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                        EmpConfidenceLevel::Medium => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                        EmpConfidenceLevel::Low => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                    };
                                    $sourceColor = match ($emp->source) {
                                        EmpSource::Livex => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                        EmpSource::Internal => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                        EmpSource::Composite => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
                                    };
                                    $lastUpdate = $emp->fetched_at?->format('M d, Y H:i') ?? 'Unknown';
                                    $empValue = number_format((float) $emp->emp_value, 2);

                                    $html .= <<<HTML
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{$emp->market}</span>
                                            <span class="{$freshnessColor}">{$freshnessIcon} {$emp->getFreshnessIndicator()}</span>
                                        </div>
                                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400 mb-2">€{$empValue}</div>
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {$sourceColor}">{$emp->source->label()}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {$confidenceColor}">{$emp->confidence_level->label()}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Last updated: {$lastUpdate}</div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Source Breakdown')
                    ->description('EMP data sources distribution')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('source_breakdown')
                            ->label('')
                            ->getStateUsing(function (): string {
                                if ($this->empRecords->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm">No data available.</div>';
                                }

                                $sourceGroups = $this->empRecords->groupBy(fn (EstimatedMarketPrice $emp) => $emp->source->value);

                                $html = '<div class="grid grid-cols-3 gap-4">';
                                foreach (EmpSource::cases() as $source) {
                                    $count = $sourceGroups->get($source->value)?->count() ?? 0;
                                    $percentage = $this->empRecords->count() > 0 ? round(($count / $this->empRecords->count()) * 100) : 0;
                                    $barColor = match ($source) {
                                        EmpSource::Livex => 'bg-blue-500',
                                        EmpSource::Internal => 'bg-purple-500',
                                        EmpSource::Composite => 'bg-indigo-500',
                                    };

                                    $html .= <<<HTML
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{$source->label()}</div>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{$count}</div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-2">
                                            <div class="{$barColor} h-2 rounded-full" style="width: {$percentage}%"></div>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{$percentage}%</div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Historical Trend')
                    ->description('EMP value trend over time (placeholder)')
                    ->icon('heroicon-o-chart-bar')
                    ->iconColor('info')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('trend_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => '<div class="text-gray-500 text-sm py-4"><strong>Historical trend chart coming soon.</strong><br>EMP historical data will be visualized here once history tracking is implemented. This will show value changes over time, allowing operators to identify pricing trends and make informed decisions.</div>')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 2: Comparisons - EMP vs Price Book prices, EMP vs active Offer prices.
     */
    protected function getComparisonsTab(): Tab
    {
        return Tab::make('Comparisons')
            ->icon('heroicon-o-scale')
            ->schema([
                Section::make('EMP vs Price Book Prices')
                    ->description('Compare estimated market prices with active Price Book prices')
                    ->schema([
                        TextEntry::make('price_book_comparison')
                            ->label('')
                            ->getStateUsing(function (): string {
                                if ($this->empRecords->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm py-4">No EMP data available for comparison.</div>';
                                }

                                return '<div class="text-gray-500 text-sm py-4"><strong>Price Book comparison coming soon.</strong><br>Once Price Books are implemented (US-009+), this section will display a comparison table showing:<ul class="list-disc ml-6 mt-2"><li>EMP value per market</li><li>Active Price Book price per channel/market</li><li>Delta (%) between EMP and Price Book price</li><li>Highlighting for significant deviations (>15%)</li></ul></div>';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('EMP vs Active Offer Prices')
                    ->description('Compare estimated market prices with active Offer prices')
                    ->schema([
                        TextEntry::make('offer_comparison')
                            ->label('')
                            ->getStateUsing(fn (): string => '<div class="text-gray-500 text-sm py-4"><strong>Offer price comparison coming soon.</strong><br>Once Offers are implemented (US-033+), this section will display active offer prices alongside EMP values, showing any discounts or benefits applied.</div>')
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Comparison Summary')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('info')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('comparison_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'EMP (Estimated Market Price) serves as a benchmark for pricing decisions. Comparing EMP with actual Price Book and Offer prices helps identify:<ul class="list-disc ml-6 mt-2"><li>Overpriced products (Price Book > EMP)</li><li>Underpriced products (Price Book < EMP)</li><li>Pricing opportunities and risks</li></ul>A deviation threshold of 15% is commonly used to flag significant discrepancies.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Tab 3: Market Coverage - markets with EMP, warnings for missing/stale data.
     */
    protected function getMarketCoverageTab(): Tab
    {
        return Tab::make('Market Coverage')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Section::make('Markets with EMP Data')
                    ->description('Overview of market coverage for this SKU')
                    ->schema([
                        TextEntry::make('market_coverage')
                            ->label('')
                            ->getStateUsing(function (): string {
                                if ($this->empRecords->isEmpty()) {
                                    return '<div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg"><div class="flex items-start"><svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg><div><strong class="text-yellow-800 dark:text-yellow-200">No Market Coverage</strong><p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">This SKU has no EMP data for any market. EMP data needs to be imported from external sources.</p></div></div></div>';
                                }

                                $html = '<div class="space-y-3">';
                                foreach ($this->empRecords as $emp) {
                                    $statusClass = match ($emp->getFreshnessIndicator()) {
                                        'fresh' => 'border-green-500 bg-green-50 dark:bg-green-900/20',
                                        'recent' => 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20',
                                        'stale' => 'border-red-500 bg-red-50 dark:bg-red-900/20',
                                        default => 'border-gray-500 bg-gray-50 dark:bg-gray-900/20',
                                    };
                                    $statusIcon = match ($emp->getFreshnessIndicator()) {
                                        'fresh' => '<svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
                                        'recent' => '<svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                                        'stale' => '<svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                                        default => '<svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                                    };
                                    $confidenceBadge = match ($emp->confidence_level) {
                                        EmpConfidenceLevel::High => '<span class="px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">High</span>',
                                        EmpConfidenceLevel::Medium => '<span class="px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">Medium</span>',
                                        EmpConfidenceLevel::Low => '<span class="px-2 py-0.5 text-xs font-medium rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">Low</span>',
                                    };
                                    $lastUpdate = $emp->fetched_at?->format('M d, Y H:i') ?? 'Unknown';
                                    $empValue = number_format((float) $emp->emp_value, 2);
                                    $daysSinceUpdate = $emp->fetched_at !== null ? (int) $emp->fetched_at->diffInDays(now()) : null;
                                    $daysText = $daysSinceUpdate !== null ? ($daysSinceUpdate === 0 ? 'Today' : "{$daysSinceUpdate} days ago") : 'Unknown';

                                    $html .= <<<HTML
                                    <div class="flex items-center justify-between p-4 border-l-4 rounded-lg {$statusClass}">
                                        <div class="flex items-center gap-4">
                                            <div>{$statusIcon}</div>
                                            <div>
                                                <div class="font-bold text-gray-900 dark:text-gray-100">{$emp->market}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">Updated: {$daysText}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-primary-600 dark:text-primary-400">€{$empValue}</div>
                                            <div class="mt-1">{$confidenceBadge}</div>
                                        </div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Data Quality Warnings')
                    ->description('Issues requiring attention')
                    ->schema([
                        TextEntry::make('data_warnings')
                            ->label('')
                            ->getStateUsing(function (): string {
                                $warnings = [];

                                // Check for stale data
                                $staleRecords = $this->empRecords->filter(fn (EstimatedMarketPrice $emp) => $emp->isStale());
                                if ($staleRecords->isNotEmpty()) {
                                    $markets = $staleRecords->pluck('market')->implode(', ');
                                    $warnings[] = [
                                        'type' => 'warning',
                                        'title' => 'Stale Data',
                                        'message' => "The following markets have EMP data older than 7 days: {$markets}. Consider refreshing the data.",
                                    ];
                                }

                                // Check for low confidence
                                $lowConfidence = $this->empRecords->filter(fn (EstimatedMarketPrice $emp) => $emp->confidence_level === EmpConfidenceLevel::Low);
                                if ($lowConfidence->isNotEmpty()) {
                                    $markets = $lowConfidence->pluck('market')->implode(', ');
                                    $warnings[] = [
                                        'type' => 'danger',
                                        'title' => 'Low Confidence Data',
                                        'message' => "The following markets have low confidence EMP data: {$markets}. Use these values with caution.",
                                    ];
                                }

                                // Check for missing fetched_at
                                $missingDate = $this->empRecords->filter(fn (EstimatedMarketPrice $emp) => $emp->fetched_at === null);
                                if ($missingDate->isNotEmpty()) {
                                    $markets = $missingDate->pluck('market')->implode(', ');
                                    $warnings[] = [
                                        'type' => 'gray',
                                        'title' => 'Missing Update Date',
                                        'message' => "The following markets have no recorded update date: {$markets}.",
                                    ];
                                }

                                if (empty($warnings) && $this->empRecords->isNotEmpty()) {
                                    return '<div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg"><div class="flex items-center"><svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span class="text-green-800 dark:text-green-200">All market data is current and high quality.</span></div></div>';
                                }

                                if (empty($warnings)) {
                                    return '<div class="text-gray-500 text-sm py-2">No market data to evaluate.</div>';
                                }

                                $html = '<div class="space-y-3">';
                                foreach ($warnings as $warning) {
                                    $bgClass = match ($warning['type']) {
                                        'danger' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700',
                                        'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-700',
                                        default => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
                                    };
                                    $titleClass = match ($warning['type']) {
                                        'danger' => 'text-red-800 dark:text-red-200',
                                        'warning' => 'text-yellow-800 dark:text-yellow-200',
                                        default => 'text-gray-800 dark:text-gray-200',
                                    };
                                    $textClass = match ($warning['type']) {
                                        'danger' => 'text-red-700 dark:text-red-300',
                                        'warning' => 'text-yellow-700 dark:text-yellow-300',
                                        default => 'text-gray-700 dark:text-gray-300',
                                    };
                                    $iconClass = match ($warning['type']) {
                                        'danger' => 'text-red-600 dark:text-red-400',
                                        'warning' => 'text-yellow-600 dark:text-yellow-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };

                                    $html .= <<<HTML
                                    <div class="p-4 border rounded-lg {$bgClass}">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 {$iconClass} mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                            <div>
                                                <strong class="{$titleClass}">{$warning['title']}</strong>
                                                <p class="text-sm {$textClass} mt-1">{$warning['message']}</p>
                                            </div>
                                        </div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 4: Signals & Alerts - outlier detection, significant deviations.
     */
    protected function getSignalsAlertsTab(): Tab
    {
        return Tab::make('Signals & Alerts')
            ->icon('heroicon-o-bell-alert')
            ->badge(fn (): ?int => $this->getAlertCount() > 0 ? $this->getAlertCount() : null)
            ->badgeColor('danger')
            ->schema([
                Section::make('Outlier Detection')
                    ->description('Identify anomalies in EMP data')
                    ->schema([
                        TextEntry::make('outlier_detection')
                            ->label('')
                            ->getStateUsing(function (): string {
                                if ($this->empRecords->count() < 2) {
                                    return '<div class="text-gray-500 text-sm py-4">Outlier detection requires EMP data for at least 2 markets to compare values.</div>';
                                }

                                $values = $this->empRecords->pluck('emp_value')->map(fn ($v) => (float) $v);
                                $avg = $values->avg();
                                $stdDev = $this->calculateStdDev($values->toArray());

                                if ($stdDev === null || $stdDev < 0.0001) {
                                    return '<div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg"><div class="flex items-center"><svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span class="text-green-800 dark:text-green-200">All EMP values are consistent across markets. No outliers detected.</span></div></div>';
                                }

                                $outliers = [];
                                foreach ($this->empRecords as $emp) {
                                    $value = (float) $emp->emp_value;
                                    $zScore = ($value - $avg) / $stdDev;
                                    if (abs($zScore) > 2) {
                                        $outliers[] = [
                                            'market' => $emp->market,
                                            'value' => $value,
                                            'deviation' => round(($value - $avg) / $avg * 100, 1),
                                            'direction' => $zScore > 0 ? 'above' : 'below',
                                        ];
                                    }
                                }

                                if (empty($outliers)) {
                                    $avgFormatted = number_format($avg, 2);

                                    return "<div class=\"p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg\"><div class=\"flex items-center\"><svg class=\"w-5 h-5 text-green-600 dark:text-green-400 mr-2\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M5 13l4 4L19 7\"></path></svg><span class=\"text-green-800 dark:text-green-200\">No outliers detected. Average EMP value: €{$avgFormatted}</span></div></div>";
                                }

                                $html = '<div class="space-y-3">';
                                foreach ($outliers as $outlier) {
                                    $deviationAbs = abs($outlier['deviation']);
                                    $directionText = $outlier['direction'] === 'above' ? 'above' : 'below';
                                    $directionIcon = $outlier['direction'] === 'above'
                                        ? '<svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>'
                                        : '<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>';
                                    $valueFormatted = number_format($outlier['value'], 2);
                                    $avgFormatted = number_format($avg, 2);

                                    $html .= <<<HTML
                                    <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                {$directionIcon}
                                                <span class="font-bold text-gray-900 dark:text-gray-100">{$outlier['market']}</span>
                                            </div>
                                            <span class="text-lg font-bold text-primary-600 dark:text-primary-400">€{$valueFormatted}</span>
                                        </div>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-2">{$deviationAbs}% {$directionText} average (€{$avgFormatted})</p>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Significant Deviations')
                    ->description('Price deviations requiring attention (placeholder)')
                    ->schema([
                        TextEntry::make('deviations_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => '<div class="text-gray-500 text-sm py-4"><strong>Price deviation alerts coming soon.</strong><br>Once Price Books and Offers are implemented, this section will display alerts for:<ul class="list-disc ml-6 mt-2"><li>Price Book prices deviating >15% from EMP</li><li>Offer prices significantly below market value</li><li>Cross-market price inconsistencies</li></ul></div>')
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Alert Configuration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->iconColor('gray')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('alert_config_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '<div class="text-gray-500 text-sm">Alert thresholds can be configured in the Commercial settings (US-008). Default deviation threshold: 15%.</div>')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - history of EMP updates.
     */
    protected function getAuditTab(): Tab
    {
        return Tab::make('Audit')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('EMP Update History')
                    ->description(fn (): string => $this->getAuditFilterDescription())
                    ->headerActions([
                        Action::make('filter_audit')
                            ->label('Filter')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Select::make('market')
                                    ->label('Market')
                                    ->placeholder('All markets')
                                    ->options(fn () => $this->empRecords->pluck('market', 'market')->toArray())
                                    ->default($this->auditMarketFilter),
                                DatePicker::make('date_from')
                                    ->label('From Date')
                                    ->default($this->auditDateFrom),
                                DatePicker::make('date_until')
                                    ->label('Until Date')
                                    ->default($this->auditDateUntil),
                            ])
                            ->action(function (array $data): void {
                                $this->auditMarketFilter = $data['market'] ?? null;
                                $this->auditDateFrom = $data['date_from'] ?? null;
                                $this->auditDateUntil = $data['date_until'] ?? null;
                            }),
                        Action::make('clear_filters')
                            ->label('Clear')
                            ->icon('heroicon-o-x-mark')
                            ->color('gray')
                            ->visible(fn (): bool => $this->auditMarketFilter !== null || $this->auditDateFrom !== null || $this->auditDateUntil !== null)
                            ->action(function (): void {
                                $this->auditMarketFilter = null;
                                $this->auditDateFrom = null;
                                $this->auditDateUntil = null;
                            }),
                    ])
                    ->schema([
                        TextEntry::make('emp_history')
                            ->label('')
                            ->getStateUsing(function (): string {
                                if ($this->empRecords->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm py-4">No EMP records available.</div>';
                                }

                                // Apply filters
                                $filtered = $this->empRecords;
                                if ($this->auditMarketFilter) {
                                    $filtered = $filtered->filter(fn (EstimatedMarketPrice $emp) => $emp->market === $this->auditMarketFilter);
                                }
                                if ($this->auditDateFrom) {
                                    $filtered = $filtered->filter(fn (EstimatedMarketPrice $emp) => $emp->fetched_at !== null && $emp->fetched_at->gte($this->auditDateFrom));
                                }
                                if ($this->auditDateUntil) {
                                    $filtered = $filtered->filter(fn (EstimatedMarketPrice $emp) => $emp->fetched_at !== null && $emp->fetched_at->lte($this->auditDateUntil));
                                }

                                if ($filtered->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm py-4">No EMP records match the current filters.</div>';
                                }

                                // Sort by fetched_at descending
                                $sorted = $filtered->sortByDesc(fn (EstimatedMarketPrice $emp) => $emp->fetched_at ?? now()->subYears(10));

                                $html = '<div class="space-y-3">';
                                foreach ($sorted as $emp) {
                                    $sourceColor = match ($emp->source) {
                                        EmpSource::Livex => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                        EmpSource::Internal => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                        EmpSource::Composite => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
                                    };
                                    $confidenceColor = match ($emp->confidence_level) {
                                        EmpConfidenceLevel::High => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                        EmpConfidenceLevel::Medium => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                        EmpConfidenceLevel::Low => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                    };
                                    $timestamp = $emp->fetched_at?->format('M d, Y H:i:s') ?? 'Unknown date';
                                    $empValue = number_format((float) $emp->emp_value, 2);

                                    $html .= <<<HTML
                                    <div class="flex items-start gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                {$emp->market}
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-lg font-bold text-primary-600 dark:text-primary-400">€{$empValue}</span>
                                                <span class="text-sm text-gray-500 dark:text-gray-400">{$timestamp}</span>
                                            </div>
                                            <div class="flex gap-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {$sourceColor}">{$emp->source->label()}</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {$confidenceColor}">{$emp->confidence_level->label()}</span>
                                            </div>
                                        </div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Audit Information')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('info')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'EMP data is imported from external sources (Liv-ex, internal calculations, composite indices). This audit view shows the current state of all EMP records for this SKU. Historical changes to EMP values will be tracked once history logging is implemented. EMP is read-only in Module S - changes can only be made through the import process.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['History of EMP updates for this SKU'];

        $filters = [];
        if ($this->auditMarketFilter) {
            $filters[] = "Market: {$this->auditMarketFilter}";
        }
        if ($this->auditDateFrom) {
            $filters[] = "From: {$this->auditDateFrom}";
        }
        if ($this->auditDateUntil) {
            $filters[] = "Until: {$this->auditDateUntil}";
        }

        if (! empty($filters)) {
            $parts[] = 'Filters: '.implode(', ', $filters);
        }

        return implode(' | ', $parts);
    }

    /**
     * Calculate standard deviation.
     *
     * @param  array<float>  $values
     */
    protected function calculateStdDev(array $values): ?float
    {
        $count = count($values);
        if ($count < 2) {
            return null;
        }

        $mean = array_sum($values) / $count;
        $squaredDiffs = array_map(fn ($v) => ($v - $mean) ** 2, $values);
        $variance = array_sum($squaredDiffs) / ($count - 1);

        return sqrt($variance);
    }

    /**
     * Get the count of alerts for the Signals & Alerts tab badge.
     */
    protected function getAlertCount(): int
    {
        $count = 0;

        // Count stale data
        $count += $this->empRecords->filter(fn (EstimatedMarketPrice $emp) => $emp->isStale())->count();

        // Count low confidence
        $count += $this->empRecords->filter(fn (EstimatedMarketPrice $emp) => $emp->confidence_level === EmpConfidenceLevel::Low)->count();

        // Count outliers (if we have enough data)
        if ($this->empRecords->count() >= 2) {
            $values = $this->empRecords->pluck('emp_value')->map(fn ($v) => (float) $v);
            $avg = $values->avg();
            $stdDev = $this->calculateStdDev($values->toArray());

            if ($stdDev !== null && $stdDev > 0) {
                foreach ($this->empRecords as $emp) {
                    $value = (float) $emp->emp_value;
                    $zScore = ($value - $avg) / $stdDev;
                    if (abs($zScore) > 2) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
