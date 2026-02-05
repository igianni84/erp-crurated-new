<?php

namespace App\Filament\Widgets\Finance;

use App\Enums\Finance\InvoiceType;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * US-E113: Monthly Financial Summary dashboard widget.
 *
 * Shows key financial metrics for the current month with comparison
 * to the previous month:
 * - Invoices issued (count and amount)
 * - Payments received (count and amount)
 * - Credit notes issued (count and amount)
 * - Refunds processed (count and amount)
 * - Breakdown by invoice type
 */
class MonthlyFinancialSummaryWidget extends Widget
{
    protected static string $view = 'filament.widgets.finance.monthly-financial-summary-widget';

    protected static ?string $heading = 'Monthly Financial Summary';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    /**
     * Get metrics for the current month.
     *
     * @return array{
     *     invoices_issued: int,
     *     invoices_amount: string,
     *     payments_received: int,
     *     payments_amount: string,
     *     credit_notes: int,
     *     credit_notes_amount: string,
     *     refunds: int,
     *     refunds_amount: string
     * }
     */
    public function getCurrentMonthMetrics(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        return $this->getMetricsForPeriod($startOfMonth, $endOfMonth);
    }

    /**
     * Get metrics for the previous month.
     *
     * @return array{
     *     invoices_issued: int,
     *     invoices_amount: string,
     *     payments_received: int,
     *     payments_amount: string,
     *     credit_notes: int,
     *     credit_notes_amount: string,
     *     refunds: int,
     *     refunds_amount: string
     * }
     */
    public function getPreviousMonthMetrics(): array
    {
        $startOfPrevMonth = now()->subMonth()->startOfMonth();
        $endOfPrevMonth = now()->subMonth()->endOfMonth();

        return $this->getMetricsForPeriod($startOfPrevMonth, $endOfPrevMonth);
    }

    /**
     * Get metrics for a specific period.
     *
     * @return array{
     *     invoices_issued: int,
     *     invoices_amount: string,
     *     payments_received: int,
     *     payments_amount: string,
     *     credit_notes: int,
     *     credit_notes_amount: string,
     *     refunds: int,
     *     refunds_amount: string
     * }
     */
    protected function getMetricsForPeriod(Carbon $start, Carbon $end): array
    {
        // Invoices issued in period
        $invoicesQuery = Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$start, $end]);

        $invoicesCount = $invoicesQuery->count();
        $invoicesAmount = $invoicesQuery->sum('total_amount');

        // Payments received in period
        $paymentsQuery = Payment::query()
            ->whereBetween('received_at', [$start, $end])
            ->where('status', 'confirmed');

        $paymentsCount = $paymentsQuery->count();
        $paymentsAmount = $paymentsQuery->sum('amount');

        // Credit notes issued in period
        $creditNotesQuery = CreditNote::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$start, $end]);

        $creditNotesCount = $creditNotesQuery->count();
        $creditNotesAmount = $creditNotesQuery->sum('amount');

        // Refunds processed in period
        $refundsQuery = Refund::query()
            ->whereNotNull('processed_at')
            ->whereBetween('processed_at', [$start, $end])
            ->where('status', 'processed');

        $refundsCount = $refundsQuery->count();
        $refundsAmount = $refundsQuery->sum('amount');

        return [
            'invoices_issued' => $invoicesCount,
            'invoices_amount' => number_format((float) $invoicesAmount, 2, '.', ''),
            'payments_received' => $paymentsCount,
            'payments_amount' => number_format((float) $paymentsAmount, 2, '.', ''),
            'credit_notes' => $creditNotesCount,
            'credit_notes_amount' => number_format((float) $creditNotesAmount, 2, '.', ''),
            'refunds' => $refundsCount,
            'refunds_amount' => number_format((float) $refundsAmount, 2, '.', ''),
        ];
    }

    /**
     * Calculate percentage change between two values.
     *
     * @return array{value: float, direction: string}
     */
    public function calculateChange(string $current, string $previous): array
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
        if ($change > 0) {
            $direction = 'up';
        } elseif ($change < 0) {
            $direction = 'down';
        }

        return [
            'value' => abs($change),
            'direction' => $direction,
        ];
    }

    /**
     * Calculate change between count values.
     *
     * @return array{value: float, direction: string}
     */
    public function calculateCountChange(int $current, int $previous): array
    {
        return $this->calculateChange((string) $current, (string) $previous);
    }

    /**
     * Get breakdown by invoice type for current month.
     *
     * @return Collection<int, array{
     *     type: InvoiceType,
     *     code: string,
     *     label: string,
     *     color: string,
     *     count: int,
     *     amount: string,
     *     previous_count: int,
     *     previous_amount: string
     * }>
     */
    public function getInvoiceTypeBreakdown(): Collection
    {
        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $prevStart = now()->subMonth()->startOfMonth();
        $prevEnd = now()->subMonth()->endOfMonth();

        $data = collect();

        foreach (InvoiceType::cases() as $type) {
            // Current month
            $currentQuery = Invoice::query()
                ->where('invoice_type', $type->value)
                ->whereNotNull('issued_at')
                ->whereBetween('issued_at', [$currentStart, $currentEnd]);

            $currentCount = $currentQuery->count();
            $currentAmount = $currentQuery->sum('total_amount');

            // Previous month
            $prevQuery = Invoice::query()
                ->where('invoice_type', $type->value)
                ->whereNotNull('issued_at')
                ->whereBetween('issued_at', [$prevStart, $prevEnd]);

            $prevCount = $prevQuery->count();
            $prevAmount = $prevQuery->sum('total_amount');

            $data->push([
                'type' => $type,
                'code' => $type->code(),
                'label' => $type->label(),
                'color' => $type->color(),
                'count' => $currentCount,
                'amount' => number_format((float) $currentAmount, 2, '.', ''),
                'previous_count' => $prevCount,
                'previous_amount' => number_format((float) $prevAmount, 2, '.', ''),
            ]);
        }

        return $data;
    }

    /**
     * Get the current month label.
     */
    public function getCurrentMonthLabel(): string
    {
        return now()->format('F Y');
    }

    /**
     * Get the previous month label.
     */
    public function getPreviousMonthLabel(): string
    {
        return now()->subMonth()->format('F Y');
    }

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Get color class for change direction.
     *
     * @param  string  $direction  The direction (up, down, neutral)
     * @param  bool  $inverse  Whether to inverse colors (e.g., refunds going up is bad)
     */
    public function getChangeColorClass(string $direction, bool $inverse = false): string
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
}
