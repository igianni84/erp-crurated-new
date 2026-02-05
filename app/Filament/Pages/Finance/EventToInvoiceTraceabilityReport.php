<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\InvoiceType;
use App\Models\Finance\Invoice;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Event-to-Invoice Traceability Report page for Finance module.
 *
 * This page provides a traceability report showing:
 * - ERP Events → Invoices → Payments chain
 * - Filter by event type (sale, shipment, storage)
 * - Filter by date range
 * - Shows unmatched events (events without invoices)
 * - Export to CSV functionality
 */
class EventToInvoiceTraceabilityReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Event Traceability';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 65;

    protected static ?string $title = 'Event-to-Invoice Traceability';

    protected static string $view = 'filament.pages.finance.event-to-invoice-traceability-report';

    /**
     * Filter by event type (source_type).
     */
    public string $filterEventType = 'all';

    /**
     * Date range start.
     */
    public string $dateFrom = '';

    /**
     * Date range end.
     */
    public string $dateTo = '';

    /**
     * Show only unmatched events.
     */
    public bool $showUnmatchedOnly = false;

    /**
     * Cache for traceability data.
     *
     * @var Collection<int, mixed>|null
     */
    protected ?Collection $traceabilityDataCache = null;

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        // Default to last 30 days
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    /**
     * Reset cache when filters change.
     */
    public function updatedFilterEventType(): void
    {
        $this->traceabilityDataCache = null;
    }

    /**
     * Reset cache when date from changes.
     */
    public function updatedDateFrom(): void
    {
        $this->traceabilityDataCache = null;
    }

    /**
     * Reset cache when date to changes.
     */
    public function updatedDateTo(): void
    {
        $this->traceabilityDataCache = null;
    }

    /**
     * Reset cache when unmatched filter changes.
     */
    public function updatedShowUnmatchedOnly(): void
    {
        $this->traceabilityDataCache = null;
    }

    /**
     * Get available event types for filter.
     *
     * @return array<string, string>
     */
    public function getEventTypes(): array
    {
        return [
            'all' => 'All Event Types',
            'voucher_sale' => 'Voucher Sales (INV1)',
            'shipping_order' => 'Shipments (INV2)',
            'storage_billing_period' => 'Storage Billing (INV3)',
            'subscription' => 'Subscriptions (INV0)',
            'event_booking' => 'Event Bookings (INV4)',
        ];
    }

    /**
     * Get the invoice type for a source type.
     */
    protected function getInvoiceTypeForSource(string $sourceType): ?InvoiceType
    {
        return match ($sourceType) {
            'subscription' => InvoiceType::MembershipService,
            'voucher_sale' => InvoiceType::VoucherSale,
            'shipping_order' => InvoiceType::ShippingRedemption,
            'storage_billing_period' => InvoiceType::StorageFee,
            'event_booking' => InvoiceType::ServiceEvents,
            default => null,
        };
    }

    /**
     * Get event type label.
     */
    public function getEventTypeLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'subscription' => 'Subscription',
            'voucher_sale' => 'Voucher Sale',
            'shipping_order' => 'Shipment',
            'storage_billing_period' => 'Storage Billing',
            'event_booking' => 'Event Booking',
            default => ucfirst(str_replace('_', ' ', $sourceType)),
        };
    }

    /**
     * Get event type color.
     */
    public function getEventTypeColor(string $sourceType): string
    {
        $invoiceType = $this->getInvoiceTypeForSource($sourceType);

        return $invoiceType !== null ? $invoiceType->color() : 'gray';
    }

    /**
     * Get event type icon.
     */
    public function getEventTypeIcon(string $sourceType): string
    {
        $invoiceType = $this->getInvoiceTypeForSource($sourceType);

        return $invoiceType !== null ? $invoiceType->icon() : 'heroicon-o-document';
    }

    /**
     * Get date range as Carbon instances.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    protected function getDateRange(): array
    {
        return [
            'from' => Carbon::parse($this->dateFrom)->startOfDay(),
            'to' => Carbon::parse($this->dateTo)->endOfDay(),
        ];
    }

    /**
     * Get traceability data - events with their invoices and payments.
     *
     * @return Collection<int, mixed>
     */
    public function getTraceabilityData(): Collection
    {
        if ($this->traceabilityDataCache !== null) {
            return $this->traceabilityDataCache;
        }

        $dateRange = $this->getDateRange();

        // Build query for invoices with source references
        $query = Invoice::query()
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->with(['customer', 'invoicePayments.payment']);

        // Filter by event type
        if ($this->filterEventType !== 'all') {
            $query->where('source_type', $this->filterEventType);
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        // Transform to traceability records
        $records = $invoices->map(function (Invoice $invoice): array {
            $payments = $invoice->invoicePayments;
            $totalPaid = '0.00';
            $paymentReferences = [];

            foreach ($payments as $invoicePayment) {
                $totalPaid = bcadd($totalPaid, $invoicePayment->amount_applied, 2);
                $payment = $invoicePayment->payment;
                if ($payment !== null) {
                    $paymentReferences[] = [
                        'reference' => $payment->payment_reference,
                        'amount' => $invoicePayment->amount_applied,
                        'source' => $payment->source->value,
                        'date' => $payment->received_at->format('Y-m-d'),
                    ];
                }
            }

            $hasInvoice = true;
            $hasPayment = $payments->isNotEmpty();
            $isFullyPaid = $invoice->isFullyPaid();

            $customer = $invoice->customer;

            return [
                'source_type' => $invoice->source_type,
                'source_id' => $invoice->source_id,
                'event_date' => $invoice->created_at->format('Y-m-d'),
                'has_invoice' => $hasInvoice,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_type' => $invoice->invoice_type,
                'invoice_status' => $invoice->status,
                'customer_name' => $customer !== null ? $customer->name : 'Unknown',
                'customer_id' => $invoice->customer_id,
                'invoice_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'has_payment' => $hasPayment,
                'is_fully_paid' => $isFullyPaid,
                'total_paid' => $totalPaid,
                'outstanding' => $invoice->getOutstandingAmount(),
                'payment_references' => $paymentReferences,
                'traceability_status' => $this->determineTraceabilityStatus($hasInvoice, $hasPayment, $isFullyPaid),
            ];
        });

        // Filter to show only unmatched if requested
        if ($this->showUnmatchedOnly) {
            $records = $records->filter(function (array $record): bool {
                return $record['traceability_status'] !== 'complete';
            });
        }

        $this->traceabilityDataCache = $records->values();

        return $this->traceabilityDataCache;
    }

    /**
     * Determine the traceability status for display.
     */
    protected function determineTraceabilityStatus(bool $hasInvoice, bool $hasPayment, bool $isFullyPaid): string
    {
        if (! $hasInvoice) {
            return 'no_invoice';
        }

        if (! $hasPayment) {
            return 'pending_payment';
        }

        if ($isFullyPaid) {
            return 'complete';
        }

        return 'partial_payment';
    }

    /**
     * Get status label for display.
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'complete' => 'Complete',
            'partial_payment' => 'Partial Payment',
            'pending_payment' => 'Pending Payment',
            'no_invoice' => 'No Invoice',
            default => 'Unknown',
        };
    }

    /**
     * Get status color for display.
     */
    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'complete' => 'success',
            'partial_payment' => 'warning',
            'pending_payment' => 'info',
            'no_invoice' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get status icon for display.
     */
    public function getStatusIcon(string $status): string
    {
        return match ($status) {
            'complete' => 'heroicon-o-check-circle',
            'partial_payment' => 'heroicon-o-clock',
            'pending_payment' => 'heroicon-o-exclamation-circle',
            'no_invoice' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Get summary statistics.
     *
     * @return array{
     *     total_events: int,
     *     with_invoices: int,
     *     with_payments: int,
     *     complete: int,
     *     partial_payment: int,
     *     pending_payment: int,
     *     total_invoiced: string,
     *     total_paid: string,
     *     total_outstanding: string,
     *     completion_rate: float
     * }
     */
    public function getSummary(): array
    {
        $data = $this->getTraceabilityData();

        $totalInvoiced = '0.00';
        $totalPaid = '0.00';
        $totalOutstanding = '0.00';
        $complete = 0;
        $partialPayment = 0;
        $pendingPayment = 0;
        $noInvoice = 0;

        foreach ($data as $record) {
            $totalInvoiced = bcadd($totalInvoiced, $record['invoice_amount'], 2);
            $totalPaid = bcadd($totalPaid, $record['total_paid'], 2);
            $totalOutstanding = bcadd($totalOutstanding, $record['outstanding'], 2);

            match ($record['traceability_status']) {
                'complete' => $complete++,
                'partial_payment' => $partialPayment++,
                'pending_payment' => $pendingPayment++,
                'no_invoice' => $noInvoice++,
                default => null,
            };
        }

        $totalEvents = $data->count();
        $withInvoices = $totalEvents - $noInvoice;
        $withPayments = $complete + $partialPayment;

        return [
            'total_events' => $totalEvents,
            'with_invoices' => $withInvoices,
            'with_payments' => $withPayments,
            'complete' => $complete,
            'partial_payment' => $partialPayment,
            'pending_payment' => $pendingPayment,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
            'completion_rate' => $totalEvents > 0 ? round(($complete / $totalEvents) * 100, 1) : 0,
        ];
    }

    /**
     * Get breakdown by event type.
     *
     * @return Collection<string, array{event_type: string, label: string, count: int, invoiced: string, paid: string, outstanding: string, complete: int, incomplete: int}>
     */
    public function getBreakdownByEventType(): Collection
    {
        $data = $this->getTraceabilityData();

        /** @var array<string, array{event_type: string, label: string, count: int, invoiced: string, paid: string, outstanding: string, complete: int, incomplete: int}> $breakdown */
        $breakdown = [];

        foreach ($data as $record) {
            /** @var string $sourceType */
            $sourceType = $record['source_type'];

            if (! isset($breakdown[$sourceType])) {
                $breakdown[$sourceType] = [
                    'event_type' => $sourceType,
                    'label' => $this->getEventTypeLabel($sourceType),
                    'count' => 0,
                    'invoiced' => '0.00',
                    'paid' => '0.00',
                    'outstanding' => '0.00',
                    'complete' => 0,
                    'incomplete' => 0,
                ];
            }

            $breakdown[$sourceType]['count']++;
            $breakdown[$sourceType]['invoiced'] = bcadd($breakdown[$sourceType]['invoiced'], $record['invoice_amount'], 2);
            $breakdown[$sourceType]['paid'] = bcadd($breakdown[$sourceType]['paid'], $record['total_paid'], 2);
            $breakdown[$sourceType]['outstanding'] = bcadd($breakdown[$sourceType]['outstanding'], $record['outstanding'], 2);

            if ($record['traceability_status'] === 'complete') {
                $breakdown[$sourceType]['complete']++;
            } else {
                $breakdown[$sourceType]['incomplete']++;
            }
        }

        // Sort by count descending
        uasort($breakdown, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return collect($breakdown);
    }

    /**
     * Format currency amount.
     */
    public function formatAmount(string $amount, string $currency = 'EUR'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Get URL for invoice detail.
     */
    public function getInvoiceUrl(string $invoiceId): string
    {
        return route('filament.admin.resources.invoices.view', ['record' => $invoiceId]);
    }

    /**
     * Get URL for customer finance view.
     */
    public function getCustomerFinanceUrl(string $customerId): string
    {
        return route('filament.admin.pages.finance.customer-finance').'?customerId='.$customerId;
    }

    /**
     * Export to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->getTraceabilityData();
        $summary = $this->getSummary();
        $dateFrom = $this->dateFrom;
        $dateTo = $this->dateTo;
        $eventTypeFilter = $this->filterEventType;

        return response()->streamDownload(function () use ($data, $summary, $dateFrom, $dateTo, $eventTypeFilter): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Report header
            fputcsv($handle, ['Event-to-Invoice Traceability Report']);
            fputcsv($handle, ['Generated', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, ['Date Range', $dateFrom.' to '.$dateTo]);
            fputcsv($handle, ['Event Type Filter', $eventTypeFilter === 'all' ? 'All' : $this->getEventTypeLabel($eventTypeFilter)]);
            fputcsv($handle, []);

            // Summary section
            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Total Events', $summary['total_events']]);
            fputcsv($handle, ['Complete', $summary['complete']]);
            fputcsv($handle, ['Partial Payment', $summary['partial_payment']]);
            fputcsv($handle, ['Pending Payment', $summary['pending_payment']]);
            fputcsv($handle, ['Completion Rate', $summary['completion_rate'].'%']);
            fputcsv($handle, ['Total Invoiced', $summary['total_invoiced']]);
            fputcsv($handle, ['Total Paid', $summary['total_paid']]);
            fputcsv($handle, ['Total Outstanding', $summary['total_outstanding']]);
            fputcsv($handle, []);

            // Data headers
            fputcsv($handle, [
                'Event Type',
                'Source ID',
                'Event Date',
                'Invoice Number',
                'Invoice Type',
                'Invoice Status',
                'Customer',
                'Currency',
                'Invoice Amount',
                'Total Paid',
                'Outstanding',
                'Traceability Status',
                'Payment References',
            ]);

            // Data rows
            foreach ($data as $record) {
                $paymentRefs = collect($record['payment_references'])
                    ->map(fn (array $p): string => $p['reference'].' ('.$p['amount'].')')
                    ->implode('; ');

                fputcsv($handle, [
                    $this->getEventTypeLabel($record['source_type']),
                    $record['source_id'],
                    $record['event_date'],
                    $record['invoice_number'] ?? 'N/A',
                    $record['invoice_type']?->code() ?? 'N/A',
                    $record['invoice_status']?->label() ?? 'N/A',
                    $record['customer_name'],
                    $record['currency'],
                    $record['invoice_amount'],
                    $record['total_paid'],
                    $record['outstanding'],
                    $this->getStatusLabel($record['traceability_status']),
                    $paymentRefs ?: 'None',
                ]);
            }

            fclose($handle);
        }, 'event-traceability-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Toggle unmatched filter.
     */
    public function toggleUnmatchedFilter(): void
    {
        $this->showUnmatchedOnly = ! $this->showUnmatchedOnly;
        $this->traceabilityDataCache = null;
    }
}
