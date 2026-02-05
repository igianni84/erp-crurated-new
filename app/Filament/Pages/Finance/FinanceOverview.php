<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Enums\Finance\RefundStatus;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Services\Finance\XeroIntegrationService;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * US-E116: Finance Overview Dashboard
 * US-E117: Dashboard quick actions
 * US-E118: Dashboard recent activity feed
 *
 * The default landing page for the Finance module, providing a quick
 * overview of key financial metrics:
 * - Total Outstanding
 * - Overdue Amount
 * - Payments This Month
 * - Pending Reconciliations
 * - Integration Health (Stripe, Xero status)
 *
 * Quick Actions (US-E117):
 * - View Overdue Invoices
 * - Pending Reconciliations
 * - Failed Syncs
 * - Generate Storage Billing
 *
 * Recent Activity Feed (US-E118):
 * - Invoices issued in last 24 hours
 * - Payments received in last 24 hours
 * - Credit notes issued in last 24 hours
 * - Refunds processed in last 24 hours
 * - Click to navigate to detail pages
 */
class FinanceOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Finance Overview';

    protected static string $view = 'filament.pages.finance.finance-overview';

    // =========================================================================
    // Header Actions
    // =========================================================================

    /**
     * Get header actions for the page.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getRefreshAction(),
        ];
    }

    /**
     * Get the refresh action.
     */
    protected function getRefreshAction(): Action
    {
        return Action::make('refresh')
            ->label('Refresh')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->action(fn () => $this->dispatch('$refresh'));
    }

    // =========================================================================
    // Quick Actions (US-E117)
    // =========================================================================

    /**
     * Get quick actions for the dashboard.
     *
     * @return array<array{label: string, description: string, icon: string, url: string, color: string, badge: int|null}>
     */
    public function getQuickActions(): array
    {
        $overdueCount = $this->getOverdueInvoicesCount();
        $pendingReconciliations = $this->getPendingReconciliationsCount();
        $failedSyncs = $this->getFailedSyncsCount();

        return [
            [
                'label' => 'View Overdue Invoices',
                'description' => 'Review invoices that are past due date',
                'icon' => 'heroicon-o-exclamation-triangle',
                'url' => $this->getOverdueInvoicesUrl(),
                'color' => $overdueCount > 0 ? 'danger' : 'gray',
                'badge' => $overdueCount > 0 ? $overdueCount : null,
            ],
            [
                'label' => 'Pending Reconciliations',
                'description' => 'Payments awaiting reconciliation',
                'icon' => 'heroicon-o-document-magnifying-glass',
                'url' => $this->getPendingReconciliationsUrl(),
                'color' => $pendingReconciliations > 0 ? 'warning' : 'gray',
                'badge' => $pendingReconciliations > 0 ? $pendingReconciliations : null,
            ],
            [
                'label' => 'Failed Syncs',
                'description' => 'Review failed Stripe/Xero integrations',
                'icon' => 'heroicon-o-arrow-path-rounded-square',
                'url' => $this->getFailedSyncsUrl(),
                'color' => $failedSyncs > 0 ? 'danger' : 'gray',
                'badge' => $failedSyncs > 0 ? $failedSyncs : null,
            ],
            [
                'label' => 'Generate Storage Billing',
                'description' => 'Preview and generate storage invoices',
                'icon' => 'heroicon-o-archive-box',
                'url' => $this->getStorageBillingUrl(),
                'color' => 'primary',
                'badge' => null,
            ],
        ];
    }

    /**
     * Get URL for overdue invoices view.
     */
    public function getOverdueInvoicesUrl(): string
    {
        return route('filament.admin.resources.finance.invoices.index', [
            'activeTab' => 'overdue',
        ]);
    }

    /**
     * Get URL for pending reconciliations view.
     */
    public function getPendingReconciliationsUrl(): string
    {
        return route('filament.admin.resources.finance.payments.index', [
            'tableFilters' => [
                'reconciliation_status' => ['value' => 'pending'],
            ],
        ]);
    }

    /**
     * Get URL for failed syncs view.
     */
    public function getFailedSyncsUrl(): string
    {
        return route('filament.admin.pages.finance.integrations-health');
    }

    /**
     * Get URL for storage billing preview.
     */
    public function getStorageBillingUrl(): string
    {
        return route('filament.admin.pages.finance.storage-billing-preview');
    }

    /**
     * Get total count of failed syncs (Stripe + Xero).
     */
    public function getFailedSyncsCount(): int
    {
        $stripeHealth = $this->getStripeHealthSummary();
        $xeroHealth = $this->getXeroHealthSummary();

        return $stripeHealth['failed_count'] + $xeroHealth['failed_count'];
    }

    // =========================================================================
    // Outstanding Metrics
    // =========================================================================

    /**
     * Get total outstanding amount across all unpaid invoices.
     */
    public function getTotalOutstanding(): string
    {
        /** @var string|null $totalOutstanding */
        $totalOutstanding = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
            ])
            ->selectRaw('SUM(total_amount - amount_paid) as outstanding')
            ->value('outstanding');

        return number_format((float) ($totalOutstanding ?? '0'), 2, '.', '');
    }

    /**
     * Get count of invoices with outstanding amount.
     */
    public function getOutstandingInvoicesCount(): int
    {
        return Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
            ])
            ->count();
    }

    // =========================================================================
    // Overdue Metrics
    // =========================================================================

    /**
     * Get total overdue amount.
     */
    public function getOverdueAmount(): string
    {
        /** @var string|null $overdueAmount */
        $overdueAmount = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->selectRaw('SUM(total_amount - amount_paid) as overdue')
            ->value('overdue');

        return number_format((float) ($overdueAmount ?? '0'), 2, '.', '');
    }

    /**
     * Get count of overdue invoices.
     */
    public function getOverdueInvoicesCount(): int
    {
        return Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->count();
    }

    // =========================================================================
    // Payments This Month
    // =========================================================================

    /**
     * Get total payments received this month.
     */
    public function getPaymentsThisMonth(): string
    {
        $paymentsAmount = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereMonth('received_at', now()->month)
            ->whereYear('received_at', now()->year)
            ->sum('amount');

        return number_format((float) $paymentsAmount, 2, '.', '');
    }

    /**
     * Get count of payments received this month.
     */
    public function getPaymentsThisMonthCount(): int
    {
        return Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereMonth('received_at', now()->month)
            ->whereYear('received_at', now()->year)
            ->count();
    }

    /**
     * Get payments comparison with last month.
     *
     * @return array{amount: string, change: float, direction: string}
     */
    public function getPaymentsComparison(): array
    {
        $thisMonth = (float) $this->getPaymentsThisMonth();

        $lastMonthAmount = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereMonth('received_at', now()->subMonth()->month)
            ->whereYear('received_at', now()->subMonth()->year)
            ->sum('amount');

        $lastMonth = (float) $lastMonthAmount;

        if ($lastMonth === 0.0) {
            $change = $thisMonth > 0 ? 100.0 : 0.0;
            $direction = $thisMonth > 0 ? 'up' : 'neutral';
        } else {
            $change = (($thisMonth - $lastMonth) / $lastMonth) * 100;
            $direction = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral');
        }

        return [
            'amount' => number_format($lastMonth, 2, '.', ''),
            'change' => abs($change),
            'direction' => $direction,
        ];
    }

    // =========================================================================
    // Reconciliation Metrics
    // =========================================================================

    /**
     * Get count of pending reconciliations.
     */
    public function getPendingReconciliationsCount(): int
    {
        return Payment::query()
            ->where('reconciliation_status', ReconciliationStatus::Pending)
            ->count();
    }

    /**
     * Get count of mismatched reconciliations.
     */
    public function getMismatchedReconciliationsCount(): int
    {
        return Payment::query()
            ->where('reconciliation_status', ReconciliationStatus::Mismatched)
            ->count();
    }

    /**
     * Get total amount pending reconciliation.
     */
    public function getPendingReconciliationAmount(): string
    {
        $amount = Payment::query()
            ->where('reconciliation_status', ReconciliationStatus::Pending)
            ->sum('amount');

        return number_format((float) $amount, 2, '.', '');
    }

    // =========================================================================
    // Integration Health
    // =========================================================================

    /**
     * Get Stripe integration health summary.
     *
     * @return array{
     *     status: string,
     *     status_color: string,
     *     last_webhook: string|null,
     *     failed_count: int,
     *     pending_count: int
     * }
     */
    public function getStripeHealthSummary(): array
    {
        $lastWebhook = \App\Models\Finance\StripeWebhook::query()
            ->orderBy('created_at', 'desc')
            ->first();

        $failedCount = \App\Models\Finance\StripeWebhook::failed()->count();
        $pendingCount = \App\Models\Finance\StripeWebhook::pending()
            ->whereNull('error_message')
            ->count();

        $oneHourAgo = now()->subHour();
        $hasNoRecent = ! \App\Models\Finance\StripeWebhook::query()
            ->where('created_at', '>=', $oneHourAgo)
            ->exists();

        $status = 'healthy';
        $statusColor = 'success';

        if ($lastWebhook === null) {
            $status = 'unknown';
            $statusColor = 'gray';
        } elseif ($hasNoRecent) {
            $status = 'warning';
            $statusColor = 'warning';
        }

        if ($failedCount > 0) {
            $status = $status === 'healthy' ? 'warning' : $status;
            $statusColor = $statusColor === 'success' ? 'warning' : $statusColor;
        }

        if ($failedCount > 10) {
            $status = 'critical';
            $statusColor = 'danger';
        }

        return [
            'status' => $status,
            'status_color' => $statusColor,
            'last_webhook' => $lastWebhook?->created_at?->diffForHumans(),
            'failed_count' => $failedCount,
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * Get Xero integration health summary.
     *
     * @return array{
     *     status: string,
     *     status_color: string,
     *     sync_enabled: bool,
     *     pending_count: int,
     *     failed_count: int,
     *     last_sync: \Carbon\Carbon|null
     * }
     */
    public function getXeroHealthSummary(): array
    {
        $health = app(XeroIntegrationService::class)->getIntegrationHealth();

        return [
            'status' => $health['status'],
            'status_color' => $health['status_color'],
            'sync_enabled' => $health['sync_enabled'],
            'pending_count' => $health['pending_count'],
            'failed_count' => $health['failed_count'],
            'last_sync' => $health['last_sync'],
        ];
    }

    // =========================================================================
    // Today's Activity
    // =========================================================================

    /**
     * Get count of invoices issued today.
     */
    public function getInvoicesIssuedToday(): int
    {
        return Invoice::query()
            ->whereDate('issued_at', today())
            ->count();
    }

    /**
     * Get count of payments received today.
     */
    public function getPaymentsReceivedToday(): int
    {
        return Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereDate('received_at', today())
            ->count();
    }

    /**
     * Get total amount of payments received today.
     */
    public function getPaymentsAmountToday(): string
    {
        $amount = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereDate('received_at', today())
            ->sum('amount');

        return number_format((float) $amount, 2, '.', '');
    }

    // =========================================================================
    // Display Helpers
    // =========================================================================

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Get status badge classes.
     */
    public function getStatusBadgeClasses(string $status): string
    {
        return match ($status) {
            'healthy' => 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400',
            'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400',
            'critical' => 'bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400',
            'disabled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /**
     * Get color class for change direction.
     */
    public function getChangeColorClass(string $direction): string
    {
        return match ($direction) {
            'up' => 'text-success-600 dark:text-success-400',
            'down' => 'text-danger-600 dark:text-danger-400',
            default => 'text-gray-500 dark:text-gray-400',
        };
    }

    /**
     * Get icon for change direction.
     */
    public function getChangeIcon(string $direction): string
    {
        return match ($direction) {
            'up' => 'heroicon-o-arrow-trending-up',
            'down' => 'heroicon-o-arrow-trending-down',
            default => 'heroicon-o-minus',
        };
    }

    /**
     * Get current month label.
     */
    public function getCurrentMonthLabel(): string
    {
        return now()->format('F Y');
    }

    // =========================================================================
    // Recent Activity Feed (US-E118)
    // =========================================================================

    /**
     * Get recent activity feed for the last 24 hours.
     *
     * Returns a unified list of financial events sorted by timestamp (newest first):
     * - Invoices issued
     * - Payments received
     * - Credit notes issued
     * - Refunds processed
     *
     * @return array<array{
     *     type: string,
     *     icon: string,
     *     icon_color: string,
     *     title: string,
     *     description: string,
     *     amount: string|null,
     *     currency: string,
     *     timestamp: \Carbon\Carbon,
     *     url: string|null,
     *     model_type: string,
     *     model_id: int|string
     * }>
     */
    public function getRecentActivityFeed(): array
    {
        $since = now()->subDay();
        $activities = [];

        // Invoices issued in last 24 hours
        $invoices = Invoice::query()
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', $since)
            ->with('customer')
            ->orderBy('issued_at', 'desc')
            ->limit(20)
            ->get();

        foreach ($invoices as $invoice) {
            // Skip if issued_at is null (shouldn't happen due to query filter but satisfies PHPStan)
            if ($invoice->issued_at === null) {
                continue;
            }

            $customerName = $invoice->customer !== null
                ? $invoice->customer->name
                : 'Unknown Customer';

            $activities[] = [
                'type' => 'invoice_issued',
                'icon' => 'heroicon-o-document-text',
                'icon_color' => 'primary',
                'title' => 'Invoice Issued',
                'description' => $invoice->invoice_number.' for '.$customerName,
                'amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'timestamp' => $invoice->issued_at,
                'url' => $this->getInvoiceUrl($invoice),
                'model_type' => 'invoice',
                'model_id' => $invoice->id,
            ];
        }

        // Payments received in last 24 hours
        $payments = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->where('received_at', '>=', $since)
            ->with('customer')
            ->orderBy('received_at', 'desc')
            ->limit(20)
            ->get();

        foreach ($payments as $payment) {
            $customerName = $payment->customer !== null
                ? $payment->customer->name
                : 'Unknown Customer';

            $activities[] = [
                'type' => 'payment_received',
                'icon' => 'heroicon-o-credit-card',
                'icon_color' => 'success',
                'title' => 'Payment Received',
                'description' => $payment->payment_reference.' from '.$customerName,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'timestamp' => $payment->received_at,
                'url' => $this->getPaymentUrl($payment),
                'model_type' => 'payment',
                'model_id' => $payment->id,
            ];
        }

        // Credit notes issued in last 24 hours
        $creditNotes = CreditNote::query()
            ->whereIn('status', [CreditNoteStatus::Issued, CreditNoteStatus::Applied])
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', $since)
            ->with(['customer', 'invoice'])
            ->orderBy('issued_at', 'desc')
            ->limit(20)
            ->get();

        foreach ($creditNotes as $creditNote) {
            // Skip if issued_at is null (shouldn't happen due to query filter but satisfies PHPStan)
            if ($creditNote->issued_at === null) {
                continue;
            }

            $customerName = $creditNote->customer !== null
                ? $creditNote->customer->name
                : 'Unknown Customer';
            $invoiceRef = $creditNote->invoice !== null
                ? $creditNote->invoice->invoice_number
                : 'Unknown Invoice';

            $activities[] = [
                'type' => 'credit_note_issued',
                'icon' => 'heroicon-o-document-minus',
                'icon_color' => 'warning',
                'title' => 'Credit Note Issued',
                'description' => ($creditNote->credit_note_number ?? 'Draft').' for '.$customerName.' (ref: '.$invoiceRef.')',
                'amount' => $creditNote->amount,
                'currency' => $creditNote->currency,
                'timestamp' => $creditNote->issued_at,
                'url' => $this->getCreditNoteUrl($creditNote),
                'model_type' => 'credit_note',
                'model_id' => $creditNote->id,
            ];
        }

        // Refunds processed in last 24 hours
        $refunds = Refund::query()
            ->where('status', RefundStatus::Processed)
            ->whereNotNull('processed_at')
            ->where('processed_at', '>=', $since)
            ->with(['invoice', 'payment'])
            ->orderBy('processed_at', 'desc')
            ->limit(20)
            ->get();

        foreach ($refunds as $refund) {
            // Skip if processed_at is null (shouldn't happen due to query filter but satisfies PHPStan)
            if ($refund->processed_at === null) {
                continue;
            }

            $invoiceRef = $refund->invoice !== null
                ? $refund->invoice->invoice_number
                : 'Unknown Invoice';

            $activities[] = [
                'type' => 'refund_processed',
                'icon' => 'heroicon-o-arrow-uturn-left',
                'icon_color' => 'danger',
                'title' => 'Refund Processed',
                'description' => 'Refund for '.$invoiceRef.' via '.$refund->method->label(),
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'timestamp' => $refund->processed_at,
                'url' => $this->getRefundUrl($refund),
                'model_type' => 'refund',
                'model_id' => $refund->id,
            ];
        }

        // Sort by timestamp descending
        usort($activities, function ($a, $b) {
            return $b['timestamp']->timestamp <=> $a['timestamp']->timestamp;
        });

        // Limit to 15 most recent
        return array_slice($activities, 0, 15);
    }

    /**
     * Get URL for invoice detail page.
     */
    public function getInvoiceUrl(Invoice $invoice): string
    {
        return route('filament.admin.resources.finance.invoices.view', ['record' => $invoice->id]);
    }

    /**
     * Get URL for payment detail page.
     */
    public function getPaymentUrl(Payment $payment): string
    {
        return route('filament.admin.resources.finance.payments.view', ['record' => $payment->id]);
    }

    /**
     * Get URL for credit note detail page.
     */
    public function getCreditNoteUrl(CreditNote $creditNote): string
    {
        return route('filament.admin.resources.finance.credit-notes.view', ['record' => $creditNote->id]);
    }

    /**
     * Get URL for refund detail page.
     */
    public function getRefundUrl(Refund $refund): string
    {
        return route('filament.admin.resources.finance.refunds.view', ['record' => $refund->id]);
    }

    /**
     * Get count of activities in last 24 hours.
     */
    public function getRecentActivityCount(): int
    {
        $since = now()->subDay();

        $invoiceCount = Invoice::query()
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', $since)
            ->count();

        $paymentCount = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->where('received_at', '>=', $since)
            ->count();

        $creditNoteCount = CreditNote::query()
            ->whereIn('status', [CreditNoteStatus::Issued, CreditNoteStatus::Applied])
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', $since)
            ->count();

        $refundCount = Refund::query()
            ->where('status', RefundStatus::Processed)
            ->whereNotNull('processed_at')
            ->where('processed_at', '>=', $since)
            ->count();

        return $invoiceCount + $paymentCount + $creditNoteCount + $refundCount;
    }

    /**
     * Check if there is any recent activity.
     */
    public function hasRecentActivity(): bool
    {
        return $this->getRecentActivityCount() > 0;
    }
}
