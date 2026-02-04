<?php

namespace App\Filament\Pages;

use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Fulfillment\ShipmentResource;
use App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderException;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class FulfillmentDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Fulfillment Overview';

    protected static ?string $navigationGroup = 'Fulfillment';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Fulfillment Overview';

    protected static string $view = 'filament.pages.fulfillment-dashboard';

    // ========================================
    // Widget A: SO by Status
    // ========================================

    /**
     * Get shipping order counts by status.
     *
     * @return array<string, int>
     */
    public function getShippingOrderStatusCounts(): array
    {
        $counts = [];
        foreach (ShippingOrderStatus::cases() as $status) {
            $counts[$status->value] = ShippingOrder::where('status', $status->value)->count();
        }

        return $counts;
    }

    /**
     * Get shipping order status metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getShippingOrderStatusMeta(): array
    {
        $meta = [];
        foreach (ShippingOrderStatus::cases() as $status) {
            $meta[$status->value] = [
                'label' => $status->label(),
                'color' => $status->color(),
                'icon' => $status->icon(),
            ];
        }

        return $meta;
    }

    // ========================================
    // Widget B: SOs Requiring Attention
    // ========================================

    /**
     * Get shipping orders with active exceptions.
     *
     * @return Collection<int, ShippingOrder>
     */
    public function getShippingOrdersWithExceptions(): Collection
    {
        return ShippingOrder::whereHas('exceptions', function ($query) {
            $query->where('status', ShippingOrderExceptionStatus::Active->value);
        })
            ->with(['customer', 'exceptions' => function ($query) {
                $query->where('status', ShippingOrderExceptionStatus::Active->value);
            }])
            ->orderBy('requested_ship_date', 'asc')
            ->take(10)
            ->get();
    }

    /**
     * Get shipping orders nearing requested ship date (within 3 days).
     *
     * @return Collection<int, ShippingOrder>
     */
    public function getShippingOrdersNearShipDate(): Collection
    {
        return ShippingOrder::whereIn('status', [
            ShippingOrderStatus::Draft->value,
            ShippingOrderStatus::Planned->value,
            ShippingOrderStatus::Picking->value,
        ])
            ->whereNotNull('requested_ship_date')
            ->where('requested_ship_date', '<=', Carbon::now()->addDays(3))
            ->with('customer')
            ->orderBy('requested_ship_date', 'asc')
            ->take(10)
            ->get();
    }

    /**
     * Get count of SOs requiring attention.
     *
     * @return array{with_exceptions: int, near_ship_date: int}
     */
    public function getAttentionCounts(): array
    {
        $exceptionsCount = ShippingOrder::whereHas('exceptions', function ($query) {
            $query->where('status', ShippingOrderExceptionStatus::Active->value);
        })->count();

        $nearShipDateCount = ShippingOrder::whereIn('status', [
            ShippingOrderStatus::Draft->value,
            ShippingOrderStatus::Planned->value,
            ShippingOrderStatus::Picking->value,
        ])
            ->whereNotNull('requested_ship_date')
            ->where('requested_ship_date', '<=', Carbon::now()->addDays(3))
            ->count();

        return [
            'with_exceptions' => $exceptionsCount,
            'near_ship_date' => $nearShipDateCount,
        ];
    }

    // ========================================
    // Widget C: Shipments Today/This Week
    // ========================================

    /**
     * Get shipment metrics.
     *
     * @return array{today: int, this_week: int, pending_confirmation: int}
     */
    public function getShipmentMetrics(): array
    {
        return [
            'today' => Shipment::whereDate('shipped_at', Carbon::today())->count(),
            'this_week' => Shipment::where('shipped_at', '>=', Carbon::now()->startOfWeek())->count(),
            'pending_confirmation' => Shipment::where('status', 'pending')->count(),
        ];
    }

    // ========================================
    // Widget D: Exception Summary
    // ========================================

    /**
     * Get exception counts by type.
     *
     * @return array<string, int>
     */
    public function getExceptionTypeCounts(): array
    {
        $counts = [];
        foreach (ShippingOrderExceptionType::cases() as $type) {
            $counts[$type->value] = ShippingOrderException::where('exception_type', $type->value)
                ->where('status', ShippingOrderExceptionStatus::Active->value)
                ->count();
        }

        return $counts;
    }

    /**
     * Get exception type metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getExceptionTypeMeta(): array
    {
        $meta = [];
        foreach (ShippingOrderExceptionType::cases() as $type) {
            $meta[$type->value] = [
                'label' => $type->label(),
                'color' => $type->color(),
                'icon' => $type->icon(),
            ];
        }

        return $meta;
    }

    /**
     * Get total active exceptions count.
     */
    public function getTotalActiveExceptions(): int
    {
        return ShippingOrderException::where('status', ShippingOrderExceptionStatus::Active->value)->count();
    }

    // ========================================
    // Quick Links and URLs
    // ========================================

    /**
     * Get URL to shipping orders list with status filter.
     */
    public function getShippingOrdersUrl(?string $status = null): string
    {
        $params = [];
        if ($status !== null) {
            $params['tableFilters'] = [
                'status' => [
                    'values' => [$status],
                ],
            ];
        }

        return ShippingOrderResource::getUrl('index', $params);
    }

    /**
     * Get URL to shipping orders list with on_hold filter.
     */
    public function getOnHoldShippingOrdersUrl(): string
    {
        return $this->getShippingOrdersUrl(ShippingOrderStatus::OnHold->value);
    }

    /**
     * Get URL to exceptions list.
     */
    public function getExceptionsUrl(): string
    {
        return ShippingOrderExceptionResource::getUrl('index');
    }

    /**
     * Get URL to exceptions list filtered by type.
     */
    public function getExceptionsByTypeUrl(string $type): string
    {
        return ShippingOrderExceptionResource::getUrl('index', [
            'tableFilters' => [
                'exception_type' => [
                    'values' => [$type],
                ],
            ],
        ]);
    }

    /**
     * Get URL to shipments list.
     */
    public function getShipmentsUrl(): string
    {
        return ShipmentResource::getUrl('index');
    }

    /**
     * Get URL to view a specific shipping order.
     */
    public function getShippingOrderViewUrl(ShippingOrder $shippingOrder): string
    {
        return ShippingOrderResource::getUrl('view', ['record' => $shippingOrder]);
    }
}
