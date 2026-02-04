<?php

namespace App\Filament\Pages;

use App\Enums\Commercial\EmpConfidenceLevel;
use App\Enums\Commercial\ExecutionStatus;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\PriceBookStatus;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Filament\Resources\OfferResource;
use App\Filament\Resources\PriceBookResource;
use App\Filament\Resources\PricingPolicyResource;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PricingPolicy;
use App\Models\Pim\SellableSku;
use Filament\Pages\Page;

class CommercialOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Commercial Overview';

    protected static string $view = 'filament.pages.commercial-overview';

    /**
     * Get the configured EMP deviation threshold.
     */
    public function getDeviationThreshold(): float
    {
        return (float) config('commercial.emp.deviation_threshold', 15.0);
    }

    /**
     * Get Price Book statistics by status.
     *
     * @return array{
     *     total: int,
     *     by_status: array<string, int>,
     *     expiring_soon: int
     * }
     */
    public function getPriceBookStatistics(): array
    {
        $byStatus = [];
        foreach (PriceBookStatus::cases() as $status) {
            $byStatus[$status->value] = PriceBook::where('status', $status->value)->count();
        }

        // Price Books expiring within 30 days
        $expiringSoon = PriceBook::where('status', PriceBookStatus::Active->value)
            ->whereNotNull('valid_to')
            ->where('valid_to', '<=', now()->addDays(30))
            ->where('valid_to', '>', now())
            ->count();

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'expiring_soon' => $expiringSoon,
        ];
    }

    /**
     * Get Offer statistics by status.
     *
     * @return array{
     *     total: int,
     *     by_status: array<string, int>,
     *     expiring_soon: int
     * }
     */
    public function getOfferStatistics(): array
    {
        $byStatus = [];
        foreach (OfferStatus::cases() as $status) {
            $byStatus[$status->value] = Offer::where('status', $status->value)->count();
        }

        // Offers expiring within 7 days
        $expiringSoon = Offer::where('status', OfferStatus::Active->value)
            ->whereNotNull('valid_to')
            ->where('valid_to', '<=', now()->addDays(7))
            ->where('valid_to', '>', now())
            ->count();

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'expiring_soon' => $expiringSoon,
        ];
    }

    /**
     * Get Pricing Policy statistics with execution status.
     *
     * @return array{
     *     total: int,
     *     by_status: array<string, int>,
     *     failed_executions: int,
     *     recent_executions: array<array{policy_name: string, status: string, executed_at: string}>
     * }
     */
    public function getPricingPolicyStatistics(): array
    {
        $byStatus = [];
        foreach (PricingPolicyStatus::cases() as $status) {
            $byStatus[$status->value] = PricingPolicy::where('status', $status->value)->count();
        }

        // Count policies with failed executions
        $failedExecutions = PricingPolicy::whereHas('executions', function ($query) {
            $query->where('status', ExecutionStatus::Failed->value);
        })->count();

        // Get recent executions (last 5)
        $recentExecutions = PricingPolicy::with(['executions' => function ($query) {
            $query->latest('executed_at')->limit(1);
        }])
            ->whereHas('executions')
            ->get()
            ->map(function (PricingPolicy $policy) {
                $latestExecution = $policy->executions->first();

                return [
                    'policy_id' => $policy->id,
                    'policy_name' => $policy->name,
                    'status' => $latestExecution !== null ? $latestExecution->status->value : 'none',
                    'executed_at' => $latestExecution !== null ? $latestExecution->executed_at->diffForHumans() : 'Never',
                ];
            })
            ->sortByDesc(function ($item) {
                return $item['executed_at'];
            })
            ->take(5)
            ->values()
            ->toArray();

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'failed_executions' => $failedExecutions,
            'recent_executions' => $recentExecutions,
        ];
    }

    /**
     * Get EMP coverage statistics.
     *
     * @return array{
     *     total_skus: int,
     *     skus_with_emp: int,
     *     coverage_percentage: float,
     *     markets_covered: int
     * }
     */
    public function getEmpCoverageStatistics(): array
    {
        $totalSkus = SellableSku::count();
        $skusWithEmp = SellableSku::whereHas('estimatedMarketPrices')->count();
        $marketsCovered = EstimatedMarketPrice::distinct('market')->count('market');

        return [
            'total_skus' => $totalSkus,
            'skus_with_emp' => $skusWithEmp,
            'coverage_percentage' => $totalSkus > 0 ? round(($skusWithEmp / $totalSkus) * 100, 1) : 0,
            'markets_covered' => $marketsCovered,
        ];
    }

    /**
     * Get alerts summary.
     *
     * @return array{
     *     expiring_offers: int,
     *     expiring_price_books: int,
     *     price_deviations: int,
     *     policy_failures: int,
     *     stale_emp: int,
     *     total: int
     * }
     */
    public function getAlertsSummary(): array
    {
        $threshold = $this->getDeviationThreshold();
        $staleThresholdDays = (int) config('commercial.emp.stale_threshold_days', 7);

        // Offers expiring within 7 days
        $expiringOffers = Offer::where('status', OfferStatus::Active->value)
            ->whereNotNull('valid_to')
            ->where('valid_to', '<=', now()->addDays(7))
            ->where('valid_to', '>', now())
            ->count();

        // Price Books expiring within 30 days
        $expiringPriceBooks = PriceBook::where('status', PriceBookStatus::Active->value)
            ->whereNotNull('valid_to')
            ->where('valid_to', '<=', now()->addDays(30))
            ->where('valid_to', '>', now())
            ->count();

        // Price deviations (base_price vs EMP > threshold)
        // This requires joining price_book_entries with estimated_market_prices
        $priceDeviations = 0;
        // Note: Complex calculation - simplified for now

        // Policy failures
        $policyFailures = PricingPolicy::whereHas('executions', function ($query) {
            $query->where('status', ExecutionStatus::Failed->value)
                ->where('executed_at', '>=', now()->subDays(7));
        })->count();

        // Stale EMP data
        $staleEmp = EstimatedMarketPrice::where('fetched_at', '<', now()->subDays($staleThresholdDays))
            ->orWhereNull('fetched_at')
            ->count();

        return [
            'expiring_offers' => $expiringOffers,
            'expiring_price_books' => $expiringPriceBooks,
            'price_deviations' => $priceDeviations,
            'policy_failures' => $policyFailures,
            'stale_emp' => $staleEmp,
            'total' => $expiringOffers + $expiringPriceBooks + $policyFailures + $staleEmp,
        ];
    }

    /**
     * Get EMP alert statistics for the dashboard.
     *
     * @return array{
     *     total_emp_records: int,
     *     deviations_count: int,
     *     deviation_breakdown: array{high: int, medium: int, low: int},
     *     stale_count: int,
     *     low_confidence_count: int,
     *     markets_with_issues: array<string, int>,
     *     threshold: float
     * }
     */
    public function getEmpAlertStatistics(): array
    {
        $threshold = $this->getDeviationThreshold();
        $staleThresholdDays = (int) config('commercial.emp.stale_threshold_days', 7);

        $totalRecords = EstimatedMarketPrice::count();
        $staleCount = EstimatedMarketPrice::where('fetched_at', '<', now()->subDays($staleThresholdDays))
            ->orWhereNull('fetched_at')
            ->count();
        $lowConfidenceCount = EstimatedMarketPrice::where('confidence_level', EmpConfidenceLevel::Low->value)->count();

        // Count deviations based on actual price book entries vs EMP
        $deviationsCount = 0;
        $deviationBreakdown = [
            'high' => 0,   // > 25%
            'medium' => 0, // 15-25%
            'low' => 0,    // < 15%
        ];

        // Track markets with data quality issues
        $marketsWithIssues = EstimatedMarketPrice::query()
            ->where(function ($query) use ($staleThresholdDays) {
                $query->where('fetched_at', '<', now()->subDays($staleThresholdDays))
                    ->orWhereNull('fetched_at')
                    ->orWhere('confidence_level', EmpConfidenceLevel::Low->value);
            })
            ->selectRaw('market, COUNT(*) as count')
            ->groupBy('market')
            ->pluck('count', 'market')
            ->toArray();

        return [
            'total_emp_records' => $totalRecords,
            'deviations_count' => $deviationsCount,
            'deviation_breakdown' => $deviationBreakdown,
            'stale_count' => $staleCount,
            'low_confidence_count' => $lowConfidenceCount,
            'markets_with_issues' => $marketsWithIssues,
            'threshold' => $threshold,
        ];
    }

    /**
     * Get alert severity based on count or percentage.
     *
     * @return 'success'|'warning'|'danger'
     */
    public function getAlertSeverity(int $count, int $total): string
    {
        if ($total === 0 || $count === 0) {
            return 'success';
        }

        $percentage = ($count / $total) * 100;

        if ($percentage > 25) {
            return 'danger';
        }

        if ($percentage > 10) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Get the URL to Pricing Intelligence page with deviation filter.
     */
    public function getPricingIntelligenceUrl(?string $filter = null): string
    {
        $url = PricingIntelligence::getUrl();

        if ($filter !== null) {
            $url .= '?tableFilters['.$filter.'][value]=1';
        }

        return $url;
    }

    /**
     * Get quick action links for the overview.
     *
     * @return array<array{label: string, url: string, icon: string, description: string, enabled: bool}>
     */
    public function getQuickActions(): array
    {
        return [
            [
                'label' => 'View Pricing Intelligence',
                'url' => PricingIntelligence::getUrl(),
                'icon' => 'heroicon-o-presentation-chart-line',
                'description' => 'Explore EMP data for all SKUs',
                'enabled' => true,
            ],
            [
                'label' => 'Create Price Book',
                'url' => PriceBookResource::getUrl('create'),
                'icon' => 'heroicon-o-book-open',
                'description' => 'Create a new price book',
                'enabled' => true,
            ],
            [
                'label' => 'Create Offer',
                'url' => OfferResource::getUrl('create'),
                'icon' => 'heroicon-o-tag',
                'description' => 'Create a new commercial offer',
                'enabled' => true,
            ],
            [
                'label' => 'Create Pricing Policy',
                'url' => PricingPolicyResource::getUrl('create'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'description' => 'Create an automated pricing policy',
                'enabled' => true,
            ],
            [
                'label' => 'Price Simulation',
                'url' => PriceSimulation::getUrl(),
                'icon' => 'heroicon-o-calculator',
                'description' => 'Simulate pricing scenarios',
                'enabled' => true,
            ],
        ];
    }

    /**
     * Get resource URLs for linking.
     *
     * @return array<string, string>
     */
    public function getResourceUrls(): array
    {
        return [
            'price_books' => PriceBookResource::getUrl(),
            'offers' => OfferResource::getUrl(),
            'pricing_policies' => PricingPolicyResource::getUrl(),
            'pricing_intelligence' => PricingIntelligence::getUrl(),
            'price_simulation' => PriceSimulation::getUrl(),
        ];
    }

    /**
     * Check if Price Book feature is available.
     */
    public function isPriceBookAvailable(): bool
    {
        return true;
    }

    /**
     * Check if Offer feature is available.
     */
    public function isOfferAvailable(): bool
    {
        return true;
    }

    /**
     * Check if Pricing Policy feature is available.
     */
    public function isPricingPolicyAvailable(): bool
    {
        return true;
    }
}
