<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Allocation\Voucher;
use App\Models\Finance\Invoice;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderException;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class OperationalAlertsWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.operational-alerts-widget';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<int, array{label: string, count: int, severity: string, icon: string, url: string|null}>
     */
    public function getAlerts(): array
    {
        return Cache::remember('operational_alerts', 30, function () {
            $alerts = [];

            // Overdue Invoices
            $overdueInvoices = Invoice::query()
                ->where('status', 'issued')
                ->whereNotNull('due_date')
                ->where('due_date', '<', today())
                ->count();
            if ($overdueInvoices > 0) {
                $alerts[] = [
                    'label' => 'Overdue Invoices',
                    'count' => $overdueInvoices,
                    'severity' => 'danger',
                    'icon' => 'heroicon-o-banknotes',
                    'url' => $this->getResourceUrl('invoices', ['tableFilters[status][value]' => 'issued']),
                ];
            }

            // Disputed Invoices
            $disputedInvoices = Invoice::query()
                ->where('is_disputed', true)
                ->count();
            if ($disputedInvoices > 0) {
                $alerts[] = [
                    'label' => 'Disputed Invoices',
                    'count' => $disputedInvoices,
                    'severity' => 'danger',
                    'icon' => 'heroicon-o-exclamation-triangle',
                    'url' => $this->getResourceUrl('invoices'),
                ];
            }

            // Active SO Exceptions
            $activeExceptions = ShippingOrderException::query()
                ->where('status', 'active')
                ->count();
            if ($activeExceptions > 0) {
                $alerts[] = [
                    'label' => 'Active SO Exceptions',
                    'count' => $activeExceptions,
                    'severity' => 'danger',
                    'icon' => 'heroicon-o-exclamation-circle',
                    'url' => $this->getResourceUrl('shipping-order-exceptions'),
                ];
            }

            // Overdue Purchase Orders
            $overduePOs = PurchaseOrder::query()
                ->whereIn('status', ['sent', 'confirmed'])
                ->whereNotNull('expected_delivery_end')
                ->where('expected_delivery_end', '<', today())
                ->count();
            if ($overduePOs > 0) {
                $alerts[] = [
                    'label' => 'Overdue Purchase Orders',
                    'count' => $overduePOs,
                    'severity' => 'danger',
                    'icon' => 'heroicon-o-clipboard-document-list',
                    'url' => $this->getResourceUrl('procurement/purchase-orders'),
                ];
            }

            // SOs Near Ship Date (within 3 days)
            $nearShipDate = ShippingOrder::query()
                ->whereIn('status', ['draft', 'planned', 'picking'])
                ->whereNotNull('requested_ship_date')
                ->where('requested_ship_date', '<=', today()->addDays(3))
                ->where('requested_ship_date', '>=', today())
                ->count();
            if ($nearShipDate > 0) {
                $alerts[] = [
                    'label' => 'SOs Near Ship Date (3 days)',
                    'count' => $nearShipDate,
                    'severity' => 'warning',
                    'icon' => 'heroicon-o-clock',
                    'url' => $this->getResourceUrl('shipping-orders'),
                ];
            }

            // Draft Procurement Intents
            $draftIntents = ProcurementIntent::query()
                ->where('status', 'draft')
                ->count();
            if ($draftIntents > 0) {
                $alerts[] = [
                    'label' => 'Intents Awaiting Approval',
                    'count' => $draftIntents,
                    'severity' => 'warning',
                    'icon' => 'heroicon-o-document-text',
                    'url' => $this->getResourceUrl('procurement/intents'),
                ];
            }

            // Vouchers Requiring Attention
            $attentionVouchers = Voucher::query()
                ->where('requires_attention', true)
                ->count();
            if ($attentionVouchers > 0) {
                $alerts[] = [
                    'label' => 'Vouchers Requiring Attention',
                    'count' => $attentionVouchers,
                    'severity' => 'warning',
                    'icon' => 'heroicon-o-ticket',
                    'url' => $this->getResourceUrl('vouchers'),
                ];
            }

            // Xero Sync Pending
            $xeroSyncPending = Invoice::xeroSyncPending()->count();
            if ($xeroSyncPending > 0) {
                $alerts[] = [
                    'label' => 'Xero Sync Pending',
                    'count' => $xeroSyncPending,
                    'severity' => 'warning',
                    'icon' => 'heroicon-o-arrow-path',
                    'url' => $this->getResourceUrl('invoices'),
                ];
            }

            return $alerts;
        });
    }

    public function hasAlerts(): bool
    {
        return count($this->getAlerts()) > 0;
    }

    protected function getResourceUrl(string $slug, array $params = []): string
    {
        $url = '/admin/'.$slug;
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $url;
    }
}
