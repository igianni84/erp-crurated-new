<?php

namespace App\Filament\Resources\Finance\InvoiceResource\Pages;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\PaymentSource;
use App\Filament\Resources\Finance\InvoiceResource;
use App\Models\AuditLog;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use App\Models\Finance\InvoicePayment;
use App\Services\Finance\InvoiceMailService;
use App\Services\Finance\InvoicePdfService;
use App\Services\Finance\InvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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
                                    ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
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
                                TextEntry::make('source_id')
                                    ->label('Source ID')
                                    ->placeholder('N/A')
                                    ->copyable()
                                    ->copyMessage('Source ID copied'),
                            ]),
                    ]),
                Section::make('Event Details')
                    ->description(fn (Invoice $record): string => $this->getSourceDescription($record))
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
                    return $pdfService->download($this->getInvoice());
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
            ])
            ->requiresConfirmation()
            ->modalHeading('Record Bank Payment')
            ->modalDescription('Record a bank transfer payment for this invoice.')
            ->modalSubmitActionLabel('Record Payment')
            ->action(function (array $data): void {
                // This action will be fully implemented in US-E056 with PaymentService
                // For now, create a simple placeholder that shows the pattern
                Notification::make()
                    ->title('Bank Payment Recorded')
                    ->body("Payment of {$this->getInvoice()->currency} {$data['amount']} with reference {$data['bank_reference']} will be recorded. Full implementation in US-E056.")
                    ->warning()
                    ->send();
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
                // This action will be fully implemented in US-E065 with CreditNoteService
                // For now, create a simple placeholder that shows the pattern
                Notification::make()
                    ->title('Credit Note Created')
                    ->body("Credit note for {$this->getInvoice()->currency} {$data['amount']} will be created as draft. Full implementation in US-E065.")
                    ->warning()
                    ->send();
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
