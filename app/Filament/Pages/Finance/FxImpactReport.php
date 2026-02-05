<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * FX Impact Summary Report page for Finance module.
 *
 * This page provides an FX (Foreign Exchange) impact report showing:
 * - Invoices grouped by currency
 * - Payments grouped by currency
 * - FX gain/loss calculation (if applicable)
 * - Period selector (monthly, quarterly, yearly)
 */
class FxImpactReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'FX Impact';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 63;

    protected static ?string $title = 'FX Impact Summary Report';

    protected static string $view = 'filament.pages.finance.fx-impact-report';

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
     * Base currency for FX calculations.
     */
    public string $baseCurrency = 'EUR';

    /**
     * Cache for invoice currency data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $invoiceCurrencyDataCache = null;

    /**
     * Cache for payment currency data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $paymentCurrencyDataCache = null;

    /**
     * Cache for FX gain/loss data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $fxGainLossDataCache = null;

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
     * Reset caches when period type changes.
     */
    public function updatedPeriodType(): void
    {
        $this->clearCaches();
    }

    /**
     * Reset caches when year changes.
     */
    public function updatedSelectedYear(): void
    {
        $this->clearCaches();
    }

    /**
     * Reset caches when month changes.
     */
    public function updatedSelectedMonth(): void
    {
        $this->clearCaches();
    }

    /**
     * Reset caches when quarter changes.
     */
    public function updatedSelectedQuarter(): void
    {
        $this->clearCaches();
    }

    /**
     * Clear all data caches.
     */
    protected function clearCaches(): void
    {
        $this->invoiceCurrencyDataCache = null;
        $this->paymentCurrencyDataCache = null;
        $this->fxGainLossDataCache = null;
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
     * Get invoices grouped by currency for the selected period.
     *
     * @return Collection<int, array{
     *     currency: string,
     *     invoice_count: int,
     *     total_amount: string,
     *     total_in_base: string,
     *     has_fx_rate: bool,
     *     is_base_currency: bool
     * }>
     */
    public function getInvoicesByCurrency(): Collection
    {
        if ($this->invoiceCurrencyDataCache !== null) {
            return $this->invoiceCurrencyDataCache;
        }

        $periodStart = $this->getPeriodStart();
        $periodEnd = $this->getPeriodEnd();

        // Get all issued invoices in period
        $invoices = Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$periodStart, $periodEnd])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->get();

        // Group by currency
        $data = $invoices->groupBy('currency')->map(function (Collection $currencyInvoices, string $currency): array {
            $totalAmount = '0.00';
            $totalInBase = '0.00';
            $hasFxRate = false;

            foreach ($currencyInvoices as $invoice) {
                $totalAmount = bcadd($totalAmount, $invoice->total_amount, 2);

                // Calculate base currency equivalent
                if ($invoice->currency === $this->baseCurrency) {
                    $totalInBase = bcadd($totalInBase, $invoice->total_amount, 2);
                } elseif ($invoice->fx_rate_at_issuance !== null) {
                    $hasFxRate = true;
                    // FX rate is stored as "1 {currency} = X EUR"
                    $amountInBase = bcmul($invoice->total_amount, $invoice->fx_rate_at_issuance, 2);
                    $totalInBase = bcadd($totalInBase, $amountInBase, 2);
                }
            }

            return [
                'currency' => $currency,
                'invoice_count' => $currencyInvoices->count(),
                'total_amount' => $totalAmount,
                'total_in_base' => $totalInBase,
                'has_fx_rate' => $hasFxRate,
                'is_base_currency' => $currency === $this->baseCurrency,
            ];
        })->sortByDesc('total_amount')->values();

        $this->invoiceCurrencyDataCache = $data;

        return $data;
    }

    /**
     * Get payments grouped by currency for the selected period.
     *
     * @return Collection<int, array{
     *     currency: string,
     *     payment_count: int,
     *     total_amount: string,
     *     is_base_currency: bool
     * }>
     */
    public function getPaymentsByCurrency(): Collection
    {
        if ($this->paymentCurrencyDataCache !== null) {
            return $this->paymentCurrencyDataCache;
        }

        $periodStart = $this->getPeriodStart();
        $periodEnd = $this->getPeriodEnd();

        // Get all confirmed payments in period
        $payments = Payment::query()
            ->whereBetween('received_at', [$periodStart, $periodEnd])
            ->where('status', PaymentStatus::Confirmed)
            ->get();

        // Group by currency
        $data = $payments->groupBy('currency')->map(function (Collection $currencyPayments, string $currency): array {
            $totalAmount = '0.00';

            foreach ($currencyPayments as $payment) {
                $totalAmount = bcadd($totalAmount, $payment->amount, 2);
            }

            return [
                'currency' => $currency,
                'payment_count' => $currencyPayments->count(),
                'total_amount' => $totalAmount,
                'is_base_currency' => $currency === $this->baseCurrency,
            ];
        })->sortByDesc('total_amount')->values();

        $this->paymentCurrencyDataCache = $data;

        return $data;
    }

    /**
     * Calculate FX gain/loss for invoices paid in the period.
     *
     * FX gain/loss occurs when:
     * - Invoice was issued in foreign currency with an FX rate
     * - Payment was received (potentially at a different effective rate)
     *
     * This is a simplified calculation based on recorded FX rates.
     *
     * @return Collection<int, array{
     *     currency: string,
     *     invoice_count: int,
     *     total_invoiced: string,
     *     total_invoiced_base: string,
     *     estimated_fx_impact: string,
     *     fx_impact_type: string
     * }>
     */
    public function getFxGainLoss(): Collection
    {
        if ($this->fxGainLossDataCache !== null) {
            return $this->fxGainLossDataCache;
        }

        $periodStart = $this->getPeriodStart();
        $periodEnd = $this->getPeriodEnd();

        // Get paid invoices in foreign currencies that have FX rates
        $invoices = Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$periodStart, $periodEnd])
            ->where('currency', '!=', $this->baseCurrency)
            ->whereNotNull('fx_rate_at_issuance')
            ->whereIn('status', [InvoiceStatus::Paid, InvoiceStatus::PartiallyPaid])
            ->get();

        if ($invoices->isEmpty()) {
            $this->fxGainLossDataCache = collect();

            return $this->fxGainLossDataCache;
        }

        // Group by currency and calculate
        $data = $invoices->groupBy('currency')->map(function (Collection $currencyInvoices, string $currency): array {
            $totalInvoiced = '0.00';
            $totalInvoicedBase = '0.00';

            foreach ($currencyInvoices as $invoice) {
                $totalInvoiced = bcadd($totalInvoiced, $invoice->total_amount, 2);

                // Calculate base currency equivalent at issuance rate
                if ($invoice->fx_rate_at_issuance !== null) {
                    $amountInBase = bcmul($invoice->total_amount, $invoice->fx_rate_at_issuance, 2);
                    $totalInvoicedBase = bcadd($totalInvoicedBase, $amountInBase, 2);
                }
            }

            // For this simplified report, we note that FX impact would require
            // comparing issuance rate vs settlement rate. Since payments don't
            // store FX rates, we show the base currency equivalent and note
            // that actual FX gain/loss requires treasury reconciliation.
            return [
                'currency' => $currency,
                'invoice_count' => $currencyInvoices->count(),
                'total_invoiced' => $totalInvoiced,
                'total_invoiced_base' => $totalInvoicedBase,
                'estimated_fx_impact' => '0.00', // Would need payment FX rates
                'fx_impact_type' => 'pending_reconciliation',
            ];
        })->sortByDesc('total_invoiced')->values();

        $this->fxGainLossDataCache = $data;

        return $data;
    }

    /**
     * Get summary statistics for the report.
     *
     * @return array{
     *     total_invoice_currencies: int,
     *     total_payment_currencies: int,
     *     total_invoiced_base: string,
     *     total_payments_base: string,
     *     foreign_invoice_count: int,
     *     foreign_invoice_amount: string,
     *     base_invoice_count: int,
     *     base_invoice_amount: string
     * }
     */
    public function getSummary(): array
    {
        $invoiceData = $this->getInvoicesByCurrency();
        $paymentData = $this->getPaymentsByCurrency();

        $totalInvoicedBase = '0.00';
        $totalPaymentsBase = '0.00';
        $foreignInvoiceCount = 0;
        $foreignInvoiceAmount = '0.00';
        $baseInvoiceCount = 0;
        $baseInvoiceAmount = '0.00';

        foreach ($invoiceData as $row) {
            $totalInvoicedBase = bcadd($totalInvoicedBase, $row['total_in_base'], 2);

            if ($row['is_base_currency']) {
                $baseInvoiceCount += $row['invoice_count'];
                $baseInvoiceAmount = bcadd($baseInvoiceAmount, $row['total_amount'], 2);
            } else {
                $foreignInvoiceCount += $row['invoice_count'];
                $foreignInvoiceAmount = bcadd($foreignInvoiceAmount, $row['total_in_base'], 2);
            }
        }

        foreach ($paymentData as $row) {
            if ($row['is_base_currency']) {
                $totalPaymentsBase = bcadd($totalPaymentsBase, $row['total_amount'], 2);
            }
            // Note: For non-base currency payments, we'd need FX rates at settlement
        }

        return [
            'total_invoice_currencies' => $invoiceData->count(),
            'total_payment_currencies' => $paymentData->count(),
            'total_invoiced_base' => $totalInvoicedBase,
            'total_payments_base' => $totalPaymentsBase,
            'foreign_invoice_count' => $foreignInvoiceCount,
            'foreign_invoice_amount' => $foreignInvoiceAmount,
            'base_invoice_count' => $baseInvoiceCount,
            'base_invoice_amount' => $baseInvoiceAmount,
        ];
    }

    /**
     * Get the percentage of foreign currency invoices.
     */
    public function getForeignCurrencyPercentage(): float
    {
        $summary = $this->getSummary();
        $totalBase = $summary['total_invoiced_base'];

        if (bccomp($totalBase, '0', 2) === 0) {
            return 0.0;
        }

        return (float) bcdiv(
            bcmul($summary['foreign_invoice_amount'], '100', 2),
            $totalBase,
            1
        );
    }

    /**
     * Get currency symbol for display.
     */
    public function getCurrencySymbol(string $currency): string
    {
        return match ($currency) {
            'EUR' => '€',
            'GBP' => '£',
            'USD' => '$',
            'CHF' => 'CHF',
            'JPY' => '¥',
            default => $currency,
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
     * Export report to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $invoiceData = $this->getInvoicesByCurrency();
        $paymentData = $this->getPaymentsByCurrency();
        $fxData = $this->getFxGainLoss();
        $summary = $this->getSummary();
        $periodLabel = $this->getPeriodLabel();
        $reportDate = now()->format('Y-m-d');

        return response()->streamDownload(function () use ($invoiceData, $paymentData, $fxData, $summary, $periodLabel, $reportDate): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Report header
            fputcsv($handle, ['FX Impact Summary Report']);
            fputcsv($handle, ['Period', $periodLabel]);
            fputcsv($handle, ['Report Date', $reportDate]);
            fputcsv($handle, ['Base Currency', $this->baseCurrency]);
            fputcsv($handle, []);

            // Summary section
            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Total Invoice Currencies', $summary['total_invoice_currencies']]);
            fputcsv($handle, ['Total Payment Currencies', $summary['total_payment_currencies']]);
            fputcsv($handle, ['Total Invoiced (Base Currency)', $summary['total_invoiced_base']]);
            fputcsv($handle, ['Base Currency Invoices', $summary['base_invoice_count'].' invoices, '.$summary['base_invoice_amount'].' '.$this->baseCurrency]);
            fputcsv($handle, ['Foreign Currency Invoices', $summary['foreign_invoice_count'].' invoices, '.$summary['foreign_invoice_amount'].' '.$this->baseCurrency.' equivalent']);
            fputcsv($handle, []);

            // Invoices by Currency section
            fputcsv($handle, ['Invoices by Currency']);
            fputcsv($handle, ['Currency', 'Invoice Count', 'Total Amount', 'Total in Base ('.$this->baseCurrency.')', 'Has FX Rate']);
            foreach ($invoiceData as $row) {
                fputcsv($handle, [
                    $row['currency'],
                    $row['invoice_count'],
                    $row['total_amount'],
                    $row['total_in_base'],
                    $row['has_fx_rate'] ? 'Yes' : ($row['is_base_currency'] ? 'N/A' : 'No'),
                ]);
            }
            fputcsv($handle, []);

            // Payments by Currency section
            fputcsv($handle, ['Payments by Currency']);
            fputcsv($handle, ['Currency', 'Payment Count', 'Total Amount']);
            foreach ($paymentData as $row) {
                fputcsv($handle, [
                    $row['currency'],
                    $row['payment_count'],
                    $row['total_amount'],
                ]);
            }
            fputcsv($handle, []);

            // FX Impact section (if any foreign currency invoices)
            if ($fxData->isNotEmpty()) {
                fputcsv($handle, ['Foreign Currency Invoice Summary']);
                fputcsv($handle, ['Currency', 'Invoice Count', 'Total Invoiced', 'Total in Base ('.$this->baseCurrency.')', 'Notes']);
                foreach ($fxData as $row) {
                    fputcsv($handle, [
                        $row['currency'],
                        $row['invoice_count'],
                        $row['total_invoiced'],
                        $row['total_invoiced_base'],
                        'FX impact requires treasury reconciliation',
                    ]);
                }
            }

            fclose($handle);
        }, 'fx-impact-report-'.str_replace(' ', '-', strtolower($periodLabel)).'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
