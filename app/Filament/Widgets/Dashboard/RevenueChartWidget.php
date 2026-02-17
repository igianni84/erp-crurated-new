<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;

class RevenueChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Revenue Over Time';

    protected ?string $description = 'Invoiced vs payments received';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate = $this->pageFilters['endDate'] ?? now()->toDateString();

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $cacheKey = 'revenue_chart_'.md5(serialize([$startDate, $endDate]));

        return Cache::remember($cacheKey, 120, function () use ($start, $end) {
            // Generate all dates in the range
            $period = CarbonPeriod::create($start, $end);
            $labels = [];
            $invoicedData = [];
            $paymentsData = [];

            // Get invoiced amounts grouped by date
            $invoiced = Invoice::query()
                ->whereNotNull('issued_at')
                ->whereBetween('issued_at', [$start, $end])
                ->selectRaw('DATE(issued_at) as date, SUM(total_amount) as total')
                ->groupByRaw('DATE(issued_at)')
                ->pluck('total', 'date')
                ->toArray();

            // Get payments received grouped by date
            $payments = Payment::query()
                ->where('status', 'confirmed')
                ->whereBetween('received_at', [$start, $end])
                ->selectRaw('DATE(received_at) as date, SUM(amount) as total')
                ->groupByRaw('DATE(received_at)')
                ->pluck('total', 'date')
                ->toArray();

            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $labels[] = $date->format('M j');
                $invoicedData[] = round((float) ($invoiced[$key] ?? 0), 2);
                $paymentsData[] = round((float) ($payments[$key] ?? 0), 2);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Invoiced',
                        'data' => $invoicedData,
                        'borderColor' => '#6366f1',
                        'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                    [
                        'label' => 'Payments Received',
                        'data' => $paymentsData,
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
