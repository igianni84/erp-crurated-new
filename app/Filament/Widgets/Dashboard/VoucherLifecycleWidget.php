<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Voucher;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class VoucherLifecycleWidget extends ChartWidget
{
    protected ?string $heading = 'Voucher Lifecycle';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return Cache::remember('voucher_lifecycle_chart', 60, function () {
            $counts = [];
            $labels = [];
            $colors = [];

            $colorMap = [
                'success' => '#34d399',
                'warning' => '#fbbf24',
                'info' => '#38bdf8',
                'danger' => '#fb7185',
            ];

            foreach (VoucherLifecycleState::cases() as $state) {
                $count = Voucher::where('lifecycle_state', $state->value)->count();
                $counts[] = $count;
                $labels[] = $state->label();
                $colors[] = $colorMap[$state->color()] ?? '#94a3b8';
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

    public function getDescription(): ?string
    {
        $attentionCount = Voucher::where('requires_attention', true)->count();

        if ($attentionCount > 0) {
            return "{$attentionCount} require attention";
        }

        return null;
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
