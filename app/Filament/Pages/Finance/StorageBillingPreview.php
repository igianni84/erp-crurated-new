<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\StorageBillingStatus;
use App\Jobs\Finance\GenerateStorageBillingJob;
use App\Models\Customer\Customer;
use App\Models\Finance\StorageBillingPeriod;
use App\Services\Finance\StorageBillingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Storage Billing Preview page for Finance module.
 *
 * This page allows Finance Operators to:
 * - Preview projected INV3 invoices for a billing period
 * - See breakdown per customer and location
 * - Generate billing periods and invoices with confirmation
 */
class StorageBillingPreview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Storage Billing Preview';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 71;

    protected static ?string $title = 'Storage Billing Preview';

    protected string $view = 'filament.pages.finance.storage-billing-preview';

    /**
     * Period start date for preview.
     */
    public string $periodStart = '';

    /**
     * Period end date for preview.
     */
    public string $periodEnd = '';

    /**
     * Whether to show location breakdown.
     */
    public bool $showLocationBreakdown = true;

    /**
     * Whether to auto-issue generated invoices.
     */
    public bool $autoIssue = true;

    /**
     * Cache for preview data.
     *
     * @var Collection<int, array{customer_id: string, customer_name: string, bottle_count: int, bottle_days: int, unit_rate: string, calculated_amount: string, currency: string, has_existing_period: bool}>|null
     */
    protected ?Collection $previewDataCache = null;

    /**
     * Mount the page and set default period to previous month.
     */
    public function mount(): void
    {
        // Default to previous month
        $this->periodStart = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->periodEnd = now()->subMonth()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Get header actions for the page.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getGenerateInvoicesAction(),
        ];
    }

    /**
     * Get the Generate Invoices action.
     */
    protected function getGenerateInvoicesAction(): Action
    {
        return Action::make('generateInvoices')
            ->label('Generate Invoices')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Generate Storage Billing Invoices')
            ->modalDescription(fn (): string => $this->getGenerateConfirmationMessage())
            ->modalSubmitActionLabel('Generate Invoices')
            ->schema([
                DatePicker::make('period_start')
                    ->label('Period Start')
                    ->default(fn (): string => $this->periodStart)
                    ->required(),
                DatePicker::make('period_end')
                    ->label('Period End')
                    ->default(fn (): string => $this->periodEnd)
                    ->required(),
                Toggle::make('auto_issue')
                    ->label('Auto-issue invoices after creation')
                    ->default(fn (): bool => $this->autoIssue)
                    ->helperText('If enabled, invoices will be issued immediately after creation.'),
            ])
            ->action(function (array $data): void {
                $this->generateInvoices(
                    Carbon::parse($data['period_start']),
                    Carbon::parse($data['period_end']),
                    (bool) $data['auto_issue']
                );
            });
    }

    /**
     * Get confirmation message for generate action.
     */
    protected function getGenerateConfirmationMessage(): string
    {
        $summary = $this->getPreviewSummary();
        $currency = $summary['currency'];

        return "This will generate storage billing periods and INV3 invoices for {$summary['total_customers']} customers. ".
            "Total amount: {$currency} ".number_format((float) $summary['total_amount'], 2).'. '.
            "Period: {$summary['period_start']} to {$summary['period_end']}.";
    }

    /**
     * Generate storage billing invoices.
     */
    public function generateInvoices(Carbon $periodStart, Carbon $periodEnd, bool $autoIssue): void
    {
        // Dispatch the job
        GenerateStorageBillingJob::dispatch(
            $periodStart,
            $periodEnd,
            autoGenerateInvoices: true,
            autoIssue: $autoIssue
        );

        Notification::make()
            ->title('Storage billing generation started')
            ->body("Storage billing periods and invoices are being generated for the period {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}. Check the Invoices list for results.")
            ->success()
            ->send();

        // Clear cache to force refresh
        $this->previewDataCache = null;
    }

    /**
     * Update the preview when period changes.
     */
    public function updatedPeriodStart(): void
    {
        $this->previewDataCache = null;
    }

    /**
     * Update the preview when period changes.
     */
    public function updatedPeriodEnd(): void
    {
        $this->previewDataCache = null;
    }

    /**
     * Get preview data for the selected period.
     *
     * @return Collection<int, array{customer_id: string, customer_name: string, bottle_count: int, bottle_days: int, unit_rate: string, calculated_amount: string, currency: string, has_existing_period: bool}>
     */
    public function getPreviewData(): Collection
    {
        if ($this->previewDataCache !== null) {
            return $this->previewDataCache;
        }

        if (empty($this->periodStart) || empty($this->periodEnd)) {
            return collect();
        }

        $periodStart = Carbon::parse($this->periodStart)->startOfDay();
        $periodEnd = Carbon::parse($this->periodEnd)->endOfDay();

        // Use the job's preview method for consistent calculation
        $job = new GenerateStorageBillingJob($periodStart, $periodEnd, false, false);
        $this->previewDataCache = $job->getPreviewData();

        return $this->previewDataCache;
    }

    /**
     * Get summary statistics for the preview.
     *
     * @return array{
     *     total_customers: int,
     *     total_bottle_days: int,
     *     total_amount: string,
     *     currency: string,
     *     period_start: string,
     *     period_end: string,
     *     existing_periods: int,
     *     new_periods: int
     * }
     */
    public function getPreviewSummary(): array
    {
        $previewData = $this->getPreviewData();

        $totalBottleDays = $previewData->sum('bottle_days');
        $totalAmount = $previewData->reduce(function (string $carry, array $item): string {
            return bcadd($carry, $item['calculated_amount'], 2);
        }, '0.00');

        $existingPeriods = $previewData->where('has_existing_period', true)->count();
        $newPeriods = $previewData->where('has_existing_period', false)->count();

        return [
            'total_customers' => $previewData->count(),
            'total_bottle_days' => (int) $totalBottleDays,
            'total_amount' => $totalAmount,
            'currency' => config('finance.pricing.base_currency', 'EUR'),
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'existing_periods' => $existingPeriods,
            'new_periods' => $newPeriods,
        ];
    }

    /**
     * Get location breakdown for a specific customer.
     *
     * @return array<string, array{location_id: string, location_name: string, bottle_count: int, bottle_days: int, unit_rate: string, amount: string}>
     */
    public function getCustomerLocationBreakdown(string $customerId): array
    {
        if (! $this->showLocationBreakdown) {
            return [];
        }

        $customer = Customer::find($customerId);
        if ($customer === null) {
            return [];
        }

        $periodStart = Carbon::parse($this->periodStart)->startOfDay();
        $periodEnd = Carbon::parse($this->periodEnd)->endOfDay();

        $service = app(StorageBillingService::class);

        return $service->calculateLocationBreakdown($customer, $periodStart, $periodEnd);
    }

    /**
     * Get the period days count.
     */
    public function getPeriodDays(): int
    {
        if (empty($this->periodStart) || empty($this->periodEnd)) {
            return 0;
        }

        $start = Carbon::parse($this->periodStart);
        $end = Carbon::parse($this->periodEnd);

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * Check if a billing period already exists for a customer.
     */
    public function hasExistingPeriod(string $customerId): bool
    {
        if (empty($this->periodStart) || empty($this->periodEnd)) {
            return false;
        }

        return StorageBillingPeriod::query()
            ->where('customer_id', $customerId)
            ->where('period_start', Carbon::parse($this->periodStart)->startOfDay())
            ->where('period_end', Carbon::parse($this->periodEnd)->startOfDay())
            ->exists();
    }

    /**
     * Get the status of an existing period for a customer.
     */
    public function getExistingPeriodStatus(string $customerId): ?StorageBillingStatus
    {
        if (empty($this->periodStart) || empty($this->periodEnd)) {
            return null;
        }

        $period = StorageBillingPeriod::query()
            ->where('customer_id', $customerId)
            ->where('period_start', Carbon::parse($this->periodStart)->startOfDay())
            ->where('period_end', Carbon::parse($this->periodEnd)->startOfDay())
            ->first();

        return $period?->status;
    }

    /**
     * Get rate tier label for display.
     */
    public function getRateTierLabel(int $bottleCount): string
    {
        $rateTiers = config('finance.storage.rate_tiers', [
            ['min_bottles' => 0, 'max_bottles' => 100, 'rate' => '0.0060'],
            ['min_bottles' => 101, 'max_bottles' => 500, 'rate' => '0.0050'],
            ['min_bottles' => 501, 'max_bottles' => 1000, 'rate' => '0.0045'],
            ['min_bottles' => 1001, 'max_bottles' => null, 'rate' => '0.0040'],
        ]);

        foreach ($rateTiers as $tier) {
            $minBottles = $tier['min_bottles'];
            $maxBottles = $tier['max_bottles'];

            if ($bottleCount >= $minBottles && ($maxBottles === null || $bottleCount <= $maxBottles)) {
                if ($maxBottles === null) {
                    return "{$minBottles}+ bottles";
                }

                return "{$minBottles}-{$maxBottles} bottles";
            }
        }

        return 'Standard rate';
    }

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('finance.pricing.base_currency', 'EUR');

        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Export preview data to CSV.
     */
    public function exportPreview(): StreamedResponse
    {
        $previewData = $this->getPreviewData();
        $summary = $this->getPreviewSummary();

        return response()->streamDownload(function () use ($previewData, $summary): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // CSV Header
            fputcsv($handle, [
                'Customer ID',
                'Customer Name',
                'Bottle Count',
                'Bottle Days',
                'Unit Rate',
                'Amount',
                'Currency',
                'Existing Period',
            ]);

            foreach ($previewData as $row) {
                fputcsv($handle, [
                    $row['customer_id'],
                    $row['customer_name'],
                    $row['bottle_count'],
                    $row['bottle_days'],
                    $row['unit_rate'],
                    $row['calculated_amount'],
                    $row['currency'],
                    $row['has_existing_period'] ? 'Yes' : 'No',
                ]);
            }

            // Add summary row
            fputcsv($handle, []);
            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Period', $summary['period_start'].' to '.$summary['period_end']]);
            fputcsv($handle, ['Total Customers', $summary['total_customers']]);
            fputcsv($handle, ['Total Bottle Days', $summary['total_bottle_days']]);
            fputcsv($handle, ['Total Amount', $summary['total_amount'], $summary['currency']]);

            fclose($handle);
        }, 'storage-billing-preview-'.$this->periodStart.'-to-'.$this->periodEnd.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
