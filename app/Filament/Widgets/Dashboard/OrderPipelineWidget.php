<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Fulfillment\ShippingOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class OrderPipelineWidget extends ChartWidget
{
    protected ?string $heading = 'Order Pipeline';

    protected ?string $description = 'Shipping orders by status';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = '30s';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return Cache::remember('order_pipeline_chart', 30, function () {
            $counts = [];
            $labels = [];
            $colors = [];

            $colorMap = [
                'gray' => '#94a3b8',
                'info' => '#38bdf8',
                'warning' => '#fbbf24',
                'success' => '#34d399',
                'danger' => '#fb7185',
                'primary' => '#818cf8',
            ];

            foreach (ShippingOrderStatus::cases() as $status) {
                $count = ShippingOrder::where('status', $status->value)->count();
                if ($count > 0) {
                    $counts[] = $count;
                    $labels[] = $status->label();
                    $colors[] = $colorMap[$status->color()] ?? '#94a3b8';
                }
            }

            return [
                'datasets' => [
                    [
                        'data' => $counts,
                        'backgroundColor' => $colors,
                        'borderWidth' => 0,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'doughnut';
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
            'cutout' => '60%',
        ];
    }
}
