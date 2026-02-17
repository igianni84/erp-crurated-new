<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class ProcurementPipelineWidget extends ChartWidget
{
    protected ?string $heading = 'Procurement Pipeline';

    protected ?string $description = 'Intents & Purchase Orders by status';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return Cache::remember('procurement_pipeline_chart', 60, function () {
            $intentCounts = [];
            $intentLabels = [];
            foreach (ProcurementIntentStatus::cases() as $status) {
                $intentLabels[] = $status->label();
                $intentCounts[] = ProcurementIntent::where('status', $status->value)->count();
            }

            $poCounts = [];
            $poLabels = [];
            foreach (PurchaseOrderStatus::cases() as $status) {
                $poLabels[] = $status->label();
                $poCounts[] = PurchaseOrder::where('status', $status->value)->count();
            }

            // Merge labels from both datasets
            $allLabels = array_unique(array_merge($intentLabels, $poLabels));
            $allLabels = array_values($allLabels);

            // Re-map counts to unified label set
            $intentMap = array_combine($intentLabels, $intentCounts);
            $poMap = array_combine($poLabels, $poCounts);

            $intentSeries = [];
            $poSeries = [];
            foreach ($allLabels as $label) {
                $intentSeries[] = $intentMap[$label] ?? 0;
                $poSeries[] = $poMap[$label] ?? 0;
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Procurement Intents',
                        'data' => $intentSeries,
                        'backgroundColor' => '#818cf8',
                    ],
                    [
                        'label' => 'Purchase Orders',
                        'data' => $poSeries,
                        'backgroundColor' => '#38bdf8',
                    ],
                ],
                'labels' => $allLabels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'bar';
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
