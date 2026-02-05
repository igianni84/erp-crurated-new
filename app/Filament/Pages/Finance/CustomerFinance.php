<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\RefundStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Customer Financial Dashboard page for Finance module.
 *
 * This page provides a complete financial view per customer including:
 * - Balance summary (outstanding, overdue, credits)
 * - Open invoices list
 * - Payment history
 * - Credits and refunds
 * - Link to Customer Resource (Module K)
 */
class CustomerFinance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Customer Finance';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Customer Financial Dashboard';

    protected static string $view = 'filament.pages.finance.customer-finance';

    /**
     * Selected customer ID.
     */
    public ?string $customerId = null;

    /**
     * Search query for customer autocomplete.
     */
    public string $customerSearch = '';

    /**
     * Active tab.
     */
    public string $activeTab = 'open-invoices';

    /**
     * Date range for payment history.
     */
    public string $paymentDateFrom = '';

    public string $paymentDateTo = '';

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        // Default payment date range to last 90 days
        $this->paymentDateTo = now()->format('Y-m-d');
        $this->paymentDateFrom = now()->subDays(90)->format('Y-m-d');
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
     * Select a customer.
     */
    public function selectCustomer(string $customerId): void
    {
        $this->customerId = $customerId;
        $this->customerSearch = '';
        $this->activeTab = 'open-invoices';
    }

    /**
     * Clear selected customer.
     */
    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->customerSearch = '';
    }

    /**
     * Get the selected customer.
     */
    public function getSelectedCustomer(): ?Customer
    {
        if ($this->customerId === null) {
            return null;
        }

        return Customer::find($this->customerId);
    }

    /**
     * Set active tab.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // =========================================================================
    // Balance Summary
    // =========================================================================

    /**
     * Get balance summary for the selected customer.
     *
     * @return array{
     *     total_outstanding: string,
     *     overdue_amount: string,
     *     total_credits: string,
     *     available_credit: string,
     *     total_paid_ytd: string,
     *     open_invoices_count: int,
     *     overdue_invoices_count: int
     * }
     */
    public function getBalanceSummary(): array
    {
        if ($this->customerId === null) {
            return [
                'total_outstanding' => '0.00',
                'overdue_amount' => '0.00',
                'total_credits' => '0.00',
                'available_credit' => '0.00',
                'total_paid_ytd' => '0.00',
                'open_invoices_count' => 0,
                'overdue_invoices_count' => 0,
            ];
        }

        // Calculate total outstanding
        $openInvoices = Invoice::where('customer_id', $this->customerId)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->get();

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

        // Calculate total credits (issued + applied credit notes)
        $totalCredits = CreditNote::where('customer_id', $this->customerId)
            ->whereIn('status', [CreditNoteStatus::Issued, CreditNoteStatus::Applied])
            ->sum('amount');

        // Calculate total paid this year
        $yearStart = Carbon::now()->startOfYear();
        $totalPaidYtd = Payment::where('customer_id', $this->customerId)
            ->where('received_at', '>=', $yearStart)
            ->sum('amount');

        return [
            'total_outstanding' => $totalOutstanding,
            'overdue_amount' => $overdueAmount,
            'total_credits' => (string) $totalCredits,
            'available_credit' => '0.00', // Placeholder - would need CustomerCredit model
            'total_paid_ytd' => (string) $totalPaidYtd,
            'open_invoices_count' => $openInvoices->count(),
            'overdue_invoices_count' => $overdueCount,
        ];
    }

    // =========================================================================
    // Open Invoices Tab
    // =========================================================================

    /**
     * Get open invoices for the selected customer.
     *
     * @return Collection<int, Invoice>
     */
    public function getOpenInvoices(): Collection
    {
        if ($this->customerId === null) {
            return collect();
        }

        return Invoice::where('customer_id', $this->customerId)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get outstanding amount for an invoice.
     */
    public function getInvoiceOutstanding(Invoice $invoice): string
    {
        return bcsub($invoice->total_amount, $invoice->amount_paid, 2);
    }

    // =========================================================================
    // Payment History Tab
    // =========================================================================

    /**
     * Get payment history for the selected customer.
     *
     * @return Collection<int, Payment>
     */
    public function getPaymentHistory(): Collection
    {
        if ($this->customerId === null) {
            return collect();
        }

        $query = Payment::where('customer_id', $this->customerId)
            ->orderBy('received_at', 'desc');

        if (! empty($this->paymentDateFrom)) {
            $query->where('received_at', '>=', Carbon::parse($this->paymentDateFrom)->startOfDay());
        }

        if (! empty($this->paymentDateTo)) {
            $query->where('received_at', '<=', Carbon::parse($this->paymentDateTo)->endOfDay());
        }

        return $query->with('invoicePayments.invoice')->get();
    }

    /**
     * Get invoices a payment is applied to.
     */
    public function getPaymentAppliedInvoices(Payment $payment): string
    {
        $invoices = $payment->invoicePayments->map(function ($ip) {
            $invoice = $ip->invoice;

            return $invoice !== null ? $invoice->invoice_number : 'N/A';
        })->filter()->toArray();

        return ! empty($invoices) ? implode(', ', $invoices) : '-';
    }

    // =========================================================================
    // Credits & Refunds Tab
    // =========================================================================

    /**
     * Get credit notes for the selected customer.
     *
     * @return Collection<int, CreditNote>
     */
    public function getCreditNotes(): Collection
    {
        if ($this->customerId === null) {
            return collect();
        }

        return CreditNote::where('customer_id', $this->customerId)
            ->orderBy('created_at', 'desc')
            ->with('invoice')
            ->get();
    }

    /**
     * Get refunds for the selected customer.
     *
     * @return Collection<int, Refund>
     */
    public function getRefunds(): Collection
    {
        if ($this->customerId === null) {
            return collect();
        }

        return Refund::whereHas('invoice', function ($query): void {
            $query->where('customer_id', $this->customerId);
        })
            ->orderBy('created_at', 'desc')
            ->with(['invoice', 'payment'])
            ->get();
    }

    /**
     * Get total credits summary.
     *
     * @return array{credit_notes_total: string, refunds_total: string, combined_total: string}
     */
    public function getCreditsSummary(): array
    {
        if ($this->customerId === null) {
            return [
                'credit_notes_total' => '0.00',
                'refunds_total' => '0.00',
                'combined_total' => '0.00',
            ];
        }

        $creditNotesTotal = CreditNote::where('customer_id', $this->customerId)
            ->whereIn('status', [CreditNoteStatus::Issued, CreditNoteStatus::Applied])
            ->sum('amount');

        $refundsTotal = Refund::whereHas('invoice', function ($query): void {
            $query->where('customer_id', $this->customerId);
        })
            ->where('status', RefundStatus::Processed)
            ->sum('amount');

        return [
            'credit_notes_total' => (string) $creditNotesTotal,
            'refunds_total' => (string) $refundsTotal,
            'combined_total' => bcadd((string) $creditNotesTotal, (string) $refundsTotal, 2),
        ];
    }

    // =========================================================================
    // Exposure & Limits Tab
    // =========================================================================

    /**
     * Get exposure metrics for the selected customer.
     *
     * @return array{
     *     total_outstanding: string,
     *     overdue_amount: string,
     *     credit_limit: string|null,
     *     available_credit: string|null,
     *     exposure_percentage: float|null,
     *     is_over_limit: bool
     * }
     */
    public function getExposureMetrics(): array
    {
        if ($this->customerId === null) {
            return [
                'total_outstanding' => '0.00',
                'overdue_amount' => '0.00',
                'credit_limit' => null,
                'available_credit' => null,
                'exposure_percentage' => null,
                'is_over_limit' => false,
            ];
        }

        $balanceSummary = $this->getBalanceSummary();
        $totalOutstanding = $balanceSummary['total_outstanding'];
        $overdueAmount = $balanceSummary['overdue_amount'];

        // Credit limit would be stored on the Customer model in Module K
        // For now, we'll use null to indicate no limit is set
        $creditLimit = null;
        $availableCredit = null;
        $exposurePercentage = null;
        $isOverLimit = false;

        // If we had a credit limit defined:
        // $customer = $this->getSelectedCustomer();
        // $creditLimit = $customer?->credit_limit;
        // if ($creditLimit !== null && bccomp($creditLimit, '0', 2) > 0) {
        //     $availableCredit = bcsub($creditLimit, $totalOutstanding, 2);
        //     $exposurePercentage = (float) bcdiv(bcmul($totalOutstanding, '100', 2), $creditLimit, 2);
        //     $isOverLimit = bccomp($totalOutstanding, $creditLimit, 2) > 0;
        // }

        return [
            'total_outstanding' => $totalOutstanding,
            'overdue_amount' => $overdueAmount,
            'credit_limit' => $creditLimit,
            'available_credit' => $availableCredit,
            'exposure_percentage' => $exposurePercentage,
            'is_over_limit' => $isOverLimit,
        ];
    }

    /**
     * Get exposure trend data for the chart.
     *
     * Returns monthly outstanding amounts for the last 12 months.
     *
     * @return array<int, array{month: string, outstanding: string, payments: string}>
     */
    public function getExposureTrendData(): array
    {
        if ($this->customerId === null) {
            return [];
        }

        $trendData = [];
        $now = Carbon::now();

        // Get data for the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $now->copy()->subMonths($i)->endOfMonth();
            $monthLabel = $monthStart->format('M Y');

            // Calculate outstanding at end of month
            // This is a simplified calculation - sum of invoices issued up to month end minus payments received up to month end
            $invoicedAmount = Invoice::where('customer_id', $this->customerId)
                ->where('issued_at', '<=', $monthEnd)
                ->whereIn('status', [
                    InvoiceStatus::Issued,
                    InvoiceStatus::PartiallyPaid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Credited,
                ])
                ->sum('total_amount');

            $paidAmount = Payment::where('customer_id', $this->customerId)
                ->where('received_at', '<=', $monthEnd)
                ->sum('amount');

            $outstanding = bcsub((string) $invoicedAmount, (string) $paidAmount, 2);
            if (bccomp($outstanding, '0', 2) < 0) {
                $outstanding = '0.00';
            }

            // Get payments for this specific month
            $monthlyPayments = Payment::where('customer_id', $this->customerId)
                ->whereBetween('received_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $trendData[] = [
                'month' => $monthLabel,
                'outstanding' => $outstanding,
                'payments' => (string) $monthlyPayments,
            ];
        }

        return $trendData;
    }

    // =========================================================================
    // Eligibility Signals Tab
    // =========================================================================

    /**
     * Get eligibility signals (financial blocks) for the selected customer.
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
    public function getEligibilitySignals(): array
    {
        if ($this->customerId === null) {
            return [];
        }

        $signals = [];

        // Check for INV0 (membership_service) overdue - causes payment_blocked
        $overdueInv0 = Invoice::where('customer_id', $this->customerId)
            ->where('invoice_type', \App\Enums\Finance\InvoiceType::MembershipService)
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
        $overdueInv3 = Invoice::where('customer_id', $this->customerId)
            ->where('invoice_type', \App\Enums\Finance\InvoiceType::StorageFee)
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
     * Check if customer has any active financial blocks.
     */
    public function hasActiveBlocks(): bool
    {
        return count($this->getEligibilitySignals()) > 0;
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Get Customer Resource URL.
     */
    public function getCustomerResourceUrl(): ?string
    {
        if ($this->customerId === null) {
            return null;
        }

        return route('filament.admin.resources.customer.customers.view', ['record' => $this->customerId]);
    }

    /**
     * Get Invoice Resource URL.
     */
    public function getInvoiceUrl(string $invoiceId): string
    {
        return route('filament.admin.resources.finance.invoices.view', ['record' => $invoiceId]);
    }

    /**
     * Get Payment Resource URL.
     */
    public function getPaymentUrl(string $paymentId): string
    {
        return route('filament.admin.resources.finance.payments.view', ['record' => $paymentId]);
    }

    /**
     * Get Credit Note Resource URL.
     */
    public function getCreditNoteUrl(string $creditNoteId): string
    {
        return route('filament.admin.resources.finance.credit-notes.view', ['record' => $creditNoteId]);
    }

    /**
     * Get status color for invoice.
     */
    public function getInvoiceStatusColor(Invoice $invoice): string
    {
        if ($invoice->isOverdue()) {
            return 'danger';
        }

        return $invoice->status->color();
    }
}
