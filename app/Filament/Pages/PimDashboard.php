<?php

namespace App\Filament\Pages;

use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\WineVariantResource;
use App\Models\Pim\WineVariant;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PimDashboard extends Page
{
    protected ?string $maxContentWidth = 'full';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Data Quality';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = -1;

    protected static ?string $title = 'Data Quality Dashboard';

    protected static string $view = 'filament.pages.pim-dashboard';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $eventTypeFilter = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    /**
     * Get products grouped by lifecycle status.
     *
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $counts = [];
        foreach (ProductLifecycleStatus::cases() as $status) {
            $counts[$status->value] = WineVariant::where('lifecycle_status', $status->value)->count();
        }

        return $counts;
    }

    /**
     * Get status metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getStatusMeta(): array
    {
        $meta = [];
        foreach (ProductLifecycleStatus::cases() as $status) {
            $meta[$status->value] = [
                'label' => $status->label(),
                'color' => $status->color(),
                'icon' => $status->icon(),
            ];
        }

        return $meta;
    }

    /**
     * Get completeness distribution.
     *
     * @return array{low: int, medium: int, high: int}
     */
    public function getCompletenessDistribution(): array
    {
        $products = WineVariant::all();

        $distribution = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
        ];

        foreach ($products as $product) {
            $percentage = $product->getCompletenessPercentage();
            if ($percentage < 50) {
                $distribution['low']++;
            } elseif ($percentage <= 80) {
                $distribution['medium']++;
            } else {
                $distribution['high']++;
            }
        }

        return $distribution;
    }

    /**
     * Get blocking issues summary.
     *
     * @return array<string, int>
     */
    public function getBlockingIssuesSummary(): array
    {
        $products = WineVariant::all();

        $summary = [];
        foreach ($products as $product) {
            $issues = $product->getBlockingIssues();
            foreach ($issues as $issue) {
                /** @var string $message */
                $message = $issue['message'];
                if (! isset($summary[$message])) {
                    $summary[$message] = 0;
                }
                $summary[$message]++;
            }
        }

        arsort($summary);

        return $summary;
    }

    /**
     * Get products with blocking issues.
     *
     * @return Collection<int, WineVariant>
     */
    public function getBlockedProducts(): Collection
    {
        return WineVariant::all()->filter(function (WineVariant $product): bool {
            return $product->hasBlockingIssues();
        })->values();
    }

    /**
     * Get total counts for the dashboard.
     *
     * @return array{total: int, blocked: int, publishable: int}
     */
    public function getTotals(): array
    {
        $products = WineVariant::all();
        $blocked = 0;
        $publishable = 0;

        foreach ($products as $product) {
            if ($product->hasBlockingIssues()) {
                $blocked++;
            } else {
                $publishable++;
            }
        }

        return [
            'total' => $products->count(),
            'blocked' => $blocked,
            'publishable' => $publishable,
        ];
    }

    /**
     * Get URL to view a wine variant.
     */
    public function getProductViewUrl(WineVariant $product): string
    {
        return WineVariantResource::getUrl('view', ['record' => $product]);
    }

    /**
     * Get URL to edit a wine variant with specific tab focus.
     */
    public function getProductEditUrl(WineVariant $product, string $tab = ''): string
    {
        $url = WineVariantResource::getUrl('edit', ['record' => $product]);
        if ($tab !== '') {
            $url .= '?activeTab='.$tab;
        }

        return $url;
    }

    /**
     * Export blocked products with issues to CSV.
     */
    public function exportIssues(): StreamedResponse
    {
        $blockedProducts = $this->getBlockedProducts();

        return response()->streamDownload(function () use ($blockedProducts): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // CSV Header
            fputcsv($handle, [
                'Product ID',
                'Wine Name',
                'Vintage',
                'Status',
                'Completeness',
                'Blocking Issues',
                'Issue Details',
            ]);

            foreach ($blockedProducts as $product) {
                $wineMaster = $product->wineMaster;
                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                $issues = $product->getBlockingIssues();
                $issueMessages = array_column($issues, 'message');

                fputcsv($handle, [
                    $product->id,
                    $wineName,
                    (string) $product->vintage_year,
                    $product->lifecycle_status->label(),
                    $product->getCompletenessPercentage().'%',
                    count($issues),
                    implode('; ', $issueMessages),
                ]);
            }

            fclose($handle);
        }, 'pim-blocked-products-'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
