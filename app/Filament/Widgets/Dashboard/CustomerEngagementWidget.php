<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\MembershipTier;
use App\Models\Customer\Customer;
use App\Models\Customer\Membership;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class CustomerEngagementWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard.customer-engagement-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<string, array{count: int, label: string, color: string}>
     */
    public function getCustomersByStatus(): array
    {
        return Cache::remember('customer_by_status', 60, function () {
            $results = [];

            foreach (CustomerStatus::cases() as $status) {
                $results[$status->value] = [
                    'count' => Customer::where('status', $status->value)->count(),
                    'label' => $status->label(),
                    'color' => $status->color(),
                ];
            }

            return $results;
        });
    }

    /**
     * @return array<string, array{count: int, label: string, color: string}>
     */
    public function getMembershipsByTier(): array
    {
        return Cache::remember('membership_by_tier', 60, function () {
            $results = [];

            foreach (MembershipTier::cases() as $tier) {
                $results[$tier->value] = [
                    'count' => Membership::where('tier', $tier->value)
                        ->where('status', 'approved')
                        ->count(),
                    'label' => $tier->label(),
                    'color' => $tier->color(),
                ];
            }

            return $results;
        });
    }

    public function getTotalCustomers(): int
    {
        return Cache::remember('total_customers', 60, function () {
            return Customer::count();
        });
    }

    public function getNewCustomersCount(): int
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;

        return Customer::query()
            ->when($startDate, fn (Builder $q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate('created_at', '<=', $endDate))
            ->when(! $startDate && ! $endDate, fn (Builder $q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->count();
    }
}
