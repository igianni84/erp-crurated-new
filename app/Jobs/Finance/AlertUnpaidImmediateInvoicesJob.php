<?php

namespace App\Jobs\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Finance\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to alert on INV1 (and other immediate payment) invoices that remain unpaid.
 *
 * INV1 (Voucher Sale), INV2 (Shipping Redemption), and INV4 (Service Events)
 * expect immediate payment. This job identifies invoices of these types that
 * have been issued for longer than the configured threshold without payment.
 *
 * This job should be scheduled to run hourly or at regular intervals to
 * provide timely alerts for follow-up on unpaid immediate invoices.
 */
class AlertUnpaidImmediateInvoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  int  $thresholdHours  Hours after issuance to trigger alert (default: config value or 24)
     */
    public function __construct(
        protected int $thresholdHours = 0
    ) {
        if ($this->thresholdHours === 0) {
            $this->thresholdHours = (int) config('finance.immediate_invoice_alert_hours', 24);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('finance')->info('AlertUnpaidImmediateInvoicesJob started', [
            'threshold_hours' => $this->thresholdHours,
        ]);

        // Get all immediate payment invoice types (no due_date expected)
        $immediateTypes = collect(InvoiceType::cases())
            ->filter(fn (InvoiceType $type): bool => ! $type->requiresDueDate())
            ->values()
            ->all();

        // Find issued invoices of immediate types that are older than threshold
        $cutoffTime = now()->subHours($this->thresholdHours);

        $unpaidInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereIn('invoice_type', $immediateTypes)
            ->where('issued_at', '<=', $cutoffTime)
            ->with('customer')
            ->get();

        $totalCount = $unpaidInvoices->count();

        if ($totalCount === 0) {
            Log::channel('finance')->info('AlertUnpaidImmediateInvoicesJob completed: No unpaid immediate invoices found');

            return;
        }

        // Group by invoice type for reporting
        $groupedByType = $unpaidInvoices->groupBy(fn (Invoice $invoice): string => $invoice->invoice_type->code());

        // Calculate total outstanding amount
        $totalOutstanding = $unpaidInvoices->reduce(
            fn (string $carry, Invoice $invoice): string => bcadd($carry, $invoice->getOutstandingAmount(), 2),
            '0.00'
        );

        // Log the alert
        Log::channel('finance')->warning('Unpaid immediate invoices alert', [
            'total_count' => $totalCount,
            'total_outstanding' => $totalOutstanding,
            'threshold_hours' => $this->thresholdHours,
            'breakdown_by_type' => $groupedByType->map(fn ($invoices) => [
                'count' => $invoices->count(),
                'total_outstanding' => $invoices->reduce(
                    fn (string $carry, Invoice $inv): string => bcadd($carry, $inv->getOutstandingAmount(), 2),
                    '0.00'
                ),
            ])->toArray(),
        ]);

        // Log individual invoice details for INV1 (primary concern per acceptance criteria)
        $inv1Unpaid = $groupedByType->get('INV1', collect());

        if ($inv1Unpaid->count() > 0) {
            Log::channel('finance')->warning('INV1 (Voucher Sale) invoices unpaid > '.$this->thresholdHours.'h', [
                'count' => $inv1Unpaid->count(),
                'invoices' => $inv1Unpaid->map(fn (Invoice $invoice): array => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer !== null ? $invoice->customer->name : 'Unknown',
                    'total_amount' => $invoice->total_amount,
                    'outstanding' => $invoice->getOutstandingAmount(),
                    'currency' => $invoice->currency,
                    'issued_at' => $invoice->issued_at?->toIso8601String(),
                    'hours_since_issuance' => $invoice->issued_at?->diffInHours(now()),
                    'source_type' => $invoice->source_type,
                    'source_id' => $invoice->source_id,
                ])->toArray(),
            ]);
        }

        Log::channel('finance')->info('AlertUnpaidImmediateInvoicesJob completed', [
            'total_alerts' => $totalCount,
            'inv1_alerts' => $inv1Unpaid->count(),
        ]);
    }

    /**
     * Get query builder for unpaid immediate invoices (for reuse in UI).
     *
     * @return Builder<Invoice>
     */
    public static function getUnpaidImmediateInvoicesQuery(?int $thresholdHours = null): Builder
    {
        $thresholdHours = $thresholdHours ?? (int) config('finance.immediate_invoice_alert_hours', 24);
        $cutoffTime = now()->subHours($thresholdHours);

        $immediateTypes = collect(InvoiceType::cases())
            ->filter(fn (InvoiceType $type): bool => ! $type->requiresDueDate())
            ->values()
            ->all();

        return Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereIn('invoice_type', $immediateTypes)
            ->where('issued_at', '<=', $cutoffTime);
    }

    /**
     * Get count of unpaid immediate invoices (for dashboard/UI).
     */
    public static function getUnpaidImmediateInvoicesCount(?int $thresholdHours = null): int
    {
        return self::getUnpaidImmediateInvoicesQuery($thresholdHours)->count();
    }

    /**
     * Get query builder for unpaid INV1 invoices specifically.
     *
     * @return Builder<Invoice>
     */
    public static function getUnpaidInv1Query(?int $thresholdHours = null): Builder
    {
        $thresholdHours = $thresholdHours ?? (int) config('finance.immediate_invoice_alert_hours', 24);
        $cutoffTime = now()->subHours($thresholdHours);

        return Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->where('invoice_type', InvoiceType::VoucherSale)
            ->where('issued_at', '<=', $cutoffTime);
    }

    /**
     * Get count of unpaid INV1 invoices (for dashboard/UI).
     */
    public static function getUnpaidInv1Count(?int $thresholdHours = null): int
    {
        return self::getUnpaidInv1Query($thresholdHours)->count();
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::channel('finance')->error('AlertUnpaidImmediateInvoicesJob failed permanently', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
