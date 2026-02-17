<?php

namespace App\Jobs\Finance;

use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\StorageBillingStatus;
use App\Enums\Inventory\BottleState;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\StorageBillingPeriod;
use App\Models\Inventory\SerializedBottle;
use App\Services\Finance\InvoiceService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to generate storage billing periods and INV3 invoices.
 *
 * This job runs at the end of a billing period (monthly/quarterly) and:
 * 1. Finds all customers with storage usage during the period
 * 2. Creates StorageBillingPeriod records with calculated bottle-days
 * 3. Optionally creates INV3 invoices for each period with usage > 0
 *
 * The billing calculation uses bottle-days:
 * - For each bottle stored during the period, calculate days stored
 * - bottle_days = sum(days each bottle was stored)
 * - calculated_amount = bottle_days * unit_rate
 *
 * This job supports both automated scheduling (first day of new period)
 * and manual triggering from the Storage Billing Preview page.
 */
class GenerateStorageBillingJob implements ShouldQueue
{
    use Queueable;

    /**
     * The start date of the billing period.
     */
    protected Carbon $periodStart;

    /**
     * The end date of the billing period.
     */
    protected Carbon $periodEnd;

    /**
     * Whether to automatically generate INV3 invoices after creating billing periods.
     */
    protected bool $autoGenerateInvoices;

    /**
     * Whether to automatically issue the generated invoices.
     */
    protected bool $autoIssue;

