<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Enums\Finance\RefundStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Services\Finance\XeroIntegrationService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

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
 *
 * Alerts and Warnings (US-E119):
 * - Overdue invoices alert
 * - Pending reconciliations alert
 * - Xero sync failures alert
 * - Stripe webhook issues alert
 * - Dismissible with persistence
 */
class FinanceOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Finance Overview';

    protected static string $view = 'filament.pages.finance.finance-overview';

    /**
     * Track dismissed alerts for this session.
     * Uses Livewire property to persist across renders within same session.
     *
     * @var array<string>
     */
    public array $dismissedAlerts = [];

    // =========================================================================
    // Alerts and Warnings (US-E119)
    // =========================================================================

    /**
     * Get all active dashboard alerts.
     *
     * @return array<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     message: string,
     *     icon: string,
     *     color: string,
     *     count: int|null,
     *     url: string|null,
     *     dismissible: bool
     * }>
     */
    public function getAlerts(): array
    {
        $alerts = [];

        // Load dismissed alerts from cache for persistence
        $this->loadDismissedAlerts();

        // Alert: Overdue invoices
        $overdueCount = $this->getOverdueInvoicesCount();
        if ($overdueCount > 0 && ! $this->isAlertDismissed('overdue_invoices')) {
            $alerts[] = [
                'id' => 'overdue_invoices',
                'type' => 'warning',
                'title' => 'Overdue Invoices',
                'message' => $overdueCount.' '.($overdueCount === 1 ? 'invoice is' : 'invoices are').' past due date and require attention.',
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => 'warning',
                'count' => $overdueCount,
                'url' => $this->getOverdueInvoicesUrl(),
                'dismissible' => true,
            ];
        }

        // Alert: Pending reconciliations
        $pendingCount = $this->getPendingReconciliationsCount();
        if ($pendingCount > 0 && ! $this->isAlertDismissed('pending_reconciliations')) {
            $alerts[] = [
                'id' => 'pending_reconciliations',
                'type' => 'info',
                'title' => 'Pending Reconciliations',
                'message' => $pendingCount.' '.($pendingCount === 1 ? 'payment needs' : 'payments need').' to be reconciled with invoices.',
                'icon' => 'heroicon-o-document-magnifying-glass',
                'color' => 'warning',
                'count' => $pendingCount,
                'url' => $this->getPendingReconciliationsUrl(),
                'dismissible' => true,
            ];
        }

        // Alert: Xero sync failures
        $xeroHealth = $this->getXeroHealthSummary();
        if ($xeroHealth['failed_count'] > 0 && ! $this->isAlertDismissed('xero_sync_failures')) {
            $alerts[] = [
                'id' => 'xero_sync_failures',
                'type' => 'error',
                'title' => 'Xero Sync Failures',
                'message' => $xeroHealth['failed_count'].' '.($xeroHealth['failed_count'] === 1 ? 'sync has' : 'syncs have').' failed. Review and retry to ensure accounting accuracy.',
                'icon' => 'heroicon-o-arrow-path-rounded-square',
                'color' => 'danger',
                'count' => $xeroHealth['failed_count'],
                'url' => $this->getFailedSyncsUrl(),
                'dismissible' => true,
            ];
        }

        // Alert: Stripe webhook issues
        $stripeHealth = $this->getStripeHealthSummary();
        if ($stripeHealth['status'] === 'critical') {
            if (! $this->isAlertDismissed('stripe_webhook_issues')) {
                $alerts[] = [
                    'id' => 'stripe_webhook_issues',
                    'type' => 'error',
                    'title' => 'Stripe Integration Issues',
                    'message' => 'Stripe integration has critical issues. '.$stripeHealth['failed_count'].' failed events require immediate attention.',
                    'icon' => 'heroicon-o-signal-slash',
                    'color' => 'danger',
                    'count' => $stripeHealth['failed_count'],
                    'url' => $this->getFailedSyncsUrl(),
                    'dismissible' => true,
                ];
            }
        } elseif ($stripeHealth['status'] === 'warning') {
            if (! $this->isAlertDismissed('stripe_webhook_issues')) {
                $message = $stripeHealth['failed_count'] > 0
                    ? $stripeHealth['failed_count'].' failed events.'
                    : 'No webhooks received recently.';

                $alerts[] = [
                    'id' => 'stripe_webhook_issues',
                    'type' => 'warning',
                    'title' => 'Stripe Integration Issues',
                    'message' => 'Stripe integration may have issues. '.$message,
                    'icon' => 'heroicon-o-signal-slash',
                    'color' => 'warning',
                    'count' => $stripeHealth['failed_count'],
                    'url' => $this->getFailedSyncsUrl(),
                    'dismissible' => true,
                ];
            }
        }

        // Alert: Mismatched reconciliations (additional alert for more severe case)
        $mismatchedCount = $this->getMismatchedReconciliationsCount();
        if ($mismatchedCount > 0 && ! $this->isAlertDismissed('mismatched_reconciliations')) {
            $alerts[] = [
                'id' => 'mismatched_reconciliations',
                'type' => 'error',
                'title' => 'Payment Mismatches',
                'message' => $mismatchedCount.' '.($mismatchedCount === 1 ? 'payment has' : 'payments have').' reconciliation mismatches requiring manual resolution.',
                'icon' => 'heroicon-o-exclamation-circle',
                'color' => 'danger',
                'count' => $mismatchedCount,
                'url' => route('filament.admin.resources.finance.payments.index', [
                    'tableFilters' => ['reconciliation_status' => ['value' => 'mismatched']],
                ]),
                'dismissible' => true,
            ];
        }

        return $alerts;
    }

    /**
     * Check if there are any active alerts.
     */
    public function hasAlerts(): bool
    {
        return count($this->getAlerts()) > 0;
    }

    /**
     * Get count of active alerts.
     */
    public function getAlertCount(): int
    {
        return count($this->getAlerts());
    }

    /**
     * Dismiss an alert by ID.
     * Persists the dismissal for 24 hours.
     */
    public function dismissAlert(string $alertId): void
    {
        if (! in_array($alertId, $this->dismissedAlerts, true)) {
            $this->dismissedAlerts[] = $alertId;
            $this->saveDismissedAlerts();
        }
    }

    /**
     * Check if an alert is dismissed.
     */
    public function isAlertDismissed(string $alertId): bool
    {
        return in_array($alertId, $this->dismissedAlerts, true);
    }

    /**
     * Load dismissed alerts from cache.
     */
    protected function loadDismissedAlerts(): void
    {
        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        $cacheKey = 'finance_dashboard_dismissed_alerts_'.$userId;
        /** @var array<string> $cached */
        $cached = Cache::get($cacheKey, []);
        $this->dismissedAlerts = array_unique(array_merge($this->dismissedAlerts, $cached));
    }

    /**
     * Save dismissed alerts to cache.
     * Alerts remain dismissed for 24 hours.
     */
    protected function saveDismissedAlerts(): void
    {
        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        $cacheKey = 'finance_dashboard_dismissed_alerts_'.$userId;
        Cache::put($cacheKey, $this->dismissedAlerts, now()->addHours(24));
    }

    /**
     * Clear all dismissed alerts.
     * Useful for testing or manual reset.
     */
    public function clearDismissedAlerts(): void
    {
        $this->dismissedAlerts = [];
        $userId = auth()->id();
        if ($userId !== null) {
            $cacheKey = 'finance_dashboard_dismissed_alerts_'.$userId;
            Cache::forget($cacheKey);
        }
    }

    /**
     * Get alert badge color class based on type.
     */
    public function getAlertColorClasses(string $color): string
    {
        return match ($color) {
            'danger' => 'bg-danger-50 dark:bg-danger-400/10 border-danger-200 dark:border-danger-400/20',
            'warning' => 'bg-warning-50 dark:bg-warning-400/10 border-warning-200 dark:border-warning-400/20',
            'success' => 'bg-success-50 dark:bg-success-400/10 border-success-200 dark:border-success-400/20',
            'info', 'primary' => 'bg-primary-50 dark:bg-primary-400/10 border-primary-200 dark:border-primary-400/20',
            default => 'bg-gray-50 dark:bg-gray-400/10 border-gray-200 dark:border-gray-400/20',
        };
    }

    /**
     * Get alert icon color class based on type.
     */
    public function getAlertIconColorClass(string $color): string
    {
        return match ($color) {
            'danger' => 'text-danger-600 dark:text-danger-400',
            'warning' => 'text-warning-600 dark:text-warning-400',
            'success' => 'text-success-600 dark:text-success-400',
            'info', 'primary' => 'text-primary-600 dark:text-primary-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    /**
     * Get alert text color class based on type.
     */
    public function getAlertTextColorClass(string $color): string
    {
        return match ($color) {
            'danger' => 'text-danger-800 dark:text-danger-200',
            'warning' => 'text-warning-800 dark:text-warning-200',
            'success' => 'text-success-800 dark:text-success-200',
            'info', 'primary' => 'text-primary-800 dark:text-primary-200',
            default => 'text-gray-800 dark:text-gray-200',
        };
    }

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
    // Period Comparison (US-E120)
    // =========================================================================

    /**
     * Get period comparison data for This Month vs Last Month.
     *
     * Returns metrics with percentage changes for:
     * - Invoices issued (count and amount)
     * - Amount collected (payments)
     * - Credit notes issued (count and amount)
     *
     * @return array{
     *     current_month: string,
     *     previous_month: string,
     *     invoices: array{
     *         current_count: int,
     *         previous_count: int,
     *         current_amount: string,
     *         previous_amount: string,
     *         count_change: array{value: float, direction: string},
     *         amount_change: array{value: float, direction: string}
     *     },
     *     payments: array{
     *         current_count: int,
     *         previous_count: int,
     *         current_amount: string,
     *         previous_amount: string,
     *         count_change: array{value: float, direction: string},
     *         amount_change: array{value: float, direction: string}
     *     },
     *     credit_notes: array{
     *         current_count: int,
     *         previous_count: int,
     *         current_amount: string,
     *         previous_amount: string,
     *         count_change: array{value: float, direction: string},
     *         amount_change: array{value: float, direction: string}
     *     }
     * }
     */
    public function getPeriodComparison(): array
    {
        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $previousStart = now()->subMonth()->startOfMonth();
        $previousEnd = now()->subMonth()->endOfMonth();

        // Invoices issued
        $currentInvoices = Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$currentStart, $currentEnd]);
        $currentInvoicesCount = $currentInvoices->count();
        $currentInvoicesAmount = $currentInvoices->sum('total_amount');

        $previousInvoices = Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$previousStart, $previousEnd]);
        $previousInvoicesCount = $previousInvoices->count();
        $previousInvoicesAmount = $previousInvoices->sum('total_amount');

        // Payments received (amount collected)
        $currentPayments = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereBetween('received_at', [$currentStart, $currentEnd]);
        $currentPaymentsCount = $currentPayments->count();
        $currentPaymentsAmount = $currentPayments->sum('amount');

        $previousPayments = Payment::query()
            ->where('status', PaymentStatus::Confirmed)
            ->whereBetween('received_at', [$previousStart, $previousEnd]);
        $previousPaymentsCount = $previousPayments->count();
        $previousPaymentsAmount = $previousPayments->sum('amount');

        // Credit notes issued
        $currentCreditNotes = CreditNote::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$currentStart, $currentEnd]);
        $currentCreditNotesCount = $currentCreditNotes->count();
        $currentCreditNotesAmount = $currentCreditNotes->sum('amount');

        $previousCreditNotes = CreditNote::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$previousStart, $previousEnd]);
        $previousCreditNotesCount = $previousCreditNotes->count();
        $previousCreditNotesAmount = $previousCreditNotes->sum('amount');

        return [
            'current_month' => now()->format('F Y'),
            'previous_month' => now()->subMonth()->format('F Y'),
            'invoices' => [
                'current_count' => $currentInvoicesCount,
                'previous_count' => $previousInvoicesCount,
                'current_amount' => number_format((float) $currentInvoicesAmount, 2, '.', ''),
                'previous_amount' => number_format((float) $previousInvoicesAmount, 2, '.', ''),
                'count_change' => $this->calculatePercentageChange($currentInvoicesCount, $previousInvoicesCount),
                'amount_change' => $this->calculatePercentageChange((float) $currentInvoicesAmount, (float) $previousInvoicesAmount),
            ],
            'payments' => [
                'current_count' => $currentPaymentsCount,
                'previous_count' => $previousPaymentsCount,
                'current_amount' => number_format((float) $currentPaymentsAmount, 2, '.', ''),
                'previous_amount' => number_format((float) $previousPaymentsAmount, 2, '.', ''),
                'count_change' => $this->calculatePercentageChange($currentPaymentsCount, $previousPaymentsCount),
                'amount_change' => $this->calculatePercentageChange((float) $currentPaymentsAmount, (float) $previousPaymentsAmount),
            ],
            'credit_notes' => [
                'current_count' => $currentCreditNotesCount,
                'previous_count' => $previousCreditNotesCount,
                'current_amount' => number_format((float) $currentCreditNotesAmount, 2, '.', ''),
                'previous_amount' => number_format((float) $previousCreditNotesAmount, 2, '.', ''),
                'count_change' => $this->calculatePercentageChange($currentCreditNotesCount, $previousCreditNotesCount),
                'amount_change' => $this->calculatePercentageChange((float) $currentCreditNotesAmount, (float) $previousCreditNotesAmount),
            ],
        ];
    }

    /**
     * Calculate percentage change between two values.
     *
     * @return array{value: float, direction: string}
     */
    protected function calculatePercentageChange(float|int $current, float|int $previous): array
    {
        $currentFloat = (float) $current;
        $previousFloat = (float) $previous;

        if ($previousFloat === 0.0) {
            if ($currentFloat > 0) {
                return ['value' => 100.0, 'direction' => 'up'];
            }

            return ['value' => 0.0, 'direction' => 'neutral'];
        }

        $change = (($currentFloat - $previousFloat) / $previousFloat) * 100;

        $direction = 'neutral';
        if ($change > 0.5) { // Use small threshold to avoid floating point issues
            $direction = 'up';
        } elseif ($change < -0.5) {
            $direction = 'down';
        }

        return [
            'value' => abs($change),
            'direction' => $direction,
        ];
    }

    /**
     * Get change color class based on direction and metric type.
     *
     * For most metrics, up = green, down = red.
     * For credit notes, up = red (more credit notes is typically unfavorable), down = green.
     *
     * @param  string  $direction  The change direction (up, down, neutral)
     * @param  bool  $inverse  Whether to inverse the colors (true for credit notes)
     */
    public function getPeriodChangeColorClass(string $direction, bool $inverse = false): string
    {
        if ($direction === 'neutral') {
            return 'text-gray-500 dark:text-gray-400';
        }

        $isPositive = $direction === 'up';
        if ($inverse) {
            $isPositive = ! $isPositive;
        }

        return $isPositive
            ? 'text-success-600 dark:text-success-400'
            : 'text-danger-600 dark:text-danger-400';
    }

    /**
     * Get change background color class based on direction and metric type.
     *
     * @param  string  $direction  The change direction (up, down, neutral)
     * @param  bool  $inverse  Whether to inverse the colors (true for credit notes)
     */
    public function getPeriodChangeBgColorClass(string $direction, bool $inverse = false): string
    {
        if ($direction === 'neutral') {
            return 'bg-gray-100 dark:bg-gray-700';
        }

        $isPositive = $direction === 'up';
        if ($inverse) {
            $isPositive = ! $isPositive;
        }

        return $isPositive
            ? 'bg-success-100 dark:bg-success-400/20'
            : 'bg-danger-100 dark:bg-danger-400/20';
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
    // Top Customers by Outstanding (US-E121)
    // =========================================================================

    /**
     * Get top 10 customers by outstanding amount.
     *
     * Returns customers ordered by their total outstanding invoice amount
     * (issued/partially_paid invoices only).
     *
     * @return array<array{
     *     customer_id: int,
     *     customer_name: string,
     *     outstanding_amount: string,
     *     invoice_count: int,
     *     url: string
     * }>
     */
    public function getTopCustomersOutstanding(): array
    {
        /** @var array<array{customer_id: int, outstanding: string, invoice_count: int}> $topCustomers */
        $topCustomers = Invoice::query()
            ->select('customer_id')
            ->selectRaw('SUM(total_amount - amount_paid) as outstanding')
            ->selectRaw('COUNT(*) as invoice_count')
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
            ])
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->having('outstanding', '>', 0)
            ->orderByDesc('outstanding')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'customer_id' => (int) $item->getAttribute('customer_id'),
                'outstanding' => (string) $item->getAttribute('outstanding'),
                'invoice_count' => (int) $item->getAttribute('invoice_count'),
            ])
            ->toArray();

        if (empty($topCustomers)) {
            return [];
        }

        // Load customer names
        $customerIds = array_column($topCustomers, 'customer_id');
        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($topCustomers as $row) {
            $customerId = $row['customer_id'];
            $customer = $customers->get($customerId);

            if ($customer === null) {
                continue;
            }

            $result[] = [
                'customer_id' => $customerId,
                'customer_name' => $customer->name ?? 'Unknown Customer',
                'outstanding_amount' => number_format((float) $row['outstanding'], 2, '.', ''),
                'invoice_count' => $row['invoice_count'],
                'url' => $this->getCustomerFinanceUrl($customerId),
            ];
        }

        return $result;
    }

    /**
     * Check if there are any customers with outstanding amounts.
     */
    public function hasTopCustomersOutstanding(): bool
    {
        return Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
            ])
            ->whereNotNull('customer_id')
            ->whereRaw('(total_amount - amount_paid) > 0')
            ->exists();
    }

    /**
     * Get URL for customer finance page.
     */
    public function getCustomerFinanceUrl(int $customerId): string
    {
        return route('filament.admin.pages.customer-finance').'?customerId='.$customerId;
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
