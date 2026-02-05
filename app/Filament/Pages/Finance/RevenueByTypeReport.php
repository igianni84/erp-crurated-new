<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Finance\Invoice;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Revenue by Invoice Type Report page for Finance module.
 *
 * This page provides a revenue breakdown by invoice type showing:
 * - Period selector (monthly, quarterly, yearly)
 * - Breakdown by INV0, INV1, INV2, INV3, INV4
 * - Amounts: issued, paid, outstanding
 * - Chart visualization
 * - Export to CSV functionality
 */
class RevenueByTypeReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Revenue by Type';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 61;

    protected static ?string $title = 'Revenue by Invoice Type';

    protected static string $view = 'filament.pages.finance.revenue-by-type-report';

    /**
     * Selected period type: monthly, quarterly, yearly.
     */
    public string $periodType = 'monthly';

    /**
     * Selected year.
     */
    public int $selectedYear;

    /**
     * Selected month (1-12), used when periodType is monthly.
     */
    public int $selectedMonth;

    /**
     * Selected quarter (1-4), used when periodType is quarterly.
     */
    public int $selectedQuarter;

    /**
     * Cache for report data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $reportDataCache = null;

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        $now = now();
        $this->selectedYear = $now->year;
        $this->selectedMonth = $now->month;
        $this->selectedQuarter = (int) ceil($now->month / 3);
    }

    /**
     * Reset report data cache when period type changes.
     */
    public function updatedPeriodType(): void
    {
        $this->reportDataCache = null;
    }

    /**
     * Reset report data cache when year changes.
     */
    public function updatedSelectedYear(): void
    {
        $this->reportDataCache = null;
    }

    /**
     * Reset report data cache when month changes.
     */
    public function updatedSelectedMonth(): void
    {
        $this->reportDataCache = null;
    }

    /**
     * Reset report data cache when quarter changes.
     */
    public function updatedSelectedQuarter(): void
    {
        $this->reportDataCache = null;
    }

    /**
     * Get the period start date based on selection.
     */
    protected function getPeriodStart(): Carbon
    {
        return match ($this->periodType) {
            'monthly' => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth(),
            'quarterly' => Carbon::create($this->selectedYear, (($this->selectedQuarter - 1) * 3) + 1, 1)->startOfMonth(),
            'yearly' => Carbon::create($this->selectedYear, 1, 1)->startOfYear(),
            default => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth(),
        };
    }

    /**
     * Get the period end date based on selection.
     */
    protected function getPeriodEnd(): Carbon
    {
        return match ($this->periodType) {
            'monthly' => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth(),
            'quarterly' => Carbon::create($this->selectedYear, (($this->selectedQuarter - 1) * 3) + 1, 1)->addMonths(2)->endOfMonth(),
            'yearly' => Carbon::create($this->selectedYear, 12, 31)->endOfYear(),
            default => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth(),
        };
    }

    /**
     * Get the period label for display.
     */
    public function getPeriodLabel(): string
    {
        return match ($this->periodType) {
            'monthly' => Carbon::create($this->selectedYear, $this->selectedMonth, 1)->format('F Y'),
            'quarterly' => 'Q'.$this->selectedQuarter.' '.$this->selectedYear,
            'yearly' => (string) $this->selectedYear,
            default => '',
        };
    }

    /**
     * Get available years for selection.
     *
     * @return array<int, int>
     */
    public function getAvailableYears(): array
    {
        $currentYear = now()->year;
        $years = [];
        for ($year = $currentYear - 5; $year <= $currentYear + 1; $year++) {
            $years[$year] = $year;
        }

        return $years;
    }

    /**
     * Get available months for selection.
     *
     * @return array<int, string>
     */
    public function getAvailableMonths(): array
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = Carbon::create(null, $month, 1)->format('F');
        }

        return $months;
    }

    /**
     * Get available quarters for selection.
     *
     * @return array<int, string>
     */
    public function getAvailableQuarters(): array
    {
        return [
            1 => 'Q1 (Jan-Mar)',
            2 => 'Q2 (Apr-Jun)',
            3 => 'Q3 (Jul-Sep)',
            4 => 'Q4 (Oct-Dec)',
        ];
    }

    /**
     * Get all invoice types for the report.
     *
     * @return array<int, InvoiceType>
     */
    public function getInvoiceTypes(): array
    {
        return InvoiceType::cases();
    }

    /**
     * Get revenue data by invoice type for the selected period.
     *
     * @return Collection<int, array{
     *     type: InvoiceType,
     *     code: string,
     *     label: string,
     *     color: string,
     *     icon: string,
     *     issued_count: int,
     *     issued_amount: string,
     *     paid_count: int,
     *     paid_amount: string,
     *     outstanding_count: int,
     *     outstanding_amount: string
     * }>
     */
    public function getRevenueData(): Collection
    {
        if ($this->reportDataCache !== null) {
            return $this->reportDataCache;
        }

        $periodStart = $this->getPeriodStart();
        $periodEnd = $this->getPeriodEnd();

        $data = collect();

        foreach (InvoiceType::cases() as $type) {
            // Get invoices issued in period
            $issuedQuery = Invoice::query()
                ->where('invoice_type', $type->value)
                ->whereNotNull('issued_at')
                ->whereBetween('issued_at', [$periodStart, $periodEnd]);

            $issuedCount = $issuedQuery->count();
            $issuedAmount = (string) $issuedQuery->sum('total_amount');

            // Get paid invoices (paid status) issued in period
            $paidQuery = Invoice::query()
                ->where('invoice_type', $type->value)
                ->whereNotNull('issued_at')
                ->whereBetween('issued_at', [$periodStart, $periodEnd])
                ->where('status', InvoiceStatus::Paid);

            $paidCount = $paidQuery->count();
            $paidAmount = (string) $paidQuery->sum('total_amount');

            // Get outstanding invoices (issued or partially paid) issued in period
            $outstandingQuery = Invoice::query()
                ->where('invoice_type', $type->value)
                ->whereNotNull('issued_at')
                ->whereBetween('issued_at', [$periodStart, $periodEnd])
                ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid]);

            $outstandingCount = $outstandingQuery->count();
            // Outstanding = total - paid for these invoices
            $outstandingInvoices = $outstandingQuery->get();
            $outstandingAmount = '0.00';
            foreach ($outstandingInvoices as $invoice) {
                $outstandingAmount = bcadd(
                    $outstandingAmount,
                    bcsub($invoice->total_amount, $invoice->amount_paid, 2),
                    2
                );
            }

            $data->push([
                'type' => $type,
                'code' => $type->code(),
                'label' => $type->label(),
                'color' => $type->color(),
                'icon' => $type->icon(),
                'issued_count' => $issuedCount,
                'issued_amount' => number_format((float) $issuedAmount, 2, '.', ''),
                'paid_count' => $paidCount,
                'paid_amount' => number_format((float) $paidAmount, 2, '.', ''),
                'outstanding_count' => $outstandingCount,
                'outstanding_amount' => $outstandingAmount,
            ]);
        }

        $this->reportDataCache = $data;

        return $data;
    }

    /**
     * Get summary totals across all invoice types.
     *
     * @return array{
     *     total_issued_count: int,
     *     total_issued_amount: string,
     *     total_paid_count: int,
     *     total_paid_amount: string,
     *     total_outstanding_count: int,
     *     total_outstanding_amount: string
     * }
     */
    public function getSummary(): array
    {
        $data = $this->getRevenueData();

        $summary = [
            'total_issued_count' => 0,
            'total_issued_amount' => '0.00',
            'total_paid_count' => 0,
            'total_paid_amount' => '0.00',
            'total_outstanding_count' => 0,
            'total_outstanding_amount' => '0.00',
        ];

        foreach ($data as $row) {
            $summary['total_issued_count'] += $row['issued_count'];
            $summary['total_issued_amount'] = bcadd($summary['total_issued_amount'], $row['issued_amount'], 2);
            $summary['total_paid_count'] += $row['paid_count'];
            $summary['total_paid_amount'] = bcadd($summary['total_paid_amount'], $row['paid_amount'], 2);
            $summary['total_outstanding_count'] += $row['outstanding_count'];
            $summary['total_outstanding_amount'] = bcadd($summary['total_outstanding_amount'], $row['outstanding_amount'], 2);
        }

        return $summary;
    }

    /**
     * Get chart data for visualization.
     *
     * @return array{
     *     labels: array<int, string>,
     *     issued: array<int, float>,
     *     paid: array<int, float>,
     *     outstanding: array<int, float>,
     *     colors: array<int, string>
     * }
     */
    public function getChartData(): array
    {
        $data = $this->getRevenueData();

        $labels = [];
        $issued = [];
        $paid = [];
        $outstanding = [];
        $colors = [];

        foreach ($data as $row) {
            $labels[] = $row['code'];
            $issued[] = (float) $row['issued_amount'];
            $paid[] = (float) $row['paid_amount'];
            $outstanding[] = (float) $row['outstanding_amount'];
            $colors[] = $this->getChartColor($row['color']);
        }

        return [
            'labels' => $labels,
            'issued' => $issued,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'colors' => $colors,
        ];
    }

    /**
     * Get hex color for chart based on Filament color name.
     */
    protected function getChartColor(string $colorName): string
    {
        return match ($colorName) {
            'primary' => '#6366f1',
            'success' => '#22c55e',
            'info' => '#3b82f6',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'gray' => '#6b7280',
            default => '#6b7280',
        };
    }

    /**
     * Get percentage of total issued amount for each type.
     *
     * @return array<string, float>
     */
    public function getTypePercentages(): array
    {
        $data = $this->getRevenueData();
        $summary = $this->getSummary();

        $percentages = [];

        if (bccomp($summary['total_issued_amount'], '0', 2) === 0) {
            foreach ($data as $row) {
                $percentages[$row['code']] = 0;
            }

            return $percentages;
        }

        foreach ($data as $row) {
            $percentages[$row['code']] = (float) bcdiv(
                bcmul($row['issued_amount'], '100', 2),
                $summary['total_issued_amount'],
                1
            );
        }

        return $percentages;
    }

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Export report to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->getRevenueData();
        $summary = $this->getSummary();
        $periodLabel = $this->getPeriodLabel();

        return response()->streamDownload(function () use ($data, $summary, $periodLabel): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Report header
            fputcsv($handle, ['Revenue by Invoice Type Report']);
            fputcsv($handle, ['Period', $periodLabel]);
            fputcsv($handle, []);

            // Column headers
            fputcsv($handle, [
                'Type Code',
                'Type Name',
                'Issued Count',
                'Issued Amount',
                'Paid Count',
                'Paid Amount',
                'Outstanding Count',
                'Outstanding Amount',
            ]);

            // Data rows
            foreach ($data as $row) {
                fputcsv($handle, [
                    $row['code'],
                    $row['label'],
                    $row['issued_count'],
                    $row['issued_amount'],
                    $row['paid_count'],
                    $row['paid_amount'],
                    $row['outstanding_count'],
                    $row['outstanding_amount'],
                ]);
            }

            // Summary row
            fputcsv($handle, []);
            fputcsv($handle, [
                'TOTAL',
                '',
                $summary['total_issued_count'],
                $summary['total_issued_amount'],
                $summary['total_paid_count'],
                $summary['total_paid_amount'],
                $summary['total_outstanding_count'],
                $summary['total_outstanding_amount'],
            ]);

            fclose($handle);
        }, 'revenue-by-type-'.$this->periodType.'-'.str_replace(' ', '-', strtolower($periodLabel)).'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
