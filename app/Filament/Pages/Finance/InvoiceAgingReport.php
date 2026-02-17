<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoice Aging Report page for Finance module.
 *
 * This page provides an invoice aging report showing:
 * - Aging buckets: Current, 1-30, 31-60, 61-90, 90+ days
 * - Breakdown per customer
 * - Totals per bucket
 * - Export to CSV functionality
 */
class InvoiceAgingReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Invoice Aging';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Invoice Aging Report';

    protected string $view = 'filament.pages.finance.invoice-aging-report';

    /**
     * Filter by customer ID.
     */
    public ?string $filterCustomerId = null;

    /**
     * Search query for customer filter.
     */
    public string $customerSearch = '';

    /**
     * Report date (defaults to today).
     */
    public string $reportDate = '';

    /**
     * Cache for aging data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $agingDataCache = null;

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        $this->reportDate = now()->format('Y-m-d');
    }

    /**
     * Reset aging data cache when report date changes.
     */
    public function updatedReportDate(): void
    {
        $this->agingDataCache = null;
    }

    /**
     * Reset aging data cache when customer filter changes.
     */
    public function updatedFilterCustomerId(): void
    {
        $this->agingDataCache = null;
    }

    /**
     * Get filtered customers for autocomplete.
     *
     * @return Collection<int, Customer>
     */
    public function getFilteredCustomers(): Collection
    {
        if (strlen($this->customerSearch) < 2) {
            return collect();
        }

        return Customer::query()
            ->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->customerSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->customerSearch.'%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * Select a customer for filtering.
     */
    public function selectCustomer(string $customerId): void
    {
        $this->filterCustomerId = $customerId;
        $this->customerSearch = '';
        $this->agingDataCache = null;
    }

    /**
     * Clear customer filter.
     */
    public function clearCustomerFilter(): void
    {
        $this->filterCustomerId = null;
        $this->customerSearch = '';
        $this->agingDataCache = null;
    }

    /**
     * Get the selected customer for display.
     */
    public function getSelectedCustomer(): ?Customer
    {
        if ($this->filterCustomerId === null) {
            return null;
        }

        return Customer::find($this->filterCustomerId);
    }

    /**
     * Get the report date as Carbon instance.
     */
    protected function getReportDateCarbon(): Carbon
    {
        return Carbon::parse($this->reportDate)->startOfDay();
    }

    /**
     * Determine the aging bucket for an invoice based on days overdue.
     *
     * @return string One of: 'current', 'days_1_30', 'days_31_60', 'days_61_90', 'days_90_plus'
     */
    protected function getAgingBucket(Invoice $invoice, Carbon $reportDate): string
    {
        // If no due date or due date is in the future, it's current
        if ($invoice->due_date === null || $invoice->due_date->gte($reportDate)) {
            return 'current';
        }

        $daysOverdue = (int) $invoice->due_date->diffInDays($reportDate);

        if ($daysOverdue <= 30) {
            return 'days_1_30';
        }

        if ($daysOverdue <= 60) {
            return 'days_31_60';
        }

        if ($daysOverdue <= 90) {
            return 'days_61_90';
        }

        return 'days_90_plus';
    }

    /**
     * Get invoice aging data by customer.
     *
     * Returns collection of arrays with keys:
     * customer_id, customer_name, current, days_1_30, days_31_60, days_61_90, days_90_plus, total
     *
     * @return Collection<int, mixed>
     */
    public function getAgingData(): Collection
    {
        if ($this->agingDataCache !== null) {
            return $this->agingDataCache;
        }

        $reportDate = $this->getReportDateCarbon();

        // Get all open invoices (issued or partially paid)
        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->with('customer');

        if ($this->filterCustomerId !== null) {
            $query->where('customer_id', $this->filterCustomerId);
        }

        $invoices = $query->get();

        // Group by customer and calculate aging
        $agingByCustomer = [];

        foreach ($invoices as $invoice) {
            $customerId = $invoice->customer_id;
            $customer = $invoice->customer;

            if (! isset($agingByCustomer[$customerId])) {
                $agingByCustomer[$customerId] = [
                    'customer_id' => $customerId,
                    'customer_name' => $customer !== null ? $customer->name : 'Unknown Customer',
                    'current' => '0.00',
                    'days_1_30' => '0.00',
                    'days_31_60' => '0.00',
                    'days_61_90' => '0.00',
                    'days_90_plus' => '0.00',
                    'total' => '0.00',
                ];
            }

            $outstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
            $bucket = $this->getAgingBucket($invoice, $reportDate);

            $agingByCustomer[$customerId][$bucket] = bcadd(
                $agingByCustomer[$customerId][$bucket],
                $outstanding,
                2
            );
            $agingByCustomer[$customerId]['total'] = bcadd(
                $agingByCustomer[$customerId]['total'],
                $outstanding,
                2
            );
        }

        // Sort by total outstanding (highest first)
        uasort($agingByCustomer, function (array $a, array $b): int {
            return bccomp($b['total'], $a['total'], 2);
        });

        $this->agingDataCache = collect(array_values($agingByCustomer));

        return $this->agingDataCache;
    }

    /**
     * Get summary totals for each aging bucket.
     *
     * @return array{
     *     current: string,
     *     days_1_30: string,
     *     days_31_60: string,
     *     days_61_90: string,
     *     days_90_plus: string,
     *     total: string,
     *     customer_count: int,
     *     invoice_count: int
     * }
     */
    public function getAgingSummary(): array
    {
        $agingData = $this->getAgingData();

        $summary = [
            'current' => '0.00',
            'days_1_30' => '0.00',
            'days_31_60' => '0.00',
            'days_61_90' => '0.00',
            'days_90_plus' => '0.00',
            'total' => '0.00',
            'customer_count' => $agingData->count(),
            'invoice_count' => 0,
        ];

        foreach ($agingData as $row) {
            $summary['current'] = bcadd($summary['current'], $row['current'], 2);
            $summary['days_1_30'] = bcadd($summary['days_1_30'], $row['days_1_30'], 2);
            $summary['days_31_60'] = bcadd($summary['days_31_60'], $row['days_31_60'], 2);
            $summary['days_61_90'] = bcadd($summary['days_61_90'], $row['days_61_90'], 2);
            $summary['days_90_plus'] = bcadd($summary['days_90_plus'], $row['days_90_plus'], 2);
            $summary['total'] = bcadd($summary['total'], $row['total'], 2);
        }

        // Count invoices
        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid]);

        if ($this->filterCustomerId !== null) {
            $query->where('customer_id', $this->filterCustomerId);
        }

        $summary['invoice_count'] = $query->count();

        return $summary;
    }

    /**
     * Get aging bucket labels for display.
     *
     * @return array<string, string>
     */
    public function getAgingBucketLabels(): array
    {
        return [
            'current' => 'Current',
            'days_1_30' => '1-30 Days',
            'days_31_60' => '31-60 Days',
            'days_61_90' => '61-90 Days',
            'days_90_plus' => '90+ Days',
        ];
    }

    /**
     * Get bucket color for display.
     */
    public function getBucketColor(string $bucket): string
    {
        return match ($bucket) {
            'current' => 'success',
            'days_1_30' => 'info',
            'days_31_60' => 'warning',
            'days_61_90' => 'warning',
            'days_90_plus' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Export aging report to CSV.
     */
    public function exportToCsv(): StreamedResponse
    {
        $agingData = $this->getAgingData();
        $summary = $this->getAgingSummary();
        $bucketLabels = $this->getAgingBucketLabels();
        $reportDate = $this->reportDate;

        return response()->streamDownload(function () use ($agingData, $summary, $bucketLabels, $reportDate): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Report header
            fputcsv($handle, ['Invoice Aging Report']);
            fputcsv($handle, ['Report Date', $reportDate]);
            fputcsv($handle, []);

            // Column headers
            fputcsv($handle, [
                'Customer',
                $bucketLabels['current'],
                $bucketLabels['days_1_30'],
                $bucketLabels['days_31_60'],
                $bucketLabels['days_61_90'],
                $bucketLabels['days_90_plus'],
                'Total Outstanding',
            ]);

            // Customer rows
            foreach ($agingData as $row) {
                fputcsv($handle, [
                    $row['customer_name'],
                    $row['current'],
                    $row['days_1_30'],
                    $row['days_31_60'],
                    $row['days_61_90'],
                    $row['days_90_plus'],
                    $row['total'],
                ]);
            }

            // Summary row
            fputcsv($handle, []);
            fputcsv($handle, [
                'TOTAL',
                $summary['current'],
                $summary['days_1_30'],
                $summary['days_31_60'],
                $summary['days_61_90'],
                $summary['days_90_plus'],
                $summary['total'],
            ]);

            // Summary stats
            fputcsv($handle, []);
            fputcsv($handle, ['Summary Statistics']);
            fputcsv($handle, ['Total Customers', $summary['customer_count']]);
            fputcsv($handle, ['Total Invoices', $summary['invoice_count']]);
            fputcsv($handle, ['Total Outstanding', $summary['total']]);

            fclose($handle);
        }, 'invoice-aging-report-'.$reportDate.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Get the percentage of total for each bucket.
     *
     * @return array<string, float>
     */
    public function getBucketPercentages(): array
    {
        $summary = $this->getAgingSummary();

        if (bccomp($summary['total'], '0', 2) === 0) {
            return [
                'current' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_90_plus' => 0,
            ];
        }

        return [
            'current' => (float) bcdiv(bcmul($summary['current'], '100', 2), $summary['total'], 1),
            'days_1_30' => (float) bcdiv(bcmul($summary['days_1_30'], '100', 2), $summary['total'], 1),
            'days_31_60' => (float) bcdiv(bcmul($summary['days_31_60'], '100', 2), $summary['total'], 1),
            'days_61_90' => (float) bcdiv(bcmul($summary['days_61_90'], '100', 2), $summary['total'], 1),
            'days_90_plus' => (float) bcdiv(bcmul($summary['days_90_plus'], '100', 2), $summary['total'], 1),
        ];
    }

    /**
     * Get the URL to view customer's finance details.
     */
    public function getCustomerFinanceUrl(string $customerId): string
    {
        return route('filament.admin.pages.finance.customer-finance').'?customerId='.$customerId;
    }

    /**
     * Get invoices for a specific customer in a specific bucket.
     *
     * @return Collection<int, Invoice>
     */
    public function getInvoicesForCustomerBucket(string $customerId, string $bucket): Collection
    {
        $reportDate = $this->getReportDateCarbon();

        $invoices = Invoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->get();

        return $invoices->filter(function (Invoice $invoice) use ($bucket, $reportDate): bool {
            return $this->getAgingBucket($invoice, $reportDate) === $bucket;
        });
    }
}
