<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Outstanding Exposure Report page for Finance module.
 *
 * This page provides an outstanding exposure report showing:
 * - Total outstanding by customer (top customers)
 * - Total outstanding by invoice type
 * - Trend over time (chart)
 * - Export to CSV functionality
 */
class OutstandingExposureReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Outstanding Exposure';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 92;

    protected static ?string $title = 'Outstanding Exposure Report';

    protected string $view = 'filament.pages.finance.outstanding-exposure-report';

    /**
     * Number of months to show in trend chart.
     */
    public int $trendMonths = 6;

    /**
     * Number of top customers to show.
     */
    public int $topCustomersCount = 10;

    /**
     * Cache for customer data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $customerDataCache = null;

    /**
     * Cache for invoice type data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $typeDataCache = null;

    /**
     * Cache for trend data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $trendDataCache = null;

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        $this->trendMonths = 6;
        $this->topCustomersCount = 10;
    }

    /**
     * Reset caches when trend months changes.
     */
    public function updatedTrendMonths(): void
    {
        $this->trendDataCache = null;
    }

    /**
     * Reset caches when top customers count changes.
     */
    public function updatedTopCustomersCount(): void
    {
        $this->customerDataCache = null;
    }

    /**
     * Get available trend period options.
     *
     * @return array<int, string>
     */
    public function getTrendMonthOptions(): array
    {
        return [
            3 => 'Last 3 months',
            6 => 'Last 6 months',
            12 => 'Last 12 months',
        ];
    }

    /**
     * Get available top customers count options.
     *
     * @return array<int, string>
     */
    public function getTopCustomersOptions(): array
    {
        return [
            5 => 'Top 5',
            10 => 'Top 10',
            20 => 'Top 20',
            50 => 'Top 50',
        ];
    }

    /**
     * Get total outstanding amount across all customers.
     */
    public function getTotalOutstanding(): string
    {
        $total = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->selectRaw('SUM(total_amount - amount_paid) as outstanding')
            ->value('outstanding');

        return number_format((float) ($total ?? 0), 2, '.', '');
    }

    /**
     * Get total overdue amount.
     */
    public function getTotalOverdue(): string
    {
        $total = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->selectRaw('SUM(total_amount - amount_paid) as outstanding')
            ->value('outstanding');

        return number_format((float) ($total ?? 0), 2, '.', '');
    }

    /**
     * Get count of customers with outstanding invoices.
     */
    public function getCustomersWithOutstanding(): int
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->distinct('customer_id')
            ->count('customer_id');
    }

    /**
     * Get count of outstanding invoices.
     */
    public function getOutstandingInvoiceCount(): int
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->count();
    }

    /**
     * Get outstanding exposure by customer.
     *
     * @return Collection<int, mixed>
     */
    public function getOutstandingByCustomer(): Collection
    {
        if ($this->customerDataCache !== null) {
            return $this->customerDataCache;
        }

        $totalOutstanding = $this->getTotalOutstanding();

        // Get customers with outstanding invoices
        $customers = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->with('customer')
            ->get()
            ->groupBy('customer_id')
            ->map(function (Collection $invoices, int $customerId) use ($totalOutstanding): array {
                $customer = $invoices->first()?->customer;
                $outstanding = '0.00';
                $overdue = '0.00';

                foreach ($invoices as $invoice) {
                    $invoiceOutstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
                    $outstanding = bcadd($outstanding, $invoiceOutstanding, 2);

                    if ($invoice->due_date !== null && $invoice->due_date->lt(now()->startOfDay())) {
                        $overdue = bcadd($overdue, $invoiceOutstanding, 2);
                    }
                }

                $percentage = bccomp($totalOutstanding, '0', 2) !== 0
                    ? (float) bcdiv(bcmul($outstanding, '100', 2), $totalOutstanding, 1)
                    : 0.0;

                return [
                    'customer_id' => $customerId,
                    'customer_name' => $customer !== null ? $customer->name : 'Unknown Customer',
                    'customer_email' => $customer?->email,
                    'outstanding' => $outstanding,
                    'overdue' => $overdue,
                    'invoice_count' => $invoices->count(),
                    'percentage' => $percentage,
                ];
            })
            ->sortByDesc('outstanding')
            ->values()
            ->take($this->topCustomersCount);

        $this->customerDataCache = $customers;

        return $customers;
    }

    /**
     * Get outstanding exposure by invoice type.
     *
     * @return Collection<int, array{
     *     type: InvoiceType,
     *     code: string,
     *     label: string,
     *     color: string,
     *     outstanding: string,
     *     overdue: string,
     *     invoice_count: int,
     *     percentage: float
     * }>
     */
    public function getOutstandingByType(): Collection
    {
        if ($this->typeDataCache !== null) {
            return $this->typeDataCache;
        }

        $totalOutstanding = $this->getTotalOutstanding();
        $data = collect();

        foreach (InvoiceType::cases() as $type) {
            $invoices = Invoice::query()
                ->where('invoice_type', $type->value)
                ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
                ->get();

            $outstanding = '0.00';
            $overdue = '0.00';

            foreach ($invoices as $invoice) {
                $invoiceOutstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
                $outstanding = bcadd($outstanding, $invoiceOutstanding, 2);

                if ($invoice->due_date !== null && $invoice->due_date->lt(now()->startOfDay())) {
                    $overdue = bcadd($overdue, $invoiceOutstanding, 2);
                }
            }

            $percentage = bccomp($totalOutstanding, '0', 2) !== 0
                ? (float) bcdiv(bcmul($outstanding, '100', 2), $totalOutstanding, 1)
                : 0;

            $data->push([
                'type' => $type,
                'code' => $type->code(),
                'label' => $type->label(),
                'color' => $type->color(),
                'outstanding' => $outstanding,
                'overdue' => $overdue,
                'invoice_count' => $invoices->count(),
                'percentage' => $percentage,
            ]);
        }

        $this->typeDataCache = $data->sortByDesc('outstanding')->values();

        return $this->typeDataCache;
    }

    /**
     * Get outstanding trend over time.
     *
     * @return Collection<int, array{
     *     month: string,
     *     month_label: string,
     *     outstanding: string,
     *     overdue: string
     * }>
     */
    public function getOutstandingTrend(): Collection
    {
        if ($this->trendDataCache !== null) {
            return $this->trendDataCache;
        }

        $data = collect();
        $now = now();

        for ($i = $this->trendMonths - 1; $i >= 0; $i--) {
            $monthEnd = $now->copy()->subMonths($i)->endOfMonth();
            $monthLabel = $monthEnd->format('M Y');
            $monthKey = $monthEnd->format('Y-m');

            // Calculate outstanding as of end of that month
            // Invoices issued on or before that date that were not yet paid
            $outstanding = $this->calculateHistoricalOutstanding($monthEnd);
            $overdue = $this->calculateHistoricalOverdue($monthEnd);

            $data->push([
                'month' => $monthKey,
                'month_label' => $monthLabel,
                'outstanding' => $outstanding,
                'overdue' => $overdue,
            ]);
        }

        $this->trendDataCache = $data;

        return $data;
    }

    /**
     * Calculate historical outstanding as of a specific date.
     *
     * This is a simplified calculation that looks at invoices
     * that were issued before the date and considers current outstanding.
     */
    protected function calculateHistoricalOutstanding(Carbon $asOfDate): string
    {
        // Get invoices issued on or before this date
        $total = Invoice::query()
            ->whereNotNull('issued_at')
            ->where('issued_at', '<=', $asOfDate)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->selectRaw('SUM(total_amount - amount_paid) as outstanding')
            ->value('outstanding');

        return number_format((float) ($total ?? 0), 2, '.', '');
    }

    /**
     * Calculate historical overdue as of a specific date.
     */
    protected function calculateHistoricalOverdue(Carbon $asOfDate): string
    {
        $total = Invoice::query()
            ->whereNotNull('issued_at')
            ->where('issued_at', '<=', $asOfDate)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $asOfDate->startOfDay())
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->selectRaw('SUM(total_amount - amount_paid) as outstanding')
            ->value('outstanding');

        return number_format((float) ($total ?? 0), 2, '.', '');
    }

    /**
     * Get chart data for trend visualization.
     *
     * @return array{
     *     labels: array<int, string>,
     *     outstanding: array<int, float>,
     *     overdue: array<int, float>
     * }
     */
    public function getChartData(): array
    {
        $trend = $this->getOutstandingTrend();

        $labels = [];
        $outstanding = [];
        $overdue = [];

        foreach ($trend as $row) {
            $labels[] = $row['month_label'];
            $outstanding[] = (float) $row['outstanding'];
            $overdue[] = (float) $row['overdue'];
        }

        return [
            'labels' => $labels,
            'outstanding' => $outstanding,
            'overdue' => $overdue,
        ];
    }

    /**
     * Get summary statistics.
     *
     * @return array{
     *     total_outstanding: string,
     *     total_overdue: string,
     *     customer_count: int,
     *     invoice_count: int,
     *     average_per_customer: string,
     *     overdue_percentage: float
     * }
     */
    public function getSummary(): array
    {
        $totalOutstanding = $this->getTotalOutstanding();
        $totalOverdue = $this->getTotalOverdue();
        $customerCount = $this->getCustomersWithOutstanding();

        $averagePerCustomer = $customerCount > 0
            ? bcdiv($totalOutstanding, (string) $customerCount, 2)
            : '0.00';

        $overduePercentage = bccomp($totalOutstanding, '0', 2) !== 0
            ? (float) bcdiv(bcmul($totalOverdue, '100', 2), $totalOutstanding, 1)
            : 0;

        return [
            'total_outstanding' => $totalOutstanding,
            'total_overdue' => $totalOverdue,
            'customer_count' => $customerCount,
            'invoice_count' => $this->getOutstandingInvoiceCount(),
            'average_per_customer' => $averagePerCustomer,
            'overdue_percentage' => $overduePercentage,
        ];
    }

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Get the URL to view customer's finance details.
     */
    public function getCustomerFinanceUrl(int $customerId): string
    {
        return route('filament.admin.pages.finance.customer-finance').'?customerId='.$customerId;
    }

    /**
     * Export report to CSV.
     */
    public function exportToCsv(): StreamedResponse
    {
        $summary = $this->getSummary();
        $customerData = $this->getOutstandingByCustomer();
        $typeData = $this->getOutstandingByType();
        $trendData = $this->getOutstandingTrend();
        $reportDate = now()->format('Y-m-d');

        return response()->streamDownload(function () use ($summary, $customerData, $typeData, $trendData, $reportDate): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Report header
            fputcsv($handle, ['Outstanding Exposure Report']);
            fputcsv($handle, ['Report Date', $reportDate]);
            fputcsv($handle, []);

            // Summary section
            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Total Outstanding', $summary['total_outstanding']]);
            fputcsv($handle, ['Total Overdue', $summary['total_overdue']]);
            fputcsv($handle, ['Customers with Outstanding', $summary['customer_count']]);
            fputcsv($handle, ['Outstanding Invoices', $summary['invoice_count']]);
            fputcsv($handle, ['Average per Customer', $summary['average_per_customer']]);
            fputcsv($handle, ['Overdue Percentage', $summary['overdue_percentage'].'%']);
            fputcsv($handle, []);

            // By Customer section
            fputcsv($handle, ['Outstanding by Customer']);
            fputcsv($handle, ['Customer', 'Email', 'Outstanding', 'Overdue', 'Invoices', 'Percentage']);
            foreach ($customerData as $row) {
                fputcsv($handle, [
                    $row['customer_name'],
                    $row['customer_email'] ?? '',
                    $row['outstanding'],
                    $row['overdue'],
                    $row['invoice_count'],
                    $row['percentage'].'%',
                ]);
            }
            fputcsv($handle, []);

            // By Type section
            fputcsv($handle, ['Outstanding by Invoice Type']);
            fputcsv($handle, ['Type', 'Name', 'Outstanding', 'Overdue', 'Invoices', 'Percentage']);
            foreach ($typeData as $row) {
                fputcsv($handle, [
                    $row['code'],
                    $row['label'],
                    $row['outstanding'],
                    $row['overdue'],
                    $row['invoice_count'],
                    $row['percentage'].'%',
                ]);
            }
            fputcsv($handle, []);

            // Trend section
            fputcsv($handle, ['Outstanding Trend']);
            fputcsv($handle, ['Month', 'Outstanding', 'Overdue']);
            foreach ($trendData as $row) {
                fputcsv($handle, [
                    $row['month_label'],
                    $row['outstanding'],
                    $row['overdue'],
                ]);
            }

            fclose($handle);
        }, 'outstanding-exposure-report-'.$reportDate.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
