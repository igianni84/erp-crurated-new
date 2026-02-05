<?php

namespace App\Services\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for aggregating customer financial data.
 *
 * Provides methods to retrieve and calculate customer financial
 * information including open invoices, outstanding amounts,
 * payment history, and eligibility signals.
 */
class CustomerFinanceService
{
    /**
     * Get open (unpaid) invoices for a customer.
     *
     * Returns invoices with status Issued or PartiallyPaid,
     * sorted by due date (oldest first).
     *
     * @return Collection<int, Invoice>
     */
    public function getOpenInvoices(Customer $customer): Collection
    {
        return Invoice::where('customer_id', $customer->id)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get total outstanding amount for a customer.
     *
     * Calculates the sum of (total_amount - amount_paid) for all
     * open invoices.
     */
    public function getTotalOutstanding(Customer $customer): string
    {
        $openInvoices = $this->getOpenInvoices($customer);

        $totalOutstanding = '0.00';

        foreach ($openInvoices as $invoice) {
            $outstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
            $totalOutstanding = bcadd($totalOutstanding, $outstanding, 2);
        }

        return $totalOutstanding;
    }

    /**
     * Get overdue amount for a customer.
     *
     * Calculates the sum of outstanding amounts for invoices
     * where the due date has passed.
     */
    public function getOverdueAmount(Customer $customer): string
    {
        $openInvoices = $this->getOpenInvoices($customer);

        $overdueAmount = '0.00';

        foreach ($openInvoices as $invoice) {
            if ($invoice->isOverdue()) {
                $outstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
                $overdueAmount = bcadd($overdueAmount, $outstanding, 2);
            }
        }

        return $overdueAmount;
    }

    /**
     * Get payment history for a customer within a date range.
     *
     * @param  array{from?: string|Carbon|null, to?: string|Carbon|null}  $dateRange  Optional date range filter
     * @return Collection<int, Payment>
     */
    public function getPaymentHistory(Customer $customer, array $dateRange = []): Collection
    {
        $query = Payment::where('customer_id', $customer->id)
            ->orderBy('received_at', 'desc');

        if (! empty($dateRange['from'])) {
            $from = $dateRange['from'] instanceof Carbon
                ? $dateRange['from']
                : Carbon::parse($dateRange['from']);
            $query->where('received_at', '>=', $from->startOfDay());
        }

        if (! empty($dateRange['to'])) {
            $to = $dateRange['to'] instanceof Carbon
                ? $dateRange['to']
                : Carbon::parse($dateRange['to']);
            $query->where('received_at', '<=', $to->endOfDay());
        }

        return $query->with('invoicePayments.invoice')->get();
    }

    /**
     * Get eligibility signals (financial blocks) for a customer.
     *
     * Checks for:
     * - payment_blocked: INV0 (membership_service) overdue
     * - custody_blocked: INV3 (storage_fee) overdue
     *
     * @return array<int, array{
     *     type: string,
     *     label: string,
     *     reason: string,
     *     invoice_number: string|null,
     *     invoice_id: string|null,
     *     how_to_resolve: string,
     *     severity: string
     * }>
     */
    public function getEligibilitySignals(Customer $customer): array
    {
        $signals = [];

        // Check for INV0 (membership_service) overdue - causes payment_blocked
        $overdueInv0 = Invoice::where('customer_id', $customer->id)
            ->where('invoice_type', InvoiceType::MembershipService)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->orderBy('due_date', 'asc')
            ->first();

        if ($overdueInv0 !== null) {
            $signals[] = [
                'type' => 'payment_blocked',
                'label' => 'Payment Blocked',
                'reason' => 'Membership invoice (INV0) is overdue. Customer cannot make new purchases until resolved.',
                'invoice_number' => $overdueInv0->invoice_number,
                'invoice_id' => $overdueInv0->id,
                'how_to_resolve' => 'Pay the outstanding membership invoice to unblock payment capability.',
                'severity' => 'danger',
            ];
        }

        // Check for INV3 (storage_fee) overdue - causes custody_blocked
        $overdueInv3 = Invoice::where('customer_id', $customer->id)
            ->where('invoice_type', InvoiceType::StorageFee)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->orderBy('due_date', 'asc')
            ->first();

        if ($overdueInv3 !== null) {
            $signals[] = [
                'type' => 'custody_blocked',
                'label' => 'Custody Blocked',
                'reason' => 'Storage fee invoice (INV3) is overdue. Customer cannot retrieve items from custody until resolved.',
                'invoice_number' => $overdueInv3->invoice_number,
                'invoice_id' => $overdueInv3->id,
                'how_to_resolve' => 'Pay the outstanding storage fee invoice to unblock custody access.',
                'severity' => 'warning',
            ];
        }

        return $signals;
    }

    /**
     * Check if customer has payment blocked status.
     *
     * Payment is blocked when a membership invoice (INV0) is overdue.
     */
    public function isPaymentBlocked(Customer $customer): bool
    {
        $signals = $this->getEligibilitySignals($customer);

        return collect($signals)->contains('type', 'payment_blocked');
    }

    /**
     * Check if customer has custody blocked status.
     *
     * Custody is blocked when a storage fee invoice (INV3) is overdue.
     */
    public function isCustodyBlocked(Customer $customer): bool
    {
        $signals = $this->getEligibilitySignals($customer);

        return collect($signals)->contains('type', 'custody_blocked');
    }

    /**
     * Get a summary of customer financial data.
     *
     * @return array{
     *     total_outstanding: string,
     *     overdue_amount: string,
     *     open_invoices_count: int,
     *     overdue_invoices_count: int,
     *     is_payment_blocked: bool,
     *     is_custody_blocked: bool
     * }
     */
    public function getFinancialSummary(Customer $customer): array
    {
        $openInvoices = $this->getOpenInvoices($customer);

        $totalOutstanding = '0.00';
        $overdueAmount = '0.00';
        $overdueCount = 0;

        foreach ($openInvoices as $invoice) {
            $outstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
            $totalOutstanding = bcadd($totalOutstanding, $outstanding, 2);

            if ($invoice->isOverdue()) {
                $overdueAmount = bcadd($overdueAmount, $outstanding, 2);
                $overdueCount++;
            }
        }

        $signals = $this->getEligibilitySignals($customer);
        $isPaymentBlocked = collect($signals)->contains('type', 'payment_blocked');
        $isCustodyBlocked = collect($signals)->contains('type', 'custody_blocked');

        return [
            'total_outstanding' => $totalOutstanding,
            'overdue_amount' => $overdueAmount,
            'open_invoices_count' => $openInvoices->count(),
            'overdue_invoices_count' => $overdueCount,
            'is_payment_blocked' => $isPaymentBlocked,
            'is_custody_blocked' => $isCustodyBlocked,
        ];
    }
}
