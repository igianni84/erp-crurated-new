<?php

namespace App\Jobs\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\StorageBillingStatus;
use App\Events\Finance\StoragePaymentBlocked;
use App\Models\Finance\Invoice;
use App\Models\Finance\StorageBillingPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to block storage billing periods with overdue INV3 invoices.
 *
 * This job runs daily and:
 * 1. Finds all invoiced storage billing periods with INV3 invoices overdue > X days (configurable)
 * 2. Blocks the storage billing period (sets status = blocked)
 * 3. Emits StoragePaymentBlocked event for Module B/C eligibility updates
 * 4. Logs block events
 *
 * The overdue threshold is configurable via the finance.storage_overdue_block_days config.
 * Default is 30 days.
 *
 * Custody operations (redemptions, transfers, shipments) should be blocked for customers
 * with blocked storage billing periods. This is enforced by other modules listening
 * to the StoragePaymentBlocked event.
 */
class BlockOverdueStorageBillingJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of days overdue before blocking.
     * If null, uses config value.
     */
    protected ?int $overdueDaysThreshold;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $overdueDaysThreshold  Override the config value for overdue days threshold
     */
    public function __construct(?int $overdueDaysThreshold = null)
    {
        $this->overdueDaysThreshold = $overdueDaysThreshold;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $thresholdDays = $this->getOverdueDaysThreshold();

        Log::channel('finance')->info('Starting storage billing block check', [
            'overdue_threshold_days' => $thresholdDays,
        ]);

        // Find storage billing periods with overdue INV3 invoices that exceed the threshold
        $periodsToBlock = $this->getPeriodsToBlock($thresholdDays);

        $blockedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($periodsToBlock as $data) {
            try {
                /** @var StorageBillingPeriod $period */
                $period = $data['period'];
                /** @var Invoice $overdueInvoice */
                $overdueInvoice = $data['overdue_invoice'];
                $daysOverdue = $data['days_overdue'];

                // Skip if period is already blocked or paid
                if ($period->isBlocked() || $period->isPaid()) {
                    Log::channel('finance')->info('Skipping storage billing period', [
                        'period_id' => $period->id,
                        'status' => $period->status->value,
                        'reason' => $period->isBlocked() ? 'already blocked' : 'already paid',
                    ]);
                    $skippedCount++;

                    continue;
                }

                // Block the storage billing period
                $this->blockPeriod($period, $overdueInvoice, $daysOverdue);

                $blockedCount++;
            } catch (\Throwable $e) {
                Log::channel('finance')->error('Failed to block storage billing period', [
                    'period_id' => $data['period']->id ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
            }
        }

        Log::channel('finance')->info('Completed storage billing block check', [
            'overdue_threshold_days' => $thresholdDays,
            'blocked' => $blockedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'total_checked' => count($periodsToBlock),
        ]);
    }

    /**
     * Get the overdue days threshold from config or constructor override.
     */
    protected function getOverdueDaysThreshold(): int
    {
        if ($this->overdueDaysThreshold !== null) {
            return $this->overdueDaysThreshold;
        }

        return (int) config('finance.storage_overdue_block_days', 30);
    }

    /**
     * Get storage billing periods with overdue INV3 invoices that exceed the threshold.
     *
     * @return array<int, array{period: StorageBillingPeriod, overdue_invoice: Invoice, days_overdue: int}>
     */
    protected function getPeriodsToBlock(int $thresholdDays): array
    {
        $thresholdDate = now()->subDays($thresholdDays)->startOfDay();

        // Find overdue INV3 invoices that are past the threshold
        $overdueInvoices = Invoice::query()
            ->where('invoice_type', InvoiceType::StorageFee)
            ->where('status', InvoiceStatus::Issued)
            ->where('source_type', 'storage_billing_period')
            ->whereNotNull('source_id')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $thresholdDate)
            ->get();

        $result = [];

        foreach ($overdueInvoices as $invoice) {
            /** @var Invoice $invoice */
            $period = $invoice->getStorageBillingPeriod();

            if ($period === null) {
                Log::channel('finance')->warning('Could not find storage billing period for overdue invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'source_id' => $invoice->source_id,
                ]);

                continue;
            }

            // Only consider invoiced periods (not already blocked or paid)
            if (! $period->isInvoiced()) {
                continue;
            }

            $daysOverdue = $invoice->getDaysOverdue();

            if ($daysOverdue === null || $daysOverdue < $thresholdDays) {
                continue;
            }

            $result[] = [
                'period' => $period,
                'overdue_invoice' => $invoice,
                'days_overdue' => $daysOverdue,
            ];
        }

        return $result;
    }

    /**
     * Block a storage billing period due to overdue payment.
     */
    protected function blockPeriod(StorageBillingPeriod $period, Invoice $overdueInvoice, int $daysOverdue): void
    {
        $reason = "Blocked due to overdue INV3 payment. Invoice #{$overdueInvoice->invoice_number} is {$daysOverdue} days overdue.";

        DB::transaction(function () use ($period, $overdueInvoice, $daysOverdue, $reason): void {
            // Update storage billing period status to blocked
            $period->status = StorageBillingStatus::Blocked;
            $period->save();

            // Log the block in audit trail
            $period->auditLogs()->create([
                'event' => 'storage_billing_blocked',
                'old_values' => ['status' => StorageBillingStatus::Invoiced->value],
                'new_values' => [
                    'status' => StorageBillingStatus::Blocked->value,
                    'overdue_invoice_id' => $overdueInvoice->id,
                    'overdue_invoice_number' => $overdueInvoice->invoice_number,
                    'days_overdue' => $daysOverdue,
                    'reason' => $reason,
                ],
                'user_id' => null, // Automated system action
            ]);

            // Log the event in the invoice audit trail as well
            $overdueInvoice->auditLogs()->create([
                'event' => 'custody_blocked_due_to_overdue',
                'old_values' => null,
                'new_values' => [
                    'storage_billing_period_id' => $period->id,
                    'days_overdue' => $daysOverdue,
                ],
                'user_id' => null, // Automated system action
            ]);
        });

        // Dispatch event for other modules (e.g., Module B/C eligibility)
        StoragePaymentBlocked::dispatch($period, $overdueInvoice, $daysOverdue, $reason);

        Log::channel('finance')->info('Blocked storage billing period due to overdue payment', [
            'period_id' => $period->id,
            'customer_id' => $period->customer_id,
            'location_id' => $period->location_id,
            'period_label' => $period->getPeriodLabel(),
            'overdue_invoice_id' => $overdueInvoice->id,
            'overdue_invoice_number' => $overdueInvoice->invoice_number,
            'days_overdue' => $daysOverdue,
        ]);
    }

    /**
     * Get query builder for storage billing periods at risk of being blocked.
     * Useful for reporting and dashboard widgets.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    public static function getOverdueInvoicesQuery(int $thresholdDays): \Illuminate\Database\Eloquent\Builder
    {
        $thresholdDate = now()->subDays($thresholdDays)->startOfDay();

        return Invoice::query()
            ->where('invoice_type', InvoiceType::StorageFee)
            ->where('status', InvoiceStatus::Issued)
            ->where('source_type', 'storage_billing_period')
            ->whereNotNull('source_id')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $thresholdDate);
    }

    /**
     * Get count of storage billing periods at risk of being blocked.
     * Useful for dashboard warnings.
     */
    public static function getAtRiskCount(?int $thresholdDays = null): int
    {
        $threshold = $thresholdDays ?? (int) config('finance.storage_overdue_block_days', 30);

        return self::getOverdueInvoicesQuery($threshold)->count();
    }

    /**
     * Get storage billing periods currently blocked.
     *
     * @return \Illuminate\Database\Eloquent\Builder<StorageBillingPeriod>
     */
    public static function getBlockedPeriodsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return StorageBillingPeriod::query()
            ->where('status', StorageBillingStatus::Blocked);
    }

    /**
     * Get count of currently blocked storage billing periods.
     */
    public static function getBlockedCount(): int
    {
        return self::getBlockedPeriodsQuery()->count();
    }

    /**
     * Get blocked storage billing periods for a specific customer.
     *
     * @return \Illuminate\Database\Eloquent\Builder<StorageBillingPeriod>
     */
    public static function getBlockedPeriodsForCustomer(string $customerId): \Illuminate\Database\Eloquent\Builder
    {
        return self::getBlockedPeriodsQuery()->where('customer_id', $customerId);
    }

    /**
     * Check if a customer has any blocked storage billing periods.
     */
    public static function customerHasBlockedPeriods(string $customerId): bool
    {
        return self::getBlockedPeriodsForCustomer($customerId)->exists();
    }
}
