<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Dashboard\CustomerEngagementWidget;
use App\Filament\Widgets\Dashboard\ExecutiveKpiStatsWidget;
use App\Filament\Widgets\Dashboard\InventoryPositionWidget;
use App\Filament\Widgets\Dashboard\OperationalAlertsWidget;
use App\Filament\Widgets\Dashboard\OrderPipelineWidget;
use App\Filament\Widgets\Dashboard\ProcurementPipelineWidget;
use App\Filament\Widgets\Dashboard\RevenueChartWidget;
use App\Filament\Widgets\Dashboard\VoucherLifecycleWidget;
use App\Filament\Widgets\Finance\MonthlyFinancialSummaryWidget;
use App\Filament\Widgets\Finance\XeroSyncPendingWidget;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Support\Enums\Width;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected Width|string|null $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Start Date')
                        ->default(now()->startOfMonth()->toDateString()),
                    DatePicker::make('endDate')
                        ->label('End Date')
                        ->default(now()->toDateString()),
                ]),
        ];
    }

    public function getWidgets(): array
    {
        return [
            ExecutiveKpiStatsWidget::class,
            RevenueChartWidget::class,
            OrderPipelineWidget::class,
            InventoryPositionWidget::class,
            CustomerEngagementWidget::class,
            ProcurementPipelineWidget::class,
            VoucherLifecycleWidget::class,
            OperationalAlertsWidget::class,
            MonthlyFinancialSummaryWidget::class,
            XeroSyncPendingWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 4,
        ];
    }
}
