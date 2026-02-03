<?php

namespace App\Filament\Pages;

use App\Enums\Commercial\EmpConfidenceLevel;
use App\Models\Commercial\EstimatedMarketPrice;
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

        // Since Price Books and Offers are not yet implemented (US-009+, US-033+),
        // we track deviations as a placeholder showing what will be calculated
        // For now, we'll count EMP records as potential alerts based on data quality
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
     * @return array<array{label: string, url: string, icon: string, description: string}>
     */
    public function getQuickActions(): array
    {
        return [
            [
                'label' => 'View Pricing Intelligence',
                'url' => PricingIntelligence::getUrl(),
                'icon' => 'heroicon-o-presentation-chart-line',
                'description' => 'Explore EMP data for all SKUs',
            ],
        ];
    }

    /**
     * Check if Price Book feature is available.
     */
    public function isPriceBookAvailable(): bool
    {
        // Will be true after US-009 is implemented
        return false;
    }

    /**
     * Check if Offer feature is available.
     */
    public function isOfferAvailable(): bool
    {
        // Will be true after US-033 is implemented
        return false;
    }
}
