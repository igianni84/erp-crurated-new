<?php

namespace App\Jobs\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to identify and log overdue invoices.
 *
 * This job should be scheduled to run daily to identify invoices that have
 * become overdue. It logs the count of overdue invoices for monitoring
 * and could be extended to send notifications or trigger follow-up actions.
 */
class IdentifyOverdueInvoicesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Find all invoices that are overdue (issued status with due_date in the past)
        $overdueInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->get();

        $overdueCount = $overdueInvoices->count();

        if ($overdueCount > 0) {
            // Calculate total overdue amount
            $totalOverdueAmount = $overdueInvoices->reduce(function (string $carry, Invoice $invoice): string {
                return bcadd($carry, $invoice->getOutstandingAmount(), 2);
            }, '0');

            Log::channel('finance')->info('Overdue invoices identified', [
                'overdue_count' => $overdueCount,
                'total_overdue_amount' => $totalOverdueAmount,
                'invoice_ids' => $overdueInvoices->pluck('id')->toArray(),
            ]);

            // Group by days overdue for reporting
            $groupedByAge = $overdueInvoices->groupBy(function (Invoice $invoice): string {
                $daysOverdue = $invoice->due_date?->diffInDays(now());

                if ($daysOverdue === null) {
                    return 'unknown';
                }

                if ($daysOverdue <= 7) {
                    return '1-7 days';
                }
                if ($daysOverdue <= 30) {
                    return '8-30 days';
                }
                if ($daysOverdue <= 60) {
                    return '31-60 days';
                }
                if ($daysOverdue <= 90) {
                    return '61-90 days';
                }

                return '90+ days';
            });

            Log::channel('finance')->info('Overdue invoices by age', [
                'breakdown' => $groupedByAge->map(fn ($invoices) => [
                    'count' => $invoices->count(),
                    'total' => $invoices->reduce(fn (string $carry, Invoice $inv): string => bcadd($carry, $inv->getOutstandingAmount(), 2), '0'),
                ])->toArray(),
            ]);
        } else {
            Log::channel('finance')->info('No overdue invoices found');
        }
    }

    /**
     * Get overdue invoices query builder for reuse.
     *
     * @return Builder<Invoice>
     */
    public static function getOverdueInvoicesQuery(): Builder
    {
        return Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay());
    }
}