    /**
     * Create a new job instance.
     *
     * @param  Carbon  $periodStart  The start date of the billing period
     * @param  Carbon  $periodEnd  The end date of the billing period
     * @param  bool  $autoGenerateInvoices  Whether to auto-generate INV3 invoices
     * @param  bool  $autoIssue  Whether to auto-issue the generated invoices
     */
    public function __construct(
        Carbon $periodStart,
        Carbon $periodEnd,
        bool $autoGenerateInvoices = true,
        bool $autoIssue = true
    ) {
        $this->periodStart = $periodStart->startOfDay();
        $this->periodEnd = $periodEnd->endOfDay();
        $this->autoGenerateInvoices = $autoGenerateInvoices;
        $this->autoIssue = $autoIssue;
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        Log::channel('finance')->info('Starting storage billing generation', [
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
            'auto_generate_invoices' => $this->autoGenerateInvoices,
            'auto_issue' => $this->autoIssue,
        ]);

        // Find all customers with storage during this period
        $customersWithStorage = $this->getCustomersWithStorage();

        $periodsCreated = 0;
        $periodsSkipped = 0;
        $invoicesCreated = 0;
        $errorCount = 0;

        foreach ($customersWithStorage as $customerId) {
            try {
                // Check if billing period already exists for this customer and period
                $existingPeriod = StorageBillingPeriod::query()
                    ->where('customer_id', $customerId)
                    ->where('period_start', $this->periodStart)
                    ->where('period_end', $this->periodEnd->startOfDay())
                    ->first();

                if ($existingPeriod !== null) {
                    Log::channel('finance')->info('Storage billing period already exists', [
                        'customer_id' => $customerId,
                        'period_id' => $existingPeriod->id,
                    ]);
                    $periodsSkipped++;

                    continue;
                }

                // Calculate storage usage for this customer
                $usageData = $this->calculateStorageUsage($customerId);

                // Skip if no usage (bottle_days = 0)
                if ($usageData['bottle_days'] === 0) {
                    Log::channel('finance')->info('Skipping customer with zero usage', [
                        'customer_id' => $customerId,
                    ]);
                    $periodsSkipped++;

                    continue;
                }

                // Create storage billing period
                $billingPeriod = $this->createBillingPeriod($customerId, $usageData);
                $periodsCreated++;

                Log::channel('finance')->info('Created storage billing period', [
                    'period_id' => $billingPeriod->id,
                    'customer_id' => $customerId,
                    'bottle_count' => $usageData['bottle_count'],
                    'bottle_days' => $usageData['bottle_days'],
                    'calculated_amount' => $usageData['calculated_amount'],
                ]);

                // Optionally create INV3 invoice
                if ($this->autoGenerateInvoices && bccomp($usageData['calculated_amount'], '0', 2) > 0) {
                    $invoice = $this->createStorageInvoice($billingPeriod, $invoiceService);

                    if ($invoice !== null) {
                        $invoicesCreated++;

                        Log::channel('finance')->info('Created INV3 invoice for storage billing', [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'period_id' => $billingPeriod->id,
                            'customer_id' => $customerId,
                            'total_amount' => $invoice->total_amount,
                        ]);
                    }
                }
            } catch (Throwable $e) {
                Log::channel('finance')->error('Failed to process storage billing for customer', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
            }
        }

        Log::channel('finance')->info('Completed storage billing generation', [
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
            'customers_processed' => $customersWithStorage->count(),
            'periods_created' => $periodsCreated,
            'periods_skipped' => $periodsSkipped,
            'invoices_created' => $invoicesCreated,
            'errors' => $errorCount,
        ]);
    }

    /**
     * Get all customer IDs that had bottles in storage during the billing period.
     *
     * @return Collection<int, string>
     */
    protected function getCustomersWithStorage(): Collection
    {
        // Find customers who had bottles stored (Stored state) during the period
        // We check bottles that were in storage at any point during the period
        // by looking at bottles that are currently stored OR were stored
        // and have movement history showing storage during this period

        // For simplicity, we look at bottles currently stored or that have
        // allocation with a custody_holder (customer)
        // In a full implementation, this would use inventory_movements to track
        // bottles stored during the specific period

        return SerializedBottle::query()
            ->whereNotNull('custody_holder')
            ->where('state', '!=', BottleState::Destroyed->value)
            ->where('state', '!=', BottleState::Shipped->value)
            ->distinct()
            ->pluck('custody_holder');
    }

    /**
     * Calculate storage usage for a customer during the billing period.
     *
     * @return array{
     *     bottle_count: int,
     *     bottle_days: int,
     *     unit_rate: string,
     *     calculated_amount: string,
     *     currency: string,
     *     metadata: array<string, mixed>
     * }
     */
    protected function calculateStorageUsage(string $customerId): array
    {
        // Get the number of days in the billing period
        $periodDays = (int) $this->periodStart->diffInDays($this->periodEnd) + 1;

        // Count bottles currently in storage for this customer
        // In a full implementation, this would calculate based on daily snapshots
        // or movement history to get accurate bottle-days
        $bottleCount = SerializedBottle::query()
            ->where('custody_holder', $customerId)
            ->whereIn('state', [
                BottleState::Stored->value,
                BottleState::ReservedForPicking->value,
            ])
            ->count();

        // Calculate bottle-days (simplified: assume all bottles stored for full period)
        // In production, this should calculate based on actual days each bottle was stored
        $bottleDays = $bottleCount * $periodDays;

        // Get the unit rate for this customer (from config or customer-specific rate)
        $unitRate = $this->getUnitRate($customerId, $bottleCount);

        // Calculate the amount
        $calculatedAmount = bcmul((string) $bottleDays, $unitRate, 2);

        return [
            'bottle_count' => $bottleCount,
            'bottle_days' => $bottleDays,
            'unit_rate' => $unitRate,
            'calculated_amount' => $calculatedAmount,
            'currency' => config('finance.pricing.base_currency', 'EUR'),
            'metadata' => [
                'period_days' => $periodDays,
                'calculation_method' => 'simple_bottle_days',
                'calculated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Get the storage unit rate for a customer.
     *
     * Rate can vary by volume tier, customer tier, or be a flat rate.
     * This is a simplified implementation - production would integrate with
     * Module S pricing or a rate tier configuration.
     *
     * @param  string  $customerId  The customer ID
     * @param  int  $bottleCount  The number of bottles (for tier calculation)
     * @return string The rate per bottle-day as a decimal string
     */
    protected function getUnitRate(string $customerId, int $bottleCount): string
    {
        // Default rate from config (per bottle-day)
        // Rate tiers could be: higher volume = lower rate
        $defaultRate = config('finance.storage.default_rate_per_bottle_day', '0.0050');

        // Volume-based tier rates (example tiers)
        $rateTiers = config('finance.storage.rate_tiers', [
            ['min_bottles' => 0, 'max_bottles' => 100, 'rate' => '0.0060'],
            ['min_bottles' => 101, 'max_bottles' => 500, 'rate' => '0.0050'],
            ['min_bottles' => 501, 'max_bottles' => 1000, 'rate' => '0.0045'],
            ['min_bottles' => 1001, 'max_bottles' => null, 'rate' => '0.0040'],
        ]);

        // Find applicable tier
        foreach ($rateTiers as $tier) {
            $minBottles = $tier['min_bottles'];
            $maxBottles = $tier['max_bottles'];

            if ($bottleCount >= $minBottles && ($maxBottles === null || $bottleCount <= $maxBottles)) {
                return $tier['rate'];
            }
        }

        return $defaultRate;
    }

    /**
     * Create a StorageBillingPeriod record.
     *
     * @param  array{bottle_count: int, bottle_days: int, unit_rate: string, calculated_amount: string, currency: string, metadata: array<string, mixed>}  $usageData
     */
    protected function createBillingPeriod(string $customerId, array $usageData): StorageBillingPeriod
    {
        return StorageBillingPeriod::create([
            'customer_id' => $customerId,
            'location_id' => null, // Aggregated across all locations
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd->startOfDay(), // Store end date without time
            'bottle_count' => $usageData['bottle_count'],
            'bottle_days' => $usageData['bottle_days'],
            'unit_rate' => $usageData['unit_rate'],
            'calculated_amount' => $usageData['calculated_amount'],
            'currency' => $usageData['currency'],
            'status' => StorageBillingStatus::Pending,
            'invoice_id' => null,
            'calculated_at' => now(),
            'metadata' => $usageData['metadata'],
        ]);
    }

    /**
     * Create an INV3 invoice for a storage billing period.
     */
    protected function createStorageInvoice(
        StorageBillingPeriod $billingPeriod,
        InvoiceService $invoiceService
    ): ?Invoice {
        return DB::transaction(function () use ($billingPeriod, $invoiceService) {
            $customer = $billingPeriod->customer;

            // Build invoice line description
            $description = $this->buildInvoiceLineDescription($billingPeriod);

            // Build invoice lines
            $lines = [
                [
                    'description' => $description,
                    'quantity' => (string) $billingPeriod->bottle_days,
                    'unit_price' => $billingPeriod->unit_rate,
                    'tax_rate' => $this->getStorageTaxRate($customer),
                    'sellable_sku_id' => null,
                    'metadata' => [
                        'storage_billing_period_id' => $billingPeriod->id,
                        'bottle_count' => $billingPeriod->bottle_count,
                        'period_start' => $billingPeriod->period_start->toDateString(),
                        'period_end' => $billingPeriod->period_end->toDateString(),
                        'line_type' => 'storage_fee',
                    ],
                ],
            ];

            // Calculate due date (30 days from issuance)
            $dueDate = now()->addDays(config('finance.default_due_date_days.storage_fee', 30));

            // Create draft invoice
            $invoice = $invoiceService->createDraft(
                invoiceType: InvoiceType::StorageFee,
                customer: $customer,
                lines: $lines,
                sourceType: 'storage_billing_period',
                sourceId: $billingPeriod->id,
                currency: $billingPeriod->currency,
                dueDate: $dueDate,
                notes: "Storage fees for period {$billingPeriod->period_start->format('Y-m-d')} to {$billingPeriod->period_end->format('Y-m-d')}"
            );

            // Link the invoice to the billing period
            $billingPeriod->invoice_id = $invoice->id;
            $billingPeriod->status = StorageBillingStatus::Invoiced;
            $billingPeriod->save();

            // Optionally issue the invoice
            if ($this->autoIssue) {
                $invoice = $invoiceService->issue($invoice);
            }

            return $invoice;
        });
    }

    /**
     * Build the invoice line description for storage fees.
     */
    protected function buildInvoiceLineDescription(StorageBillingPeriod $billingPeriod): string
    {
        $periodLabel = $billingPeriod->period_start->format('M j').' - '.$billingPeriod->period_end->format('M j, Y');

        return "Wine Storage Services ({$periodLabel}) - {$billingPeriod->bottle_count} bottles, {$billingPeriod->bottle_days} bottle-days";
    }

    /**
     * Get the VAT/tax rate for storage services.
     *
     * @return string Tax rate as decimal (e.g., "22.00" for 22%)
     */
    protected function getStorageTaxRate(Customer $customer): string
    {
        // Storage services are typically taxed at standard VAT rate
        // In a full implementation, this would check customer's VAT status
        // and apply reverse charge for B2B EU customers with valid VAT number

        return config('finance.pricing.default_vat_rate', '22.00');
    }

    /**
     * Create a job for the previous month's billing period.
     *
     * Convenience factory method for monthly billing.
     */
    public static function forPreviousMonth(
        bool $autoGenerateInvoices = true,
        bool $autoIssue = true
    ): self {
        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        return new self($periodStart, $periodEnd, $autoGenerateInvoices, $autoIssue);
    }

    /**
     * Create a job for the previous quarter's billing period.
     *
     * Convenience factory method for quarterly billing.
     */
    public static function forPreviousQuarter(
        bool $autoGenerateInvoices = true,
        bool $autoIssue = true
    ): self {
        $periodStart = now()->subQuarter()->startOfQuarter();
        $periodEnd = now()->subQuarter()->endOfQuarter();

        return new self($periodStart, $periodEnd, $autoGenerateInvoices, $autoIssue);
    }

    /**
     * Get a preview of storage billing for all customers.
     *
     * Returns data that can be shown in the Storage Billing Preview page
     * before generating actual billing periods and invoices.
     *
     * @return Collection<int, array{
     *     customer_id: string,
     *     customer_name: string,
     *     bottle_count: int,
     *     bottle_days: int,
     *     unit_rate: string,
     *     calculated_amount: string,
     *     currency: string,
     *     has_existing_period: bool
     * }>
     */
    public function getPreviewData(): Collection
    {
        $customersWithStorage = $this->getCustomersWithStorage();
        $previewData = collect();

        foreach ($customersWithStorage as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer === null) {
                continue;
            }

            $usageData = $this->calculateStorageUsage($customerId);

            // Skip if no usage
            if ($usageData['bottle_days'] === 0) {
                continue;
            }

            // Check if period already exists
            $existingPeriod = StorageBillingPeriod::query()
                ->where('customer_id', $customerId)
                ->where('period_start', $this->periodStart)
                ->where('period_end', $this->periodEnd->startOfDay())
                ->exists();

            $previewData->push([
                'customer_id' => $customerId,
                'customer_name' => $customer->name,
                'bottle_count' => $usageData['bottle_count'],
                'bottle_days' => $usageData['bottle_days'],
                'unit_rate' => $usageData['unit_rate'],
                'calculated_amount' => $usageData['calculated_amount'],
                'currency' => $usageData['currency'],
                'has_existing_period' => $existingPeriod,
            ]);
        }

        return $previewData->sortBy('customer_name');
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
     *     period_end: string
     * }
     */
    public function getPreviewSummary(): array
    {
        $previewData = $this->getPreviewData();

        $totalBottleDays = $previewData->sum('bottle_days');
        $totalAmount = $previewData->reduce(function (string $carry, array $item): string {
            return bcadd($carry, $item['calculated_amount'], 2);
        }, '0.00');

        return [
            'total_customers' => $previewData->count(),
            'total_bottle_days' => $totalBottleDays,
            'total_amount' => $totalAmount,
            'currency' => config('finance.pricing.base_currency', 'EUR'),
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
        ];
    }
}
