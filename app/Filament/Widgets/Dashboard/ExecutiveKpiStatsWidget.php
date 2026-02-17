<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\BottleState;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Inventory\SerializedBottle;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ExecutiveKpiStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;

        $cacheKey = 'exec_kpi_'.md5(serialize([$startDate, $endDate]));

        $data = Cache::remember($cacheKey, 60, function () use ($startDate, $endDate) {
            return $this->computeStats($startDate, $endDate);
        });

        return [
            Stat::make('Revenue', 'EUR '.number_format($data['revenue'], 0, '.', ','))
                ->description($data['revenue_description'])
                ->descriptionIcon($data['revenue'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->chart($data['revenue_sparkline'])
                ->color('success'),

            Stat::make('Outstanding AR', 'EUR '.number_format($data['outstanding_ar'], 0, '.', ','))
                ->description($data['overdue_count'].' overdue')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($data['outstanding_ar'] > 0 ? 'warning' : 'success'),

            Stat::make('Active Shipping Orders', number_format($data['active_sos']))
                ->description($data['shipped_today'].' shipped today')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Bottles In Stock', number_format($data['bottles_stored']))
                ->description($data['bottles_reserved'].' reserved for picking')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success'),

            Stat::make('Active Vouchers', number_format($data['vouchers_issued']))
                ->description($data['vouchers_locked'].' locked')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Active Customers', number_format($data['active_customers']))
                ->description($data['new_customers'].' new this period')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function computeStats(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // Revenue in period
        $revenue = (float) Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$start, $end])
            ->sum('total_amount');

        // Revenue sparkline (last 7 days relative to end date)
        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::parse($end)->subDays($i);
            $sparkline[] = (float) Invoice::query()
                ->whereNotNull('issued_at')
                ->whereDate('issued_at', $day)
                ->sum('total_amount');
        }

        // Outstanding AR
        $outstandingAr = (float) Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as outstanding')
            ->value('outstanding');

        $overdueCount = Invoice::query()
            ->where('status', InvoiceStatus::Issued->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->count();

        // Active shipping orders
        $activeSos = ShippingOrder::query()
            ->whereIn('status', [
                ShippingOrderStatus::Draft->value,
                ShippingOrderStatus::Planned->value,
                ShippingOrderStatus::Picking->value,
            ])
            ->count();

        $shippedToday = ShippingOrder::query()
            ->where('status', ShippingOrderStatus::Shipped->value)
            ->whereDate('updated_at', today())
            ->count();

        // Bottles in stock
        $bottlesStored = SerializedBottle::query()
            ->where('state', BottleState::Stored->value)
            ->count();

        $bottlesReserved = SerializedBottle::query()
            ->where('state', BottleState::ReservedForPicking->value)
            ->count();

        // Active vouchers
        $vouchersIssued = Voucher::query()
            ->where('lifecycle_state', 'issued')
            ->count();

        $vouchersLocked = Voucher::query()
            ->where('lifecycle_state', 'locked')
            ->count();

        // Active customers
        $activeCustomers = Customer::query()
            ->where('status', 'active')
            ->count();

        $newCustomers = Customer::query()
            ->where('status', 'active')
            ->when($startDate, fn (Builder $q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('created_at', '<=', $endDate))
            ->when(! $startDate && ! $endDate, fn (Builder $q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->count();

        return [
            'revenue' => $revenue,
            'revenue_description' => 'Period total',
            'revenue_sparkline' => $sparkline,
            'outstanding_ar' => $outstandingAr,
            'overdue_count' => $overdueCount,
            'active_sos' => $activeSos,
            'shipped_today' => $shippedToday,
            'bottles_stored' => $bottlesStored,
            'bottles_reserved' => $bottlesReserved,
            'vouchers_issued' => $vouchersIssued,
            'vouchers_locked' => $vouchersLocked,
            'active_customers' => $activeCustomers,
            'new_customers' => $newCustomers,
        ];
    }
}
