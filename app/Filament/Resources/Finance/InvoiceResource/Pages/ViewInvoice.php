<?php

namespace App\Filament\Resources\Finance\InvoiceResource\Pages;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\RefundMethod;
use App\Enums\Finance\RefundStatus;
use App\Enums\Finance\RefundType;
use App\Filament\Resources\Finance\InvoiceResource;
use App\Models\AuditLog;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Refund;
use App\Services\Finance\InvoiceMailService;
use App\Services\Finance\InvoicePdfService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Invoice $record */
        $record = $this->record;

        return 'Invoice: '.($record->invoice_number ?? 'Draft #'.$record->id);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getHeaderSection(),
                Tabs::make('Invoice Details')
                    ->tabs([
                        $this->getLinesTab(),
                        $this->getPaymentsTab(),
                        $this->getLinkedErpEventsTab(),
                        $this->getAccountingTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Header section with invoice_number, type, status, customer, currency, totals.
     */
    protected function getHeaderSection(): Section
    {
        return Section::make('Invoice Overview')
            ->schema([
                Grid::make(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('invoice_number')
                                ->label('Invoice Number')
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large)
                                ->copyable()
                                ->copyMessage('Invoice number copied')
                                ->placeholder('Draft - Not yet issued'),
                            TextEntry::make('invoice_type')
                                ->label('Type')
                                ->badge()
                                ->formatStateUsing(fn (InvoiceType $state): string => $state->code().' - '.$state->label())
                                ->color(fn (InvoiceType $state): string => $state->color())
                                ->icon(fn (InvoiceType $state): string => $state->icon())
                                ->helperText('Invoice type is locked and cannot be changed'),
                            TextEntry::make('customer.name')
                                ->label('Customer')
                                ->url(fn (Invoice $record): ?string => $record->customer
                                    ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                                    : null)
                                ->color('primary'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())
                                ->color(fn (InvoiceStatus $state): string => $state->color())
                                ->icon(fn (InvoiceStatus $state): string => $state->icon()),
                            TextEntry::make('currency')
                                ->label('Currency')
                                ->badge()
                                ->formatStateUsing(fn (Invoice $record): string => $record->currency.' ('.$record->getCurrencySymbol().')')
                                ->color('gray'),
                            TextEntry::make('issued_at')
                                ->label('Issue Date')
                                ->dateTime()
                                ->placeholder('Not issued'),
                            TextEntry::make('due_date')
                                ->label('Due Date')
                                ->date()
                                ->placeholder('N/A')
                                ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null)
                                ->helperText(fn (Invoice $record): ?string => $record->isOverdue() ? 'OVERDUE' : null),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('subtotal')
                                ->label('Subtotal')
                                ->money(fn (Invoice $record): string => $record->currency),
                            TextEntry::make('tax_amount')
                                ->label('Tax Amount')
                                ->money(fn (Invoice $record): string => $record->currency),
                            TextEntry::make('total_amount')
                                ->label('Total Amount')
                                ->money(fn (Invoice $record): string => $record->currency)
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('amount_paid')
                                ->label('Amount Paid')
                                ->money(fn (Invoice $record): string => $record->currency)
                                ->color('success'),
                            TextEntry::make('outstanding')
                                ->label('Outstanding')
                                ->getStateUsing(fn (Invoice $record): string => $record->getOutstandingAmount())
                                ->money(fn (Invoice $record): string => $record->currency)
                                ->weight(FontWeight::Bold)
                                ->color(fn (Invoice $record): string => $record->isOverdue() ? 'danger' : 'warning'),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    /**
     * Tab 1: Lines - Read-only invoice lines with description, qty, unit_price, tax, total.
     */
    protected function getLinesTab(): Tab
    {
        /** @var Invoice $record */
        $record = $this->record;
        $linesCount = $record->invoiceLines()->count();

        return Tab::make('Lines')
            ->icon('heroicon-o-list-bullet')
            ->badge($linesCount > 0 ? (string) $linesCount : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Invoice Lines')
                    ->description('Read-only view of invoice line items')
                    ->schema([
                        RepeatableEntry::make('invoiceLines')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('description')
                                            ->label('Description')
                                            ->weight(FontWeight::Bold)
                                            ->columnSpan(2),
                                        TextEntry::make('quantity')
                                            ->label('Qty')
                                            ->numeric(decimalPlaces: 2)
                                            ->alignEnd(),
                                        TextEntry::make('unit_price')
                                            ->label('Unit Price')
                                            ->money(fn (InvoiceLine $line): string => $line->invoice !== null ? $line->invoice->currency : 'EUR')
                                            ->alignEnd(),
                                        TextEntry::make('tax_amount')
                                            ->label('Tax')
                                            ->money(fn (InvoiceLine $line): string => $line->invoice !== null ? $line->invoice->currency : 'EUR')
                                            ->alignEnd()
                                            ->helperText(fn (InvoiceLine $line): string => $line->getFormattedTaxRate()),
                                        TextEntry::make('line_total')
                                            ->label('Total')
                                            ->money(fn (InvoiceLine $line): string => $line->invoice !== null ? $line->invoice->currency : 'EUR')
                                            ->weight(FontWeight::Bold)
                                            ->alignEnd(),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No invoice lines'),
                    ]),
            ]);
    }

    /**
     * Tab 2: Payments - Applied payments with amount, date, source, reference.
     */
    protected function getPaymentsTab(): Tab
    {
        /** @var Invoice $record */
        $record = $this->record;
        $paymentsCount = $record->invoicePayments()->count();

        return Tab::make('Payments')
            ->icon('heroicon-o-banknotes')
            ->badge($paymentsCount > 0 ? (string) $paymentsCount : null)
            ->badgeColor('success')
            ->schema([
                Section::make('Payment Summary')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_amount')
                                    ->label('Invoice Total')
                                    ->money(fn (Invoice $record): string => $record->currency)
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('amount_paid')
                                    ->label('Total Paid')
                                    ->money(fn (Invoice $record): string => $record->currency)
                                    ->color('success')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('outstanding_amount')
                                    ->label('Outstanding')
                                    ->getStateUsing(fn (Invoice $record): string => $record->getOutstandingAmount())
                                    ->money(fn (Invoice $record): string => $record->currency)
                                    ->color(fn (Invoice $record): string => bccomp($record->getOutstandingAmount(), '0', 2) > 0 ? 'warning' : 'success')
                                    ->weight(FontWeight::Bold),
                            ]),
                    ]),
                Section::make('Applied Payments')
                    ->description('Payments applied to this invoice')
                    ->schema([
                        RepeatableEntry::make('invoicePayments')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('payment.payment_reference')
                                            ->label('Reference')
                                            ->copyable()
                                            ->copyMessage('Payment reference copied')
                                            ->weight(FontWeight::Bold)
                                            ->url(fn (InvoicePayment $invoicePayment): ?string => $invoicePayment->payment
                                                ? route('filament.admin.resources.finance.payments.view', ['record' => $invoicePayment->payment])
                                                : null)
                                            ->color('primary'),
                                        TextEntry::make('amount_applied')
                                            ->label('Amount Applied')
                                            ->money(fn (InvoicePayment $invoicePayment): string => $invoicePayment->invoice !== null ? $invoicePayment->invoice->currency : 'EUR')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('payment.source')
                                            ->label('Source')
                                            ->badge()
                                            ->formatStateUsing(fn (?PaymentSource $state): string => $state?->label() ?? 'N/A')
                                            ->color(fn (?PaymentSource $state): string => $state?->color() ?? 'gray')
                                            ->icon(fn (?PaymentSource $state): string => $state?->icon() ?? 'heroicon-o-question-mark-circle'),
                                        TextEntry::make('applied_at')
                                            ->label('Applied At')
                                            ->dateTime(),
                                        TextEntry::make('appliedByUser.name')
                                            ->label('Applied By')
                                            ->placeholder('System'),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No payments applied to this invoice'),
                    ]),
            ]);
    }

    /**
     * Tab 3: Linked ERP Events - Source reference with link.
     */
    protected function getLinkedErpEventsTab(): Tab
    {
        /** @var Invoice $record */
        $record = $this->record;

        return Tab::make('Linked ERP Events')
            ->icon('heroicon-o-link')
            ->badge(fn (): ?string => $this->getLinkedErpEventsBadge($record))
            ->badgeColor('info')
            ->schema([
                Section::make('Source Reference')
                    ->description('The ERP event that triggered this invoice')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('invoice_type')
                                    ->label('Invoice Type')
                                    ->badge()
                                    ->formatStateUsing(fn (InvoiceType $state): string => $state->code().' - '.$state->label())
                                    ->color(fn (InvoiceType $state): string => $state->color())
                                    ->icon(fn (InvoiceType $state): string => $state->icon()),
                                TextEntry::make('source_type')
                                    ->label('Source Type')
                                    ->placeholder('No source type')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'subscription' => 'Subscription (Membership)',
                                        'voucher_sale', 'voucher_batch' => 'Voucher Sale',
                                        'shipping_order' => 'Shipping Order',
                                        'storage_billing_period' => 'Storage Billing Period',
                                        'event_booking' => 'Event Booking',
                                        default => $state ?? 'Manual Invoice',
                                    }),
                                TextEntry::make('source_id_display')
                                    ->label('Source ID')
                                    ->getStateUsing(fn (Invoice $record): string => $this->formatSourceId($record))
                                    ->placeholder('N/A')
                                    ->copyable()
                                    ->copyMessage('Source ID copied'),
                            ]),
                    ]),
                // Multi-shipment section (INV2)
                $this->getMultiShipmentSection(),
                // Storage location breakdown section (INV3)
                $this->getStorageLocationBreakdownSection(),
                Section::make('Event Details')
                    ->description(fn (Invoice $record): string => $this->getSourceDescription($record))
                    ->visible(fn (Invoice $record): bool => ! $record->isMultiShipmentInvoice() && ! $record->hasLocationBreakdown())
                    ->schema([
                        TextEntry::make('source_link')
                            ->label('View Source')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSourceLinkLabel($record))
                            ->url(fn (Invoice $record): ?string => $this->getSourceUrl($record))
                            ->visible(fn (Invoice $record): bool => $record->source_type !== null && $record->source_id !== null)
                            ->color('primary')
                            ->icon('heroicon-o-arrow-top-right-on-square'),
                        TextEntry::make('no_source')
                            ->label('')
                            ->getStateUsing(fn (): string => 'This invoice was created manually or the source is not available.')
                            ->visible(fn (Invoice $record): bool => $record->source_type === null || $record->source_id === null)
                            ->color('gray'),
                    ]),
                $this->getSubscriptionDetailsSection(),
            ]);
    }

    /**
     * Get the badge for Linked ERP Events tab.
     */
    protected function getLinkedErpEventsBadge(Invoice $record): ?string
    {
        if ($record->isMultiShipmentInvoice()) {
            return (string) $record->getShipmentCount();
        }

        if ($record->hasLocationBreakdown()) {
            return (string) $record->getStorageLocationCount();
        }

        return null;
    }

    /**
     * Get the storage location breakdown section for INV3 invoices.
     */
    protected function getStorageLocationBreakdownSection(): Section
    {
        return Section::make('Storage Location Breakdown')
            ->description(fn (Invoice $record): string => $this->getStorageLocationDescription($record))
            ->visible(fn (Invoice $record): bool => $record->isStorageFeeInvoice())
            ->icon('heroicon-o-building-office-2')
            ->schema([
                TextEntry::make('storage_location_summary')
                    ->label('')
                    ->getStateUsing(fn (Invoice $record): string => $this->formatStorageLocationSummary($record))
                    ->html(),
            ]);
    }

    /**
     * Get description for storage location section.
     */
    protected function getStorageLocationDescription(Invoice $record): string
    {
        $locationCount = $record->getStorageLocationCount();
        $periodDates = $record->getStoragePeriodDates();
        $periodLabel = $periodDates !== null ? $periodDates['label'] : 'Unknown period';

        if ($locationCount > 1) {
            return "Storage fees across {$locationCount} locations for {$periodLabel}";
        }

        return "Storage fees for {$periodLabel}";
    }

    /**
     * Format the storage location summary for display.
     */
    protected function formatStorageLocationSummary(Invoice $record): string
    {
        $summaries = $record->getStorageLocationSummaries();

        if (empty($summaries)) {
            return '<span class="text-gray-500">No storage location details available</span>';
        }

        $currency = $record->currency;
        $currencySymbol = $record->getCurrencySymbol();
        $periodDates = $record->getStoragePeriodDates();
        $periodLabel = $periodDates !== null ? $periodDates['label'] : '';
        $hasMultipleLocations = count($summaries) > 1;
        $totalBottleDays = $record->getTotalBottleDays();

        $lines = [];
        $lines[] = '<div class="space-y-4">';

        // Period summary header
        if ($periodLabel !== '') {
            $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 mb-4">';
            $lines[] = '<div class="flex justify-between items-center">';
            $lines[] = '<span class="text-sm text-gray-600 dark:text-gray-400">Billing Period:</span>';
            $lines[] = '<span class="font-semibold">'.e($periodLabel).'</span>';
            $lines[] = '</div>';
            $lines[] = '<div class="flex justify-between items-center mt-1">';
            $lines[] = '<span class="text-sm text-gray-600 dark:text-gray-400">Total Bottle-Days:</span>';
            $lines[] = '<span class="font-semibold">'.number_format($totalBottleDays).'</span>';
            $lines[] = '</div>';
            $lines[] = '</div>';
        }

        // Location breakdown
        foreach ($summaries as $locationKey => $summary) {
            $locationName = $summary['location_name'];
            $bottleCount = $summary['bottle_count'];
            $bottleDays = $summary['bottle_days'];
            $unitRate = $summary['unit_rate'];
            $rateTier = $summary['rate_tier'] ?? 'Standard';
            $subtotal = $summary['subtotal'];
            $tax = $summary['tax'];
            $total = $summary['total'];

            $lines[] = '<div class="border rounded-lg p-4 bg-white dark:bg-gray-900">';

            // Location header
            $lines[] = '<div class="flex justify-between items-start mb-3">';
            $lines[] = '<div>';
            $lines[] = '<h4 class="font-semibold text-lg flex items-center gap-2">';
            $lines[] = '<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            $lines[] = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>';
            $lines[] = '</svg>';
            $lines[] = e($locationName);
            $lines[] = '</h4>';
            $lines[] = '</div>';
            $lines[] = '<div class="text-right">';
            $lines[] = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">';
            $lines[] = e($rateTier);
            $lines[] = '</span>';
            $lines[] = '</div>';
            $lines[] = '</div>';

            // Usage details table
            $lines[] = '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">';
            $lines[] = '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">';

            // Bottle count row
            $lines[] = '<tr>';
            $lines[] = '<td class="px-2 py-2 text-gray-600 dark:text-gray-400">Average Bottles</td>';
            $lines[] = '<td class="px-2 py-2 text-right font-mono font-semibold">'.number_format($bottleCount).'</td>';
            $lines[] = '</tr>';

            // Bottle-days row
            $lines[] = '<tr>';
            $lines[] = '<td class="px-2 py-2 text-gray-600 dark:text-gray-400">Bottle-Days</td>';
            $lines[] = '<td class="px-2 py-2 text-right font-mono font-semibold">'.number_format($bottleDays).'</td>';
            $lines[] = '</tr>';

            // Rate row
            $lines[] = '<tr>';
            $lines[] = '<td class="px-2 py-2 text-gray-600 dark:text-gray-400">Rate per Bottle-Day</td>';
            $lines[] = '<td class="px-2 py-2 text-right font-mono">'.$currencySymbol.' '.number_format((float) $unitRate, 4).'</td>';
            $lines[] = '</tr>';

            // Subtotal row
            $lines[] = '<tr class="border-t border-gray-200 dark:border-gray-700">';
            $lines[] = '<td class="px-2 py-2 text-gray-600 dark:text-gray-400">Subtotal</td>';
            $lines[] = '<td class="px-2 py-2 text-right font-mono">'.$currencySymbol.' '.number_format((float) $subtotal, 2).'</td>';
            $lines[] = '</tr>';

            // Tax row
            $lines[] = '<tr>';
            $lines[] = '<td class="px-2 py-2 text-gray-600 dark:text-gray-400">Tax</td>';
            $lines[] = '<td class="px-2 py-2 text-right font-mono">'.$currencySymbol.' '.number_format((float) $tax, 2).'</td>';
            $lines[] = '</tr>';

            // Total row
            $lines[] = '<tr class="bg-gray-50 dark:bg-gray-800">';
            $lines[] = '<td class="px-2 py-2 font-semibold">Location Total</td>';
            $lines[] = '<td class="px-2 py-2 text-right font-mono font-bold">'.$currencySymbol.' '.number_format((float) $total, 2).'</td>';
            $lines[] = '</tr>';

            $lines[] = '</tbody>';
            $lines[] = '</table>';

            $lines[] = '</div>';
        }

        // Grand total summary (only if multiple locations)
        if ($hasMultipleLocations) {
            $lines[] = '<div class="border-t-2 border-gray-300 dark:border-gray-600 pt-4 mt-4">';
            $lines[] = '<div class="flex justify-between items-center">';
            $lines[] = '<span class="text-lg font-semibold">Combined Total ('.count($summaries).' locations):</span>';
            $lines[] = '<span class="text-xl font-bold">'.$currencySymbol.' '.number_format((float) $record->total_amount, 2).'</span>';
            $lines[] = '</div>';
            $lines[] = '</div>';
        }

        // Link to storage billing period
        if ($record->source_id !== null) {
            $lines[] = '<div class="mt-4 text-right">';
            $lines[] = '<a href="'.route('filament.admin.resources.finance.storage-billing-periods.view', ['record' => $record->source_id]).'" ';
            $lines[] = 'class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 inline-flex items-center gap-1">';
            $lines[] = 'View Storage Billing Period';
            $lines[] = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            $lines[] = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>';
            $lines[] = '</svg>';
            $lines[] = '</a>';
            $lines[] = '</div>';
        }

        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Get the multi-shipment section for INV2 invoices aggregating multiple shipments.
     */
    protected function getMultiShipmentSection(): Section
    {
        return Section::make('Aggregated Shipments')
            ->description(fn (Invoice $record): string => "This invoice aggregates {$record->getShipmentCount()} shipments into a single invoice")
            ->visible(fn (Invoice $record): bool => $record->isMultiShipmentInvoice())
            ->icon('heroicon-o-truck')
            ->schema([
                TextEntry::make('multi_shipment_summary')
                    ->label('')
                    ->getStateUsing(fn (Invoice $record): string => $this->formatMultiShipmentSummary($record))
                    ->html(),
            ]);
    }

    /**
     * Format the multi-shipment summary for display.
     */
    protected function formatMultiShipmentSummary(Invoice $record): string
    {
        $summaries = $record->getShipmentSummaries();

        if (empty($summaries)) {
            return '<span class="text-gray-500">No shipment details available</span>';
        }

        $currency = $record->currency;
        $currencySymbol = $record->getCurrencySymbol();
        $lines = [];

        $lines[] = '<div class="space-y-4">';

        foreach ($summaries as $orderId => $summary) {
            $carrierInfo = $record->getCarrierInfoForShippingOrder($orderId);
            $carrierName = $carrierInfo['carrier_name'] ?? 'N/A';
            $trackingNumber = $carrierInfo['tracking_number'] ?? null;

            $lines[] = '<div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">';
            $lines[] = '<div class="flex justify-between items-start mb-3">';
            $lines[] = '<div>';
            $lines[] = '<h4 class="font-semibold text-lg">Shipment: '.e($orderId).'</h4>';
            $lines[] = '<p class="text-sm text-gray-600 dark:text-gray-400">';
            $lines[] = 'Carrier: '.e($carrierName);
            if ($trackingNumber !== null) {
                $lines[] = ' | Tracking: '.e($trackingNumber);
            }
            $lines[] = '</p>';
            $lines[] = '</div>';
            $lines[] = '<div class="text-right">';
            $lines[] = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">';
            $lines[] = $summary['line_count'].' lines';
            $lines[] = '</span>';
            $lines[] = '</div>';
            $lines[] = '</div>';

            // Table of lines for this shipment
            $lines[] = '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">';
            $lines[] = '<thead class="bg-white dark:bg-gray-900">';
            $lines[] = '<tr>';
            $lines[] = '<th class="px-2 py-1 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Description</th>';
            $lines[] = '<th class="px-2 py-1 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Qty</th>';
            $lines[] = '<th class="px-2 py-1 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Unit Price</th>';
            $lines[] = '<th class="px-2 py-1 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Total</th>';
            $lines[] = '</tr>';
            $lines[] = '</thead>';
            $lines[] = '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">';

            foreach ($summary['lines'] as $invoiceLine) {
                $lineType = $invoiceLine->metadata['line_type'] ?? 'shipping';
                $lineTypeClass = $this->getLineTypeClass($lineType);

                $lines[] = '<tr>';
                $lines[] = '<td class="px-2 py-1">';
                $lines[] = '<span class="'.$lineTypeClass.'">'.e($invoiceLine->description).'</span>';
                $lines[] = '</td>';
                $lines[] = '<td class="px-2 py-1 text-right font-mono">'.number_format((float) $invoiceLine->quantity, 2).'</td>';
                $lines[] = '<td class="px-2 py-1 text-right font-mono">'.$currencySymbol.' '.number_format((float) $invoiceLine->unit_price, 2).'</td>';
                $lines[] = '<td class="px-2 py-1 text-right font-mono font-semibold">'.$currencySymbol.' '.number_format((float) $invoiceLine->line_total, 2).'</td>';
                $lines[] = '</tr>';
            }

            $lines[] = '</tbody>';

            // Subtotals for this shipment
            $lines[] = '<tfoot class="bg-gray-100 dark:bg-gray-800">';
            $lines[] = '<tr class="font-semibold">';
            $lines[] = '<td colspan="3" class="px-2 py-1 text-right">Shipment Total:</td>';
            $lines[] = '<td class="px-2 py-1 text-right font-mono">'.$currencySymbol.' '.number_format((float) $summary['total'], 2).'</td>';
            $lines[] = '</tr>';
            $lines[] = '</tfoot>';

            $lines[] = '</table>';

            // View shipping order link
            $lines[] = '<div class="mt-2 text-right">';
            $lines[] = '<a href="'.route('filament.admin.resources.fulfillment.shipping-orders.view', ['record' => $orderId]).'" ';
            $lines[] = 'class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 inline-flex items-center gap-1">';
            $lines[] = 'View Shipping Order';
            $lines[] = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            $lines[] = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>';
            $lines[] = '</svg>';
            $lines[] = '</a>';
            $lines[] = '</div>';

            $lines[] = '</div>';
        }

        // Grand total summary
        $lines[] = '<div class="border-t-2 border-gray-300 dark:border-gray-600 pt-4 mt-4">';
        $lines[] = '<div class="flex justify-between items-center">';
        $lines[] = '<span class="text-lg font-semibold">Combined Total ('.$record->getShipmentCount().' shipments):</span>';
        $lines[] = '<span class="text-xl font-bold">'.$currencySymbol.' '.number_format((float) $record->total_amount, 2).'</span>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Get CSS class for line type badges.
     */
    protected function getLineTypeClass(string $lineType): string
    {
        return match ($lineType) {
            'shipping' => '',
            'insurance' => 'text-blue-600 dark:text-blue-400',
            'packaging' => 'text-amber-600 dark:text-amber-400',
            'handling' => 'text-purple-600 dark:text-purple-400',
            'duties' => 'text-red-600 dark:text-red-400',
            'taxes' => 'text-orange-600 dark:text-orange-400',
            'redemption' => 'text-green-600 dark:text-green-400 font-semibold',
            default => '',
        };
    }

    /**
     * Format source ID for display.
     *
     * For multi-shipment invoices, shows count and first ID.
     */
    protected function formatSourceId(Invoice $record): string
    {
        if ($record->source_id === null) {
            return 'N/A';
        }

        if ($record->isMultiShipmentInvoice()) {
            $ids = $record->getShippingOrderIds();
            $count = count($ids);

            return "{$count} shipments (see below)";
        }

        return $record->source_id;
    }

    /**
     * Get subscription details section for INV0 invoices.
     */
    protected function getSubscriptionDetailsSection(): Section
    {
        return Section::make('Subscription Details')
            ->description('Membership subscription information for this INV0 invoice')
            ->visible(fn (Invoice $record): bool => $record->isSubscriptionInvoice())
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('subscription_plan_name')
                            ->label('Plan Name')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSubscriptionField($record, 'plan_name') ?? 'N/A')
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-tag'),
                        TextEntry::make('subscription_plan_type')
                            ->label('Plan Type')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSubscriptionField($record, 'plan_type_label') ?? 'N/A')
                            ->badge()
                            ->color(fn (Invoice $record): string => $this->getSubscriptionField($record, 'plan_type_color') ?? 'gray'),
                        TextEntry::make('subscription_status')
                            ->label('Subscription Status')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSubscriptionField($record, 'status_label') ?? 'N/A')
                            ->badge()
                            ->color(fn (Invoice $record): string => $this->getSubscriptionField($record, 'status_color') ?? 'gray'),
                    ]),
                Grid::make(3)
                    ->schema([
                        TextEntry::make('subscription_billing_cycle')
                            ->label('Billing Cycle')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSubscriptionField($record, 'billing_cycle_label') ?? 'N/A')
                            ->badge()
                            ->color(fn (Invoice $record): string => $this->getSubscriptionField($record, 'billing_cycle_color') ?? 'gray')
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('subscription_amount')
                            ->label('Subscription Amount')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSubscriptionField($record, 'formatted_amount') ?? 'N/A'),
                        TextEntry::make('subscription_next_billing')
                            ->label('Next Billing Date')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSubscriptionField($record, 'next_billing_date') ?? 'N/A'),
                    ]),
                Grid::make(2)
                    ->schema([
                        TextEntry::make('subscription_started')
                            ->label('Started At')
                            ->getStateUsing(fn (Invoice $record): string => $this->getSubscriptionField($record, 'started_at') ?? 'N/A'),
                        TextEntry::make('subscription_stripe_id')
                            ->label('Stripe Subscription ID')
                            ->getStateUsing(fn (Invoice $record): ?string => $this->getSubscriptionField($record, 'stripe_subscription_id'))
                            ->placeholder('Not linked to Stripe')
                            ->copyable()
                            ->copyMessage('Stripe ID copied')
                            ->visible(fn (Invoice $record): bool => $this->getSubscriptionField($record, 'stripe_subscription_id') !== null),
                    ]),
                Section::make('Membership Tier Details')
                    ->description('Additional metadata from the subscription')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('subscription_metadata')
                            ->label('')
                            ->getStateUsing(fn (Invoice $record): string => $this->formatSubscriptionMetadata($record))
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get a field from the linked subscription.
     */
    protected function getSubscriptionField(Invoice $record, string $field): ?string
    {
        $subscription = $record->getSourceSubscription();

        if ($subscription === null) {
            return null;
        }

        return match ($field) {
            'plan_name' => $subscription->plan_name,
            'plan_type_label' => $subscription->getPlanTypeLabel(),
            'plan_type_color' => $subscription->getPlanTypeColor(),
            'status_label' => $subscription->getStatusLabel(),
            'status_color' => $subscription->getStatusColor(),
            'billing_cycle_label' => $subscription->getBillingCycleLabel(),
            'billing_cycle_color' => $subscription->getBillingCycleColor(),
            'formatted_amount' => $subscription->getFormattedAmount(),
            'next_billing_date' => $subscription->next_billing_date->format('M d, Y'),
            'started_at' => $subscription->started_at->format('M d, Y'),
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            default => null,
        };
    }

    /**
     * Format subscription metadata for display.
     */
    protected function formatSubscriptionMetadata(Invoice $record): string
    {
        $subscription = $record->getSourceSubscription();

        if ($subscription === null) {
            return '<span class="text-gray-500">No subscription data available</span>';
        }

        $lines = [];

        // Add core subscription info
        $lines[] = '<div class="grid grid-cols-2 gap-4">';
        $customerName = $subscription->customer !== null ? $subscription->customer->name : 'N/A';
        $lines[] = '<div><strong>Customer:</strong> '.e($customerName).'</div>';
        $lines[] = '<div><strong>Currency:</strong> '.$subscription->currency.'</div>';
        $lines[] = '<div><strong>Plan Type:</strong> '.$subscription->plan_type->label().'</div>';
        $lines[] = '<div><strong>Billing Cycle:</strong> '.$subscription->billing_cycle->label().'</div>';
        $lines[] = '</div>';

        // Add metadata if available
        if ($subscription->metadata !== null && count($subscription->metadata) > 0) {
            $lines[] = '<hr class="my-4">';
            $lines[] = '<strong>Additional Metadata:</strong>';
            $lines[] = '<div class="mt-2 bg-gray-50 p-3 rounded-lg text-sm font-mono">';
            foreach ($subscription->metadata as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                $lines[] = '<div><span class="text-gray-600">'.$key.':</span> '.e($displayValue).'</div>';
            }
            $lines[] = '</div>';
        }

        // Also show invoice line metadata (contains membership tier details)
        $firstLine = $record->invoiceLines()->first();
        if ($firstLine !== null && is_array($firstLine->metadata) && count($firstLine->metadata) > 0) {
            $lines[] = '<hr class="my-4">';
            $lines[] = '<strong>Invoice Line Metadata (Billing Period Details):</strong>';
            $lines[] = '<div class="mt-2 bg-blue-50 p-3 rounded-lg text-sm font-mono">';
            foreach ($firstLine->metadata as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                $displayKey = str_replace('_', ' ', ucfirst($key));
                $lines[] = '<div><span class="text-blue-600">'.$displayKey.':</span> '.e($displayValue).'</div>';
            }
            $lines[] = '</div>';
        }

        return implode("\n", $lines);
    }

    /**
     * Tab 4: Accounting - Xero sync info, statutory invoice number, GL posting, FX rate.
     */
    protected function getAccountingTab(): Tab
    {
        return Tab::make('Accounting')
            ->icon('heroicon-o-calculator')
            ->schema([
                $this->getTaxBreakdownSection(),
                Section::make('Xero Integration')
                    ->description('Synchronization status with Xero accounting system')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('xero_invoice_id')
                                    ->label('Xero Invoice ID')
                                    ->copyable()
                                    ->copyMessage('Xero Invoice ID copied')
                                    ->placeholder('Not synced with Xero'),
                                TextEntry::make('xero_synced_at')
                                    ->label('Last Synced')
                                    ->dateTime()
                                    ->placeholder('Never synced'),
                                TextEntry::make('xero_sync_status')
                                    ->label('Sync Status')
                                    ->getStateUsing(fn (Invoice $record): string => $record->xero_invoice_id !== null ? 'Synced' : 'Pending')
                                    ->badge()
                                    ->color(fn (Invoice $record): string => $record->xero_invoice_id !== null ? 'success' : 'warning'),
                            ]),
                    ]),
                Section::make('Statutory Information')
                    ->description('Official invoice identification and tax details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('invoice_number')
                                    ->label('Statutory Invoice Number')
                                    ->placeholder('Not issued')
                                    ->helperText('Sequential invoice number generated at issuance'),
                                TextEntry::make('currency')
                                    ->label('Invoice Currency')
                                    ->badge()
                                    ->formatStateUsing(fn (Invoice $record): string => $record->currency.' ('.$record->getCurrencySymbol().')')
                                    ->color('gray')
                                    ->helperText(fn (Invoice $record): string => $record->isBaseCurrency() ? 'Base currency' : 'Foreign currency'),
                                TextEntry::make('fx_rate_info')
                                    ->label('FX Rate')
                                    ->getStateUsing(fn (Invoice $record): string => $record->isBaseCurrency()
                                        ? 'N/A (Base currency)'
                                        : ($record->hasFxRate() ? $record->getFxRateDescription() ?? 'N/A' : 'Pending (Draft)'))
                                    ->helperText('Exchange rate snapshot at issuance'),
                            ]),
                    ]),
                Section::make('General Ledger')
                    ->description('Accounting classification and posting information')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('gl_account')
                                    ->label('GL Account')
                                    ->getStateUsing(fn (Invoice $record): string => $this->getGlAccount($record))
                                    ->helperText('Determined by invoice type'),
                                TextEntry::make('tax_classification')
                                    ->label('Tax Classification')
                                    ->getStateUsing(fn (Invoice $record): string => $this->getTaxClassification($record)),
                            ]),
                    ]),
                Section::make('Foreign Exchange')
                    ->description('Exchange rate information for multi-currency invoices')
                    ->collapsed()
                    ->visible(fn (Invoice $record): bool => ! $record->isBaseCurrency())
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('currency')
                                    ->label('Invoice Currency')
                                    ->formatStateUsing(fn (Invoice $record): string => $record->currency.' ('.$record->getCurrencySymbol().')'),
                                TextEntry::make('fx_rate_at_issuance')
                                    ->label('FX Rate at Issuance')
                                    ->formatStateUsing(fn (Invoice $record): string => $record->hasFxRate()
                                        ? $record->fx_rate_at_issuance
                                        : 'Not captured (draft)')
                                    ->helperText(fn (Invoice $record): ?string => $record->getFxRateDescription()),
                                TextEntry::make('total_in_eur')
                                    ->label('Total in EUR')
                                    ->getStateUsing(fn (Invoice $record): string => $record->getTotalInBaseCurrency() ?? 'N/A')
                                    ->formatStateUsing(fn (string $state): string => $state === 'N/A' ? $state : '€ '.$state)
                                    ->helperText('Calculated using FX rate at issuance'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - Immutable event timeline (status changes, payments, credits).
     */
    protected function getAuditTab(): Tab
    {
        /** @var Invoice $record */
        $record = $this->record;
        $auditCount = $record->auditLogs()->count();

        return Tab::make('Audit')
            ->icon('heroicon-o-clock')
            ->badge($auditCount > 0 ? (string) $auditCount : null)
            ->badgeColor('gray')
            ->schema([
                Section::make('Audit Timeline')
                    ->description('Immutable record of all changes to this invoice')
                    ->schema([
                        RepeatableEntry::make('auditLogs')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Timestamp')
                                            ->dateTime()
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('event')
                                            ->label('Event')
                                            ->badge()
                                            ->formatStateUsing(fn (AuditLog $log): string => $log->getEventLabel())
                                            ->color(fn (AuditLog $log): string => $log->getEventColor())
                                            ->icon(fn (AuditLog $log): string => $log->getEventIcon()),
                                        TextEntry::make('user.name')
                                            ->label('User')
                                            ->placeholder('System'),
                                        TextEntry::make('changes')
                                            ->label('Changes')
                                            ->getStateUsing(fn (AuditLog $log): string => $this->formatAuditChanges($log))
                                            ->html()
                                            ->columnSpan(4),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No audit records available'),
                    ]),
            ]);
    }

    /**
     * Get the description for the source based on invoice type.
     */
    protected function getSourceDescription(Invoice $record): string
    {
        return match ($record->invoice_type) {
            InvoiceType::MembershipService => 'Membership subscription invoice (INV0)',
            InvoiceType::VoucherSale => 'Voucher sale invoice (INV1)',
            InvoiceType::ShippingRedemption => 'Shipping redemption invoice (INV2)',
            InvoiceType::StorageFee => 'Storage fee invoice (INV3)',
            InvoiceType::ServiceEvents => 'Service events invoice (INV4)',
        };
    }

    /**
     * Get the source link label based on source type.
     */
    protected function getSourceLinkLabel(Invoice $record): string
    {
        return match ($record->source_type) {
            'subscription' => 'View Subscription',
            'voucher_sale', 'voucher_batch' => 'View Voucher Sale',
            'shipping_order' => 'View Shipping Order',
            'storage_billing_period' => 'View Storage Billing Period',
            'event_booking' => 'View Event Booking',
            default => 'View Source',
        };
    }

    /**
     * Get the source URL based on source type.
     */
    protected function getSourceUrl(Invoice $record): ?string
    {
        if ($record->source_type === null || $record->source_id === null) {
            return null;
        }

        return match ($record->source_type) {
            'subscription' => route('filament.admin.resources.finance.subscriptions.view', ['record' => $record->source_id]),
            'shipping_order' => route('filament.admin.resources.fulfillment.shipping-orders.view', ['record' => $record->source_id]),
            'storage_billing_period' => route('filament.admin.resources.finance.storage-billing-periods.view', ['record' => $record->source_id]),
            default => null,
        };
    }

    /**
     * Get the GL account based on invoice type.
     */
    protected function getGlAccount(Invoice $record): string
    {
        return match ($record->invoice_type) {
            InvoiceType::MembershipService => '4100 - Membership Revenue',
            InvoiceType::VoucherSale => '4200 - Voucher Sales Revenue',
            InvoiceType::ShippingRedemption => '4300 - Shipping Revenue',
            InvoiceType::StorageFee => '4400 - Storage Revenue',
            InvoiceType::ServiceEvents => '4500 - Services Revenue',
        };
    }

    /**
     * Get tax classification based on invoice type and customer.
     */
    protected function getTaxClassification(Invoice $record): string
    {
        return 'Standard VAT ('.$record->currency.')';
    }

    /**
     * Get the tax breakdown section for the Accounting tab.
     */
    protected function getTaxBreakdownSection(): Section
    {
        return Section::make('Tax Breakdown')
            ->description('VAT/Tax amounts by rate')
            ->schema([
                // Summary row
                Grid::make(4)
                    ->schema([
                        TextEntry::make('tax_subtotal')
                            ->label('Subtotal (excl. Tax)')
                            ->getStateUsing(fn (Invoice $record): string => $record->getTaxBreakdown()['total_subtotal'])
                            ->money(fn (Invoice $record): string => $record->currency),
                        TextEntry::make('tax_total')
                            ->label('Total Tax')
                            ->getStateUsing(fn (Invoice $record): string => $record->getTaxBreakdown()['total_tax'])
                            ->money(fn (Invoice $record): string => $record->currency)
                            ->color('warning'),
                        TextEntry::make('destination_info')
                            ->label('Destination Country')
                            ->getStateUsing(fn (Invoice $record): string => $this->getDestinationCountryDisplay($record))
                            ->visible(fn (Invoice $record): bool => $record->isShippingInvoice()),
                        TextEntry::make('cross_border_status')
                            ->label('Cross-Border')
                            ->getStateUsing(fn (Invoice $record): string => $record->isCrossBorderShipment() ? 'Yes - Cross-border shipment' : 'No - Domestic')
                            ->badge()
                            ->color(fn (Invoice $record): string => $record->isCrossBorderShipment() ? 'info' : 'gray')
                            ->visible(fn (Invoice $record): bool => $record->isShippingInvoice()),
                    ]),

                // Tax rate breakdown
                Section::make('Tax by Rate')
                    ->description('Breakdown of taxable amounts and tax by rate')
                    ->collapsed(fn (Invoice $record): bool => ! $record->hasMixedTaxRates())
                    ->schema([
                        TextEntry::make('tax_breakdown_display')
                            ->label('')
                            ->getStateUsing(fn (Invoice $record): string => $this->formatTaxBreakdown($record))
                            ->html(),
                    ]),

                // Duties section (for cross-border shipping)
                Section::make('Customs Duties')
                    ->description('Customs duties for cross-border shipments')
                    ->visible(fn (Invoice $record): bool => $record->hasDuties())
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('duties_amount')
                                    ->label('Duties Amount')
                                    ->getStateUsing(function (Invoice $record): string {
                                        $breakdown = $record->getTaxBreakdown();

                                        return $breakdown['duty_summary']['duty_amount'] ?? '0.00';
                                    })
                                    ->money(fn (Invoice $record): string => $record->currency)
                                    ->color('danger'),
                                TextEntry::make('duties_info')
                                    ->label('Note')
                                    ->getStateUsing(fn (): string => 'Customs duties are typically not subject to VAT. They are charged as a pass-through cost.')
                                    ->color('gray'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Format the tax breakdown for display.
     */
    protected function formatTaxBreakdown(Invoice $record): string
    {
        $breakdown = $record->getTaxBreakdown();

        if (empty($breakdown['tax_breakdown'])) {
            return '<span class="text-gray-500">No tax information available</span>';
        }

        $currency = $record->currency;
        $currencySymbol = $record->getCurrencySymbol();
        $lines = [];

        $lines[] = '<div class="overflow-x-auto">';
        $lines[] = '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
        $lines[] = '<thead class="bg-gray-50 dark:bg-gray-800">';
        $lines[] = '<tr>';
        $lines[] = '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tax Rate</th>';
        $lines[] = '<th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Taxable Amount</th>';
        $lines[] = '<th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tax Amount</th>';
        $lines[] = '<th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lines</th>';
        $lines[] = '</tr>';
        $lines[] = '</thead>';
        $lines[] = '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

        foreach ($breakdown['tax_breakdown'] as $rateData) {
            $rate = $rateData['rate'];
            $taxableAmount = number_format((float) $rateData['taxable_amount'], 2);
            $taxAmount = number_format((float) $rateData['tax_amount'], 2);
            $lineCount = $rateData['line_count'];
            $description = $rateData['description'];

            // Determine badge color based on rate
            $badgeColor = bccomp($rate, '0', 2) === 0 ? 'bg-gray-100 text-gray-800' : 'bg-blue-100 text-blue-800';

            $lines[] = '<tr>';
            $lines[] = '<td class="px-4 py-2 text-sm">';
            $lines[] = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$badgeColor.'">';
            $lines[] = e($description);
            $lines[] = '</span>';
            $lines[] = '</td>';
            $lines[] = '<td class="px-4 py-2 text-sm text-right font-mono">'.$currencySymbol.' '.$taxableAmount.'</td>';
            $lines[] = '<td class="px-4 py-2 text-sm text-right font-mono font-semibold">'.$currencySymbol.' '.$taxAmount.'</td>';
            $lines[] = '<td class="px-4 py-2 text-sm text-center">'.$lineCount.'</td>';
            $lines[] = '</tr>';
        }

        $lines[] = '</tbody>';

        // Footer with totals
        $lines[] = '<tfoot class="bg-gray-50 dark:bg-gray-800">';
        $lines[] = '<tr class="font-semibold">';
        $lines[] = '<td class="px-4 py-2 text-sm">Total</td>';
        $lines[] = '<td class="px-4 py-2 text-sm text-right font-mono">'.$currencySymbol.' '.number_format((float) $breakdown['total_subtotal'], 2).'</td>';
        $lines[] = '<td class="px-4 py-2 text-sm text-right font-mono">'.$currencySymbol.' '.number_format((float) $breakdown['total_tax'], 2).'</td>';
        $lines[] = '<td class="px-4 py-2 text-sm text-center">-</td>';
        $lines[] = '</tr>';
        $lines[] = '</tfoot>';

        $lines[] = '</table>';
        $lines[] = '</div>';

        // Add cross-border notice if applicable
        if ($breakdown['is_cross_border']) {
            $destination = $breakdown['destination_country'] ?? 'unknown';
            $lines[] = '<div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm">';
            $lines[] = '<span class="font-semibold text-blue-700 dark:text-blue-300">Cross-Border Shipment:</span> ';
            $lines[] = '<span class="text-blue-600 dark:text-blue-400">Tax rate determined by destination country ('.$destination.'). ';

            if ($breakdown['duty_summary'] !== null && $breakdown['duty_summary']['has_duties']) {
                $lines[] = 'Customs duties included in invoice.';
            }

            $lines[] = '</span>';
            $lines[] = '</div>';
        }

        return implode("\n", $lines);
    }

    /**
     * Get destination country display string.
     */
    protected function getDestinationCountryDisplay(Invoice $record): string
    {
        $destination = $record->getDestinationCountry();

        if ($destination === null) {
            return 'Not specified';
        }

        // Map country codes to names
        $countryNames = [
            'IT' => 'Italy',
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'BE' => 'Belgium',
            'NL' => 'Netherlands',
            'PT' => 'Portugal',
        ];

        $countryName = $countryNames[$destination] ?? $destination;

        return "{$countryName} ({$destination})";
    }

    /**
     * Format audit log changes for display.
     */
    protected function formatAuditChanges(AuditLog $log): string
    {
        $changes = [];

        if ($log->event === AuditLog::EVENT_CREATED) {
            return '<span class="text-success-600">Invoice created</span>';
        }

        /** @var array<string, mixed>|null $oldValues */
        $oldValues = $log->old_values;
        /** @var array<string, mixed>|null $newValues */
        $newValues = $log->new_values;

        if ($oldValues !== null && $newValues !== null) {
            foreach ($newValues as $key => $newValue) {
                $oldValue = $oldValues[$key] ?? 'null';
                $changes[] = "<strong>{$key}</strong>: {$oldValue} → {$newValue}";
            }
        } elseif ($newValues !== null) {
            foreach ($newValues as $key => $newValue) {
                $changes[] = "<strong>{$key}</strong>: {$newValue}";
            }
        }

        return count($changes) > 0 ? implode('<br>', $changes) : 'No details available';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getDownloadPdfAction(),
            $this->getSendToCustomerAction(),
            $this->getIssueAction(),
            $this->getRecordBankPaymentAction(),
            $this->getCreateCreditNoteAction(),
            $this->getCreateRefundAction(),
            $this->getCancelAction(),
        ];
    }

    /**
     * Download PDF action - visible for issued/paid invoices.
     */
    protected function getDownloadPdfAction(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->visible(function (): bool {
                $pdfService = app(InvoicePdfService::class);

                return $pdfService->canGeneratePdf($this->getInvoice());
            })
            ->action(function () {
                $pdfService = app(InvoicePdfService::class);

                try {
                    $invoice = $this->getInvoice();
                    $content = $pdfService->getContent($invoice);
                    $filename = $pdfService->getFilename($invoice);

                    return response()->streamDownload(
                        fn () => print ($content),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot Generate PDF')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }
            });
    }

    /**
     * Send to Customer action - visible for issued invoices.
     */
    protected function getSendToCustomerAction(): Action
    {
        return Action::make('sendToCustomer')
            ->label('Send to Customer')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->visible(function (): bool {
                $mailService = app(InvoiceMailService::class);

                return $mailService->canSendEmail($this->getInvoice());
            })
            ->form([
                Placeholder::make('recipient_info')
                    ->label('Recipient')
                    ->content(function (): string {
                        $invoice = $this->getInvoice();
                        $invoice->loadMissing('customer');

                        $customerName = $invoice->customer !== null ? $invoice->customer->name : 'Unknown';
                        $customerEmail = $invoice->customer !== null ? $invoice->customer->email : 'No email';

                        return "{$customerName}\n{$customerEmail}";
                    }),
                TextInput::make('custom_subject')
                    ->label('Email Subject (Optional)')
                    ->maxLength(255)
                    ->placeholder(function (): string {
                        $invoice = $this->getInvoice();
                        $companyName = config('app.name', 'ERP4');

                        return "Invoice {$invoice->invoice_number} from {$companyName}";
                    })
                    ->helperText('Leave blank to use the default subject'),
                Textarea::make('custom_message')
                    ->label('Custom Message (Optional)')
                    ->rows(4)
                    ->maxLength(2000)
                    ->placeholder('Add a personalized message to include in the email...')
                    ->helperText('This message will appear in the email body above the invoice summary'),
            ])
            ->requiresConfirmation()
            ->modalHeading('Send Invoice to Customer')
            ->modalDescription(function (): string {
                $invoice = $this->getInvoice();
                $invoice->loadMissing('customer');

                return "Send invoice {$invoice->invoice_number} to {$invoice->customer?->name}?\n\n".
                    "Email will be sent to: {$invoice->customer?->email}\n".
                    'The invoice PDF will be attached automatically.';
            })
            ->modalSubmitActionLabel('Send Email')
            ->action(function (array $data): void {
                $mailService = app(InvoiceMailService::class);

                try {
                    $mailService->sendToCustomer(
                        invoice: $this->getInvoice(),
                        customSubject: $data['custom_subject'] ?? null,
                        customMessage: $data['custom_message'] ?? null,
                        sentBy: auth()->id()
                    );

                    $invoice = $this->getInvoice();
                    $invoice->loadMissing('customer');

                    Notification::make()
                        ->title('Invoice Sent')
                        ->body("Invoice {$invoice->invoice_number} has been sent to {$invoice->customer?->email}.")
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot Send Invoice')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Issue action - visible only if status = draft.
     */
    protected function getIssueAction(): Action
    {
        return Action::make('issue')
            ->label('Issue Invoice')
            ->icon('heroicon-o-document-check')
            ->color('success')
            ->visible(fn (): bool => $this->getInvoice()->isDraft())
            ->requiresConfirmation()
            ->modalHeading('Issue Invoice')
            ->modalDescription(function (): string {
                $invoice = $this->getInvoice();

                return "Are you sure you want to issue this invoice?\n\n".
                    "Invoice Total: {$invoice->currency} {$invoice->total_amount}\n".
                    'Customer: '.($invoice->customer !== null ? $invoice->customer->name : 'Unknown')."\n\n".
                    "Once issued:\n".
                    "• A sequential invoice number will be generated\n".
                    "• Invoice lines become immutable\n".
                    '• The invoice will be synced to Xero';
            })
            ->modalSubmitActionLabel('Issue Invoice')
            ->action(function (): void {
                $invoiceService = app(InvoiceService::class);

                try {
                    $invoice = $invoiceService->issue($this->getInvoice());

                    Notification::make()
                        ->title('Invoice Issued')
                        ->body("Invoice number {$invoice->invoice_number} has been issued successfully.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'invoice_number', 'issued_at']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot Issue Invoice')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Record Bank Payment action - visible only if status = issued or partially_paid.
     */
    protected function getRecordBankPaymentAction(): Action
    {
        return Action::make('recordBankPayment')
            ->label('Record Bank Payment')
            ->icon('heroicon-o-banknotes')
            ->color('info')
            ->visible(fn (): bool => $this->getInvoice()->canReceivePayment())
            ->form([
                Placeholder::make('invoice_info')
                    ->label('Invoice Details')
                    ->content(function (): string {
                        $invoice = $this->getInvoice();

                        return 'Invoice: '.($invoice->invoice_number ?? 'N/A')."\n".
                            "Outstanding: {$invoice->currency} {$invoice->getOutstandingAmount()}";
                    }),
                TextInput::make('amount')
                    ->label('Payment Amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(fn (): float => (float) $this->getInvoice()->getOutstandingAmount())
                    ->default(fn (): string => $this->getInvoice()->getOutstandingAmount())
                    ->prefix(fn (): string => $this->getInvoice()->currency)
                    ->helperText(fn (): string => "Maximum: {$this->getInvoice()->currency} {$this->getInvoice()->getOutstandingAmount()}"),
                TextInput::make('bank_reference')
                    ->label('Bank Reference')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., TRN-2026-001234')
                    ->helperText('The bank transaction reference for this payment'),
                DatePicker::make('received_at')
                    ->label('Date Received')
                    ->required()
                    ->default(now())
                    ->maxDate(now())
                    ->helperText('The date the payment was received'),
                Placeholder::make('partial_payment_warning')
                    ->label('')
                    ->content(function (\Filament\Forms\Get $get): \Illuminate\Contracts\Support\Htmlable|string {
                        $amount = $get('amount');
                        $outstanding = $this->getInvoice()->getOutstandingAmount();

                        if ($amount !== null && bccomp((string) $amount, $outstanding, 2) < 0) {
                            $remaining = bcsub($outstanding, (string) $amount, 2);
                            $currency = $this->getInvoice()->currency;

                            return new \Illuminate\Support\HtmlString(
                                '<div class="p-3 rounded-lg bg-warning-50 text-warning-700 dark:bg-warning-900/20 dark:text-warning-400">'.
                                '<strong>⚠ Partial Payment:</strong> This will leave '.
                                "{$currency} {$remaining} outstanding on this invoice.".
                                '</div>'
                            );
                        }

                        return '';
                    })
                    ->visible(fn (\Filament\Forms\Get $get): bool => $get('amount') !== null
                        && bccomp((string) $get('amount'), $this->getInvoice()->getOutstandingAmount(), 2) < 0),
            ])
            ->requiresConfirmation()
            ->modalHeading('Record Bank Payment')
            ->modalDescription('Record a bank transfer payment for this invoice. The payment will be automatically applied to this invoice.')
            ->modalSubmitActionLabel('Record Payment')
            ->action(function (array $data): void {
                $paymentService = app(PaymentService::class);
                $invoice = $this->getInvoice();
                $invoice->loadMissing('customer');

                try {
                    // Create bank payment
                    $payment = $paymentService->createBankPayment(
                        amount: (string) $data['amount'],
                        bankReference: $data['bank_reference'],
                        customer: $invoice->customer,
                        currency: $invoice->currency,
                        receivedAt: Carbon::parse($data['received_at'])
                    );

                    // Auto-apply the payment to this invoice
                    $paymentService->applyToInvoice($payment, $invoice, (string) $data['amount']);

                    // Mark as reconciled since we're applying directly to a known invoice
                    $paymentService->markReconciled($payment, \App\Enums\Finance\ReconciliationStatus::Matched);

                    // Refresh the record to show updated amounts
                    $this->record->refresh();

                    Notification::make()
                        ->title('Bank Payment Recorded')
                        ->body("Payment of {$invoice->currency} {$data['amount']} with reference {$data['bank_reference']} has been recorded and applied to invoice {$invoice->invoice_number}.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'amount_paid']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot Record Payment')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Create Credit Note action - visible only if status = issued, paid, partially_paid.
     */
    protected function getCreateCreditNoteAction(): Action
    {
        return Action::make('createCreditNote')
            ->label('Create Credit Note')
            ->icon('heroicon-o-receipt-refund')
            ->color('warning')
            ->visible(fn (): bool => $this->getInvoice()->canHaveCreditNote())
            ->form([
                Placeholder::make('invoice_info')
                    ->label('Original Invoice')
                    ->content(function (): string {
                        $invoice = $this->getInvoice();

                        return 'Invoice: '.($invoice->invoice_number ?? 'N/A')."\n".
                            "Total Amount: {$invoice->currency} {$invoice->total_amount}\n".
                            "Outstanding: {$invoice->currency} {$invoice->getOutstandingAmount()}";
                    }),
                TextInput::make('amount')
                    ->label('Credit Note Amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(fn (): float => (float) $this->getInvoice()->total_amount)
                    ->prefix(fn (): string => $this->getInvoice()->currency)
                    ->helperText(fn (): string => "Maximum: {$this->getInvoice()->currency} {$this->getInvoice()->total_amount}"),
                Textarea::make('reason')
                    ->label('Reason for Credit Note')
                    ->required()
                    ->rows(3)
                    ->maxLength(1000)
                    ->placeholder('Please provide a detailed reason for this credit note...')
                    ->helperText('A reason is required for audit purposes'),
            ])
            ->requiresConfirmation()
            ->modalHeading('Create Credit Note')
            ->modalDescription('Create a credit note against this invoice. The credit note will be created as a draft and must be issued separately.')
            ->modalSubmitActionLabel('Create Credit Note')
            ->action(function (array $data): void {
                $invoice = $this->getInvoice();
                $invoice->loadMissing('customer');

                // Validate amount is positive and <= invoice total
                $amount = (string) $data['amount'];
                if (bccomp($amount, '0', 2) <= 0) {
                    Notification::make()
                        ->title('Invalid Amount')
                        ->body('Credit note amount must be greater than zero.')
                        ->danger()
                        ->send();

                    return;
                }

                if (bccomp($amount, $invoice->total_amount, 2) > 0) {
                    Notification::make()
                        ->title('Invalid Amount')
                        ->body("Credit note amount cannot exceed invoice total of {$invoice->currency} {$invoice->total_amount}.")
                        ->danger()
                        ->send();

                    return;
                }

                // Create the credit note in draft status
                $creditNote = CreditNote::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'amount' => $amount,
                    'currency' => $invoice->currency,
                    'reason' => $data['reason'],
                    'status' => CreditNoteStatus::Draft,
                ]);

                // Log the creation in the audit trail
                $creditNote->auditLogs()->create([
                    'event' => AuditLog::EVENT_CREATED,
                    'old_values' => [],
                    'new_values' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $amount,
                        'currency' => $invoice->currency,
                        'reason' => $data['reason'],
                    ],
                    'user_id' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Credit Note Created')
                    ->body("Credit note for {$invoice->currency} {$amount} has been created as draft. Navigate to Credit Notes to issue it.")
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('View Credit Note')
                            ->url(route('filament.admin.resources.finance.credit-notes.view', ['record' => $creditNote->id])),
                    ])
                    ->send();
            });
    }

    /**
     * Create Refund action - visible only if invoice is paid.
     */
    protected function getCreateRefundAction(): Action
    {
        return Action::make('createRefund')
            ->label('Refund')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->visible(fn (): bool => $this->getInvoice()->isPaid())
            ->form([
                Placeholder::make('warning')
                    ->label('')
                    ->content(new \Illuminate\Support\HtmlString(
                        '<div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700">'.
                        '<div class="flex items-start gap-3">'.
                        '<svg class="w-6 h-6 text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">'.
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>'.
                        '</svg>'.
                        '<div>'.
                        '<h4 class="font-semibold text-warning-800 dark:text-warning-200">Important Notice</h4>'.
                        '<p class="text-sm text-warning-700 dark:text-warning-300 mt-1">'.
                        'Refunding does not automatically reverse operational effects (e.g., voucher cancellation). '.
                        'Coordinate with Operations if needed.'.
                        '</p>'.
                        '</div>'.
                        '</div>'.
                        '</div>'
                    )),
                Placeholder::make('invoice_info')
                    ->label('Invoice Details')
                    ->content(function (): string {
                        $invoice = $this->getInvoice();

                        return 'Invoice: '.($invoice->invoice_number ?? 'N/A')."\n".
                            "Total: {$invoice->currency} {$invoice->total_amount}\n".
                            "Amount Paid: {$invoice->currency} {$invoice->amount_paid}";
                    }),
                Select::make('refund_type')
                    ->label('Refund Type')
                    ->required()
                    ->options([
                        RefundType::Full->value => RefundType::Full->label(),
                        RefundType::Partial->value => RefundType::Partial->label(),
                    ])
                    ->default(RefundType::Partial->value)
                    ->live()
                    ->helperText('Choose Full for complete refund or Partial for a specific amount'),
                Select::make('payment_id')
                    ->label('Payment to Refund')
                    ->required()
                    ->options(function (): array {
                        $invoice = $this->getInvoice();
                        $invoice->loadMissing('invoicePayments.payment');

                        $options = [];
                        foreach ($invoice->invoicePayments as $invoicePayment) {
                            $payment = $invoicePayment->payment;
                            if ($payment !== null) {
                                $label = "{$payment->payment_reference} - {$invoice->currency} {$invoicePayment->amount_applied}";
                                if ($payment->stripe_charge_id !== null) {
                                    $label .= ' (Stripe)';
                                } else {
                                    $label .= ' (Bank Transfer)';
                                }
                                $options[$payment->id] = $label;
                            }
                        }

                        return $options;
                    })
                    ->live()
                    ->afterStateUpdated(function (\Filament\Forms\Set $set, \Filament\Forms\Get $get, ?string $state): void {
                        if ($state === null) {
                            return;
                        }

                        // Auto-set the refund method based on payment type
                        $invoice = $this->getInvoice();
                        $invoicePayment = $invoice->invoicePayments()
                            ->where('payment_id', $state)
                            ->with('payment')
                            ->first();

                        if ($invoicePayment !== null && $invoicePayment->payment !== null) {
                            $payment = $invoicePayment->payment;
                            if ($payment->stripe_charge_id !== null) {
                                $set('method', RefundMethod::Stripe->value);
                            } else {
                                $set('method', RefundMethod::BankTransfer->value);
                            }

                            // If full refund, set amount to the applied amount
                            if ($get('refund_type') === RefundType::Full->value) {
                                $set('amount', $invoicePayment->amount_applied);
                            }
                        }
                    })
                    ->helperText('Select the payment you want to refund'),
                TextInput::make('amount')
                    ->label('Refund Amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(function (\Filament\Forms\Get $get): float {
                        $paymentId = $get('payment_id');
                        if ($paymentId === null) {
                            return 0;
                        }

                        $invoice = $this->getInvoice();
                        $invoicePayment = $invoice->invoicePayments()
                            ->where('payment_id', $paymentId)
                            ->first();

                        return $invoicePayment !== null ? (float) $invoicePayment->amount_applied : 0;
                    })
                    ->prefix(fn (): string => $this->getInvoice()->currency)
                    ->disabled(fn (\Filament\Forms\Get $get): bool => $get('refund_type') === RefundType::Full->value)
                    ->dehydrated()
                    ->helperText(function (\Filament\Forms\Get $get): string {
                        $paymentId = $get('payment_id');
                        if ($paymentId === null) {
                            return 'Select a payment first';
                        }

                        $invoice = $this->getInvoice();
                        $invoicePayment = $invoice->invoicePayments()
                            ->where('payment_id', $paymentId)
                            ->first();

                        $maxAmount = $invoicePayment !== null ? $invoicePayment->amount_applied : '0.00';

                        return "Maximum: {$invoice->currency} {$maxAmount}";
                    }),
                Select::make('method')
                    ->label('Refund Method')
                    ->required()
                    ->options([
                        RefundMethod::Stripe->value => RefundMethod::Stripe->label(),
                        RefundMethod::BankTransfer->value => RefundMethod::BankTransfer->label(),
                    ])
                    ->helperText(function (\Filament\Forms\Get $get): string {
                        $method = $get('method');
                        if ($method === RefundMethod::Stripe->value) {
                            return 'Refund will be processed automatically via Stripe';
                        }

                        return 'You will need to process the bank transfer manually';
                    }),
                Textarea::make('reason')
                    ->label('Reason for Refund')
                    ->required()
                    ->rows(3)
                    ->maxLength(1000)
                    ->placeholder('Please provide a detailed reason for this refund...')
                    ->helperText('A reason is required for audit purposes'),
                Checkbox::make('confirm_understanding')
                    ->label('I understand this is a financial transaction only and does not reverse operational effects')
                    ->required()
                    ->accepted()
                    ->validationMessages([
                        'accepted' => 'You must confirm that you understand this is a financial transaction only.',
                    ]),
            ])
            ->requiresConfirmation()
            ->modalHeading('Create Refund')
            ->modalDescription('Create a refund for this paid invoice. Please review all details carefully.')
            ->modalSubmitActionLabel('Create Refund')
            ->action(function (array $data): void {
                $invoice = $this->getInvoice();
                $invoice->loadMissing('customer');

                // Get the selected payment
                $invoicePayment = $invoice->invoicePayments()
                    ->where('payment_id', $data['payment_id'])
                    ->with('payment')
                    ->first();

                if ($invoicePayment === null || $invoicePayment->payment === null) {
                    Notification::make()
                        ->title('Invalid Payment')
                        ->body('The selected payment could not be found.')
                        ->danger()
                        ->send();

                    return;
                }

                $payment = $invoicePayment->payment;

                // Determine amount based on refund type
                $refundType = RefundType::from($data['refund_type']);
                $amount = $refundType === RefundType::Full
                    ? $invoicePayment->amount_applied
                    : (string) $data['amount'];

                // Validate amount doesn't exceed payment applied amount
                if (bccomp($amount, $invoicePayment->amount_applied, 2) > 0) {
                    Notification::make()
                        ->title('Invalid Amount')
                        ->body("Refund amount cannot exceed the payment applied amount of {$invoice->currency} {$invoicePayment->amount_applied}.")
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    // Create the refund
                    $refund = Refund::create([
                        'invoice_id' => $invoice->id,
                        'payment_id' => $payment->id,
                        'refund_type' => $data['refund_type'],
                        'method' => $data['method'],
                        'amount' => $amount,
                        'currency' => $invoice->currency,
                        'status' => RefundStatus::Pending,
                        'reason' => $data['reason'],
                    ]);

                    // Log the creation in the audit trail
                    $refund->auditLogs()->create([
                        'event' => AuditLog::EVENT_CREATED,
                        'old_values' => [],
                        'new_values' => [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'payment_id' => $payment->id,
                            'payment_reference' => $payment->payment_reference,
                            'amount' => $amount,
                            'currency' => $invoice->currency,
                            'refund_type' => $data['refund_type'],
                            'method' => $data['method'],
                            'reason' => $data['reason'],
                        ],
                        'user_id' => auth()->id(),
                    ]);

                    $methodLabel = RefundMethod::from($data['method'])->label();

                    Notification::make()
                        ->title('Refund Created')
                        ->body("Refund for {$invoice->currency} {$amount} has been created with status Pending. Method: {$methodLabel}.")
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('View Refund')
                                ->url(route('filament.admin.resources.finance.refunds.view', ['record' => $refund->id])),
                        ])
                        ->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot Create Refund')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Cancel action - visible only if status = draft.
     */
    protected function getCancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel Invoice')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (): bool => $this->getInvoice()->canBeCancelled())
            ->requiresConfirmation()
            ->modalHeading('Cancel Invoice')
            ->modalDescription(function (): string {
                $invoice = $this->getInvoice();

                return "Are you sure you want to cancel this draft invoice?\n\n".
                    "Invoice Total: {$invoice->currency} {$invoice->total_amount}\n".
                    'Customer: '.($invoice->customer !== null ? $invoice->customer->name : 'Unknown')."\n\n".
                    'This action cannot be undone.';
            })
            ->modalSubmitActionLabel('Cancel Invoice')
            ->action(function (): void {
                $invoiceService = app(InvoiceService::class);

                try {
                    $invoiceService->cancel($this->getInvoice());

                    Notification::make()
                        ->title('Invoice Cancelled')
                        ->body('The invoice has been cancelled successfully.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot Cancel Invoice')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Get the current invoice record.
     */
    protected function getInvoice(): Invoice
    {
        /** @var Invoice $record */
        $record = $this->record;

        return $record;
    }
}
