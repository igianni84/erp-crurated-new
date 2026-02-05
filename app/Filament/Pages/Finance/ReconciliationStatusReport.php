<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\ReconciliationStatus;
use App\Models\Finance\Payment;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Reconciliation Status Report page for Finance module.
 *
 * This page provides a reconciliation status report showing:
 * - Payments by reconciliation_status (summary)
 * - List of pending reconciliations
 * - List of mismatches with resolution status
 */
class ReconciliationStatusReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Reconciliation Status';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 96;

    protected static ?string $title = 'Reconciliation Status Report';

    protected static string $view = 'filament.pages.finance.reconciliation-status-report';

    /**
     * Filter by payment source.
     */
    public ?string $filterSource = null;

    /**
     * Date range start for filtering.
     */
    public string $dateFrom = '';

    /**
     * Date range end for filtering.
     */
    public string $dateTo = '';

    /**
     * Current active tab.
     */
    public string $activeTab = 'summary';

    /**
     * Cache for summary data.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $summaryCache = null;

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        // Default to last 30 days
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    /**
     * Reset cache when filters change.
     */
    public function updatedFilterSource(): void
    {
        $this->summaryCache = null;
    }

    /**
     * Reset cache when date from changes.
     */
    public function updatedDateFrom(): void
    {
        $this->summaryCache = null;
    }

    /**
     * Reset cache when date to changes.
     */
    public function updatedDateTo(): void
    {
        $this->summaryCache = null;
    }

    /**
     * Get base query with filters applied.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Payment>
     */
    protected function getBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Payment::query();

        if ($this->filterSource !== null && $this->filterSource !== '') {
            $query->where('source', $this->filterSource);
        }

        if ($this->dateFrom !== '') {
            $query->where('received_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
        }

        if ($this->dateTo !== '') {
            $query->where('received_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
        }

        return $query;
    }

    /**
     * Get summary data for reconciliation status.
     *
     * @return array{
     *     total_payments: int,
     *     total_amount: string,
     *     by_status: array<string, array{count: int, amount: string, percentage: float}>,
     *     pending_count: int,
     *     pending_amount: string,
     *     mismatched_count: int,
     *     mismatched_amount: string,
     *     matched_count: int,
     *     matched_amount: string
     * }
     */
    public function getSummary(): array
    {
        if ($this->summaryCache !== null) {
            return $this->summaryCache;
        }

        $query = $this->getBaseQuery();

        $payments = $query->get();

        $totalPayments = $payments->count();
        $totalAmount = '0.00';

        $byStatus = [];
        foreach (ReconciliationStatus::cases() as $status) {
            $byStatus[$status->value] = [
                'count' => 0,
                'amount' => '0.00',
                'percentage' => 0.0,
            ];
        }

        foreach ($payments as $payment) {
            $totalAmount = bcadd($totalAmount, $payment->amount, 2);
            $statusValue = $payment->reconciliation_status->value;
            $byStatus[$statusValue]['count']++;
            $byStatus[$statusValue]['amount'] = bcadd($byStatus[$statusValue]['amount'], $payment->amount, 2);
        }

        // Calculate percentages
        if ($totalPayments > 0) {
            foreach ($byStatus as $status => $data) {
                $byStatus[$status]['percentage'] = round(($data['count'] / $totalPayments) * 100, 1);
            }
        }

        $this->summaryCache = [
            'total_payments' => $totalPayments,
            'total_amount' => $totalAmount,
            'by_status' => $byStatus,
            'pending_count' => $byStatus[ReconciliationStatus::Pending->value]['count'],
            'pending_amount' => $byStatus[ReconciliationStatus::Pending->value]['amount'],
            'mismatched_count' => $byStatus[ReconciliationStatus::Mismatched->value]['count'],
            'mismatched_amount' => $byStatus[ReconciliationStatus::Mismatched->value]['amount'],
            'matched_count' => $byStatus[ReconciliationStatus::Matched->value]['count'],
            'matched_amount' => $byStatus[ReconciliationStatus::Matched->value]['amount'],
        ];

        return $this->summaryCache;
    }

    /**
     * Get pending reconciliation payments.
     *
     * @return Collection<int, Payment>
     */
    public function getPendingReconciliations(): Collection
    {
        return $this->getBaseQuery()
            ->where('reconciliation_status', ReconciliationStatus::Pending)
            ->with('customer')
            ->orderBy('received_at', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Get mismatched payments.
     *
     * @return Collection<int, Payment>
     */
    public function getMismatchedPayments(): Collection
    {
        return $this->getBaseQuery()
            ->where('reconciliation_status', ReconciliationStatus::Mismatched)
            ->with('customer')
            ->orderBy('received_at', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Get payment source options for filter.
     *
     * @return array<string, string>
     */
    public function getSourceOptions(): array
    {
        $options = ['' => 'All Sources'];
        foreach (PaymentSource::cases() as $source) {
            $options[$source->value] = $source->label();
        }

        return $options;
    }

    /**
     * Get color class for reconciliation status.
     */
    public function getStatusColor(ReconciliationStatus $status): string
    {
        return $status->color();
    }

    /**
     * Get icon for reconciliation status.
     */
    public function getStatusIcon(ReconciliationStatus $status): string
    {
        return $status->icon();
    }

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Get the URL to view payment details.
     */
    public function getPaymentUrl(string $paymentId): string
    {
        return route('filament.admin.resources.payments.view', ['record' => $paymentId]);
    }

    /**
     * Get mismatch type label for a payment.
     */
    public function getMismatchTypeLabel(Payment $payment): string
    {
        return $payment->getMismatchTypeLabel() ?? 'Unknown';
    }

    /**
     * Get mismatch reason for a payment.
     */
    public function getMismatchReason(Payment $payment): string
    {
        return $payment->getMismatchReason();
    }

    /**
     * Check if payment has resolution status (resolved mismatch info in metadata).
     */
    public function hasResolutionStatus(Payment $payment): bool
    {
        if ($payment->metadata === null) {
            return false;
        }

        return isset($payment->metadata['resolution_status']) || isset($payment->metadata['resolved_at']);
    }

    /**
     * Get resolution status for a payment.
     */
    public function getResolutionStatus(Payment $payment): ?string
    {
        if ($payment->metadata === null) {
            return null;
        }

        return $payment->metadata['resolution_status'] ?? null;
    }

    /**
     * Set the active tab.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Get days since payment received.
     */
    public function getDaysSinceReceived(Payment $payment): int
    {
        return (int) $payment->received_at->diffInDays(now());
    }

    /**
     * Get urgency level based on days since received.
     */
    public function getUrgencyLevel(Payment $payment): string
    {
        $days = $this->getDaysSinceReceived($payment);

        if ($days > 7) {
            return 'high';
        }

        if ($days > 3) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get urgency color class.
     */
    public function getUrgencyColor(string $level): string
    {
        return match ($level) {
            'high' => 'danger',
            'medium' => 'warning',
            default => 'gray',
        };
    }
}
