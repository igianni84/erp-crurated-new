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
            ]);
    }

    /**
     * Tab 4: Accounting - Xero sync info, statutory invoice number, GL posting, FX rate.
     */
    protected function getAccountingTab(): Tab
    {
        return Tab::make('Accounting')
            ->icon('heroicon-o-calculator')
            ->schema([
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
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('invoice_number')
                                    ->label('Statutory Invoice Number')
                                    ->placeholder('Not issued')
                                    ->helperText('Sequential invoice number generated at issuance'),
                                TextEntry::make('currency')
                                    ->label('Invoice Currency')
                                    ->badge()
                                    ->color('gray'),
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
                    ->visible(fn (Invoice $record): bool => $record->currency !== 'EUR')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('currency')
                                    ->label('Invoice Currency'),
                                TextEntry::make('fx_rate')
                                    ->label('FX Rate at Issuance')
                                    ->getStateUsing(fn (): string => 'Rate snapshot not yet implemented')
                                    ->helperText('Exchange rate to EUR at time of issuance'),
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
            $this->getIssueAction(),
            $this->getRecordBankPaymentAction(),
            $this->getCreateCreditNoteAction(),
            $this->getCancelAction(),
        ];
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
