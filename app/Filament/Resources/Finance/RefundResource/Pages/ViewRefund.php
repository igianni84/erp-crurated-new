<?php

namespace App\Filament\Resources\Finance\RefundResource\Pages;

use App\Enums\Finance\RefundMethod;
use App\Enums\Finance\RefundStatus;
use App\Enums\Finance\RefundType;
use App\Filament\Resources\Finance\RefundResource;
use App\Models\AuditLog;
use App\Models\Finance\Refund;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
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

class ViewRefund extends ViewRecord
{
    protected static string $resource = RefundResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Refund $record */
        $record = $this->record;

        return 'Refund #'.$record->id;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getHeaderSection(),
                Tabs::make('Refund Details')
                    ->tabs([
                        $this->getLinkedInvoiceTab(),
                        $this->getLinkedPaymentTab(),
                        $this->getLinkedCreditNoteTab(),
                        $this->getProcessingTab(),
                        $this->getReasonTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Header section with refund_id, amount, method, status.
     */
    protected function getHeaderSection(): Section
    {
        return Section::make('Refund Overview')
            ->schema([
                Grid::make(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('id')
                                ->label('Refund ID')
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large)
                                ->prefix('#'),
                            TextEntry::make('uuid')
                                ->label('UUID')
                                ->copyable()
                                ->copyMessage('UUID copied')
                                ->color('gray'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('amount')
                                ->label('Refund Amount')
                                ->money(fn (Refund $record): string => $record->currency)
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large)
                                ->color('danger'),
                            TextEntry::make('currency')
                                ->label('Currency')
                                ->badge()
                                ->color('gray'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('method')
                                ->label('Method')
                                ->badge()
                                ->formatStateUsing(fn (RefundMethod $state): string => $state->label())
                                ->color(fn (RefundMethod $state): string => $state->color())
                                ->icon(fn (RefundMethod $state): string => $state->icon()),
                            TextEntry::make('refund_type')
                                ->label('Type')
                                ->badge()
                                ->formatStateUsing(fn (RefundType $state): string => $state->label())
                                ->color(fn (RefundType $state): string => $state->color())
                                ->icon(fn (RefundType $state): string => $state->icon()),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn (RefundStatus $state): string => $state->label())
                                ->color(fn (RefundStatus $state): string => $state->color())
                                ->icon(fn (RefundStatus $state): string => $state->icon()),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime('M j, Y H:i'),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    /**
     * Tab 1: Linked Invoice - link to invoice.
     */
    protected function getLinkedInvoiceTab(): Tab
    {
        return Tab::make('Linked Invoice')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Invoice Details')
                    ->description('The invoice this refund is associated with')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('invoice.invoice_number')
                                    ->label('Invoice Number')
                                    ->weight(FontWeight::Bold)
                                    ->url(fn (Refund $record): ?string => $record->invoice !== null
                                        ? route('filament.admin.resources.finance.invoices.view', ['record' => $record->invoice])
                                        : null)
                                    ->color('primary')
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.invoice_type')
                                    ->label('Invoice Type')
                                    ->badge()
                                    ->formatStateUsing(fn (Refund $record): string => $record->invoice !== null
                                        ? $record->invoice->invoice_type->code().' - '.$record->invoice->invoice_type->label()
                                        : 'N/A')
                                    ->color(fn (Refund $record): string => $record->invoice !== null
                                        ? $record->invoice->invoice_type->color()
                                        : 'gray')
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.status')
                                    ->label('Invoice Status')
                                    ->badge()
                                    ->formatStateUsing(fn (Refund $record): string => $record->invoice !== null
                                        ? $record->invoice->status->label()
                                        : 'N/A')
                                    ->color(fn (Refund $record): string => $record->invoice !== null
                                        ? $record->invoice->status->color()
                                        : 'gray')
                                    ->placeholder('N/A'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('invoice.subtotal')
                                    ->label('Invoice Subtotal')
                                    ->money(fn (Refund $record): string => $record->invoice !== null
                                        ? $record->invoice->currency
                                        : $record->currency)
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.tax_amount')
                                    ->label('Invoice Tax')
                                    ->money(fn (Refund $record): string => $record->invoice !== null
                                        ? $record->invoice->currency
                                        : $record->currency)
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.total_amount')
                                    ->label('Invoice Total')
                                    ->money(fn (Refund $record): string => $record->invoice !== null
                                        ? $record->invoice->currency
                                        : $record->currency)
                                    ->weight(FontWeight::Bold)
                                    ->placeholder('N/A'),
                                TextEntry::make('refund_percentage')
                                    ->label('Refund Percentage')
                                    ->getStateUsing(fn (Refund $record): string => $this->calculateRefundPercentage($record))
                                    ->badge()
                                    ->color(fn (Refund $record): string => $this->getRefundPercentageColor($record)),
                            ]),
                    ]),
                Section::make('Invoice Customer')
                    ->description('Customer associated with the invoice')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('invoice.customer.name')
                                    ->label('Customer Name')
                                    ->url(fn (Refund $record): ?string => $record->invoice !== null && $record->invoice->customer !== null
                                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->invoice->customer])
                                        : null)
                                    ->color('primary')
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.customer.email')
                                    ->label('Customer Email')
                                    ->copyable()
                                    ->copyMessage('Email copied')
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.issued_at')
                                    ->label('Invoice Issued At')
                                    ->dateTime()
                                    ->placeholder('N/A'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Linked Payment - link to payment.
     */
    protected function getLinkedPaymentTab(): Tab
    {
        return Tab::make('Linked Payment')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Section::make('Payment Details')
                    ->description('The payment being refunded')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('payment.payment_reference')
                                    ->label('Payment Reference')
                                    ->weight(FontWeight::Bold)
                                    ->url(fn (Refund $record): ?string => $record->payment !== null
                                        ? route('filament.admin.resources.finance.payments.view', ['record' => $record->payment])
                                        : null)
                                    ->color('primary')
                                    ->copyable()
                                    ->copyMessage('Payment reference copied')
                                    ->placeholder('N/A'),
                                TextEntry::make('payment.source')
                                    ->label('Payment Source')
                                    ->badge()
                                    ->formatStateUsing(fn (Refund $record): string => $record->payment !== null
                                        ? $record->payment->source->label()
                                        : 'N/A')
                                    ->color(fn (Refund $record): string => $record->payment !== null
                                        ? $record->payment->source->color()
                                        : 'gray')
                                    ->placeholder('N/A'),
                                TextEntry::make('payment.status')
                                    ->label('Payment Status')
                                    ->badge()
                                    ->formatStateUsing(fn (Refund $record): string => $record->payment !== null
                                        ? $record->payment->status->label()
                                        : 'N/A')
                                    ->color(fn (Refund $record): string => $record->payment !== null
                                        ? $record->payment->status->color()
                                        : 'gray')
                                    ->placeholder('N/A'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('payment.amount')
                                    ->label('Payment Amount')
                                    ->money(fn (Refund $record): string => $record->payment !== null
                                        ? $record->payment->currency
                                        : $record->currency)
                                    ->weight(FontWeight::Bold)
                                    ->placeholder('N/A'),
                                TextEntry::make('amount')
                                    ->label('Refund Amount')
                                    ->money(fn (Refund $record): string => $record->currency)
                                    ->weight(FontWeight::Bold)
                                    ->color('danger'),
                                TextEntry::make('refund_vs_payment')
                                    ->label('% of Payment')
                                    ->getStateUsing(fn (Refund $record): string => $this->calculateRefundVsPayment($record))
                                    ->badge()
                                    ->color(fn (Refund $record): string => $this->getRefundVsPaymentColor($record)),
                                TextEntry::make('payment.received_at')
                                    ->label('Payment Received')
                                    ->dateTime()
                                    ->placeholder('N/A'),
                            ]),
                    ]),
                Section::make('Payment Stripe Details')
                    ->description('Stripe-specific payment information')
                    ->collapsed()
                    ->visible(fn (Refund $record): bool => $record->payment !== null && $record->payment->stripe_payment_intent_id !== null)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('payment.stripe_payment_intent_id')
                                    ->label('Stripe Payment Intent ID')
                                    ->copyable()
                                    ->copyMessage('Payment Intent ID copied')
                                    ->placeholder('N/A'),
                                TextEntry::make('payment.stripe_charge_id')
                                    ->label('Stripe Charge ID')
                                    ->copyable()
                                    ->copyMessage('Charge ID copied')
                                    ->placeholder('N/A'),
                            ]),
                    ]),
                Section::make('Payment Bank Details')
                    ->description('Bank transfer payment information')
                    ->collapsed()
                    ->visible(fn (Refund $record): bool => $record->payment !== null && $record->payment->bank_reference !== null)
                    ->schema([
                        TextEntry::make('payment.bank_reference')
                            ->label('Bank Reference')
                            ->copyable()
                            ->copyMessage('Bank reference copied')
                            ->placeholder('N/A'),
                    ]),
            ]);
    }

    /**
     * Tab 3: Linked Credit Note - link if present.
     */
    protected function getLinkedCreditNoteTab(): Tab
    {
        /** @var Refund $record */
        $record = $this->record;
        $hasCreditNote = $record->credit_note_id !== null;

        return Tab::make('Linked Credit Note')
            ->icon('heroicon-o-document-minus')
            ->badge($hasCreditNote ? '1' : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Credit Note Details')
                    ->description($hasCreditNote
                        ? 'The credit note associated with this refund'
                        : 'This refund is not linked to a credit note')
                    ->schema($hasCreditNote
                        ? [
                            Grid::make(3)
                                ->schema([
                                    TextEntry::make('creditNote.credit_note_number')
                                        ->label('Credit Note Number')
                                        ->weight(FontWeight::Bold)
                                        ->url(fn (Refund $record): ?string => $record->creditNote !== null
                                            ? route('filament.admin.resources.finance.credit-notes.view', ['record' => $record->creditNote])
                                            : null)
                                        ->color('primary')
                                        ->placeholder('Draft'),
                                    TextEntry::make('creditNote.status')
                                        ->label('Credit Note Status')
                                        ->badge()
                                        ->formatStateUsing(fn (Refund $record): string => $record->creditNote !== null
                                            ? $record->creditNote->status->label()
                                            : 'N/A')
                                        ->color(fn (Refund $record): string => $record->creditNote !== null
                                            ? $record->creditNote->status->color()
                                            : 'gray')
                                        ->placeholder('N/A'),
                                    TextEntry::make('creditNote.amount')
                                        ->label('Credit Note Amount')
                                        ->money(fn (Refund $record): string => $record->creditNote !== null
                                            ? $record->creditNote->currency
                                            : $record->currency)
                                        ->weight(FontWeight::Bold)
                                        ->placeholder('N/A'),
                                ]),
                            Grid::make(3)
                                ->schema([
                                    TextEntry::make('creditNote.reason')
                                        ->label('Credit Note Reason')
                                        ->limit(100)
                                        ->tooltip(fn (Refund $record): ?string => $record->creditNote !== null
                                            ? $record->creditNote->reason
                                            : null)
                                        ->placeholder('N/A'),
                                    TextEntry::make('creditNote.issued_at')
                                        ->label('Credit Note Issued At')
                                        ->dateTime()
                                        ->placeholder('Not issued'),
                                    TextEntry::make('creditNote.issuedByUser.name')
                                        ->label('Issued By')
                                        ->placeholder('N/A'),
                                ]),
                        ]
                        : [
                            TextEntry::make('no_credit_note')
                                ->label('')
                                ->getStateUsing(fn (): string => 'This refund is not associated with a credit note. Credit notes are optional and may be created separately to document the reason for the refund.')
                                ->color('gray'),
                        ]),
            ]);
    }

    /**
     * Tab 4: Processing - stripe_refund_id or bank_reference, processed_at.
     */
    protected function getProcessingTab(): Tab
    {
        return Tab::make('Processing')
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Section::make('Processing Status')
                    ->description('Current processing status of this refund')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (RefundStatus $state): string => $state->label())
                                    ->color(fn (RefundStatus $state): string => $state->color())
                                    ->icon(fn (RefundStatus $state): string => $state->icon()),
                                TextEntry::make('processed_at')
                                    ->label('Processed At')
                                    ->dateTime()
                                    ->placeholder('Not yet processed'),
                                TextEntry::make('processedByUser.name')
                                    ->label('Processed By')
                                    ->placeholder('N/A'),
                            ]),
                    ]),
                Section::make('Stripe Refund Details')
                    ->description('Stripe-specific refund information')
                    ->visible(fn (Refund $record): bool => $record->isStripeRefund())
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('stripe_refund_id')
                                    ->label('Stripe Refund ID')
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->copyMessage('Stripe Refund ID copied')
                                    ->placeholder('Not yet processed - Pending Stripe refund')
                                    ->helperText('The unique identifier for this refund in Stripe'),
                                TextEntry::make('stripe_processing_status')
                                    ->label('Stripe Processing Status')
                                    ->getStateUsing(fn (Refund $record): string => $this->getStripeProcessingStatus($record))
                                    ->badge()
                                    ->color(fn (Refund $record): string => $this->getStripeProcessingStatusColor($record)),
                            ]),
                        TextEntry::make('stripe_refund_link')
                            ->label('Stripe Dashboard')
                            ->getStateUsing(fn (Refund $record): string => $record->stripe_refund_id !== null
                                ? 'View in Stripe Dashboard →'
                                : 'Not available')
                            ->url(fn (Refund $record): ?string => $record->stripe_refund_id !== null
                                ? 'https://dashboard.stripe.com/refunds/'.$record->stripe_refund_id
                                : null)
                            ->openUrlInNewTab()
                            ->color('primary')
                            ->visible(fn (Refund $record): bool => $record->stripe_refund_id !== null),
                    ]),
                Section::make('Bank Transfer Details')
                    ->description('Bank transfer refund information')
                    ->visible(fn (Refund $record): bool => $record->isBankTransferRefund())
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('bank_reference')
                                    ->label('Bank Reference')
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->copyMessage('Bank reference copied')
                                    ->placeholder('Not yet processed - Add bank reference when transfer is complete')
                                    ->helperText('The reference number for the bank transfer'),
                                TextEntry::make('bank_processing_status')
                                    ->label('Bank Processing Status')
                                    ->getStateUsing(fn (Refund $record): string => $this->getBankProcessingStatus($record))
                                    ->badge()
                                    ->color(fn (Refund $record): string => $this->getBankProcessingStatusColor($record)),
                            ]),
                    ]),
                Section::make('Method Details')
                    ->description('Refund method information')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('method')
                                    ->label('Refund Method')
                                    ->badge()
                                    ->formatStateUsing(fn (RefundMethod $state): string => $state->label())
                                    ->color(fn (RefundMethod $state): string => $state->color())
                                    ->icon(fn (RefundMethod $state): string => $state->icon()),
                                TextEntry::make('refund_type')
                                    ->label('Refund Type')
                                    ->badge()
                                    ->formatStateUsing(fn (RefundType $state): string => $state->label())
                                    ->color(fn (RefundType $state): string => $state->color())
                                    ->icon(fn (RefundType $state): string => $state->icon()),
                                TextEntry::make('auto_process_status')
                                    ->label('Auto-Process Support')
                                    ->getStateUsing(fn (Refund $record): string => $record->supportsAutoProcess()
                                        ? 'Supports automatic processing'
                                        : 'Requires manual tracking')
                                    ->badge()
                                    ->color(fn (Refund $record): string => $record->supportsAutoProcess()
                                        ? 'success'
                                        : 'warning'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 5: Reason - full reason text.
     */
    protected function getReasonTab(): Tab
    {
        return Tab::make('Reason')
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->schema([
                Section::make('Refund Reason')
                    ->description('The reason provided for this refund')
                    ->schema([
                        TextEntry::make('reason')
                            ->label('')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
                Section::make('Operational Warning')
                    ->description('Important information about refund operations')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('operational_warning')
                            ->label('')
                            ->getStateUsing(fn (): string => '**Note:** Refunding does not automatically reverse operational effects (e.g., voucher cancellation, shipment reversal). Financial transactions and operational actions are handled separately. If operational reversal is needed, please coordinate with the Operations team.')
                            ->markdown()
                            ->color('warning')
                            ->columnSpanFull(),
                    ]),
                Section::make('Creation Details')
                    ->description('When and how this refund was created')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                TextEntry::make('processed_at')
                                    ->label('Processed At')
                                    ->dateTime()
                                    ->placeholder('Not yet processed'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 6: Audit - timeline.
     */
    protected function getAuditTab(): Tab
    {
        /** @var Refund $record */
        $record = $this->record;
        $auditCount = $record->auditLogs()->count();

        return Tab::make('Audit')
            ->icon('heroicon-o-clock')
            ->badge($auditCount > 0 ? (string) $auditCount : null)
            ->badgeColor('gray')
            ->schema([
                Section::make('Audit Timeline')
                    ->description('Immutable record of all changes to this refund')
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

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get the current refund record.
     */
    protected function getRefund(): Refund
    {
        /** @var Refund $record */
        $record = $this->record;

        return $record;
    }

    /**
     * Calculate the refund percentage relative to invoice total.
     */
    protected function calculateRefundPercentage(Refund $record): string
    {
        if ($record->invoice === null) {
            return 'N/A';
        }

        $invoiceTotal = (float) $record->invoice->total_amount;

        if ($invoiceTotal <= 0) {
            return 'N/A';
        }

        $refundAmount = (float) $record->amount;
        $percentage = ($refundAmount / $invoiceTotal) * 100;

        return number_format($percentage, 1).'%';
    }

    /**
     * Get color for refund percentage badge.
     */
    protected function getRefundPercentageColor(Refund $record): string
    {
        if ($record->invoice === null) {
            return 'gray';
        }

        $invoiceTotal = (float) $record->invoice->total_amount;

        if ($invoiceTotal <= 0) {
            return 'gray';
        }

        $refundAmount = (float) $record->amount;
        $percentage = ($refundAmount / $invoiceTotal) * 100;

        if ($percentage >= 100) {
            return 'danger';
        }
        if ($percentage >= 50) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Calculate refund vs payment percentage.
     */
    protected function calculateRefundVsPayment(Refund $record): string
    {
        if ($record->payment === null) {
            return 'N/A';
        }

        $paymentAmount = (float) $record->payment->amount;

        if ($paymentAmount <= 0) {
            return 'N/A';
        }

        $refundAmount = (float) $record->amount;
        $percentage = ($refundAmount / $paymentAmount) * 100;

        return number_format($percentage, 1).'%';
    }

    /**
     * Get color for refund vs payment percentage.
     */
    protected function getRefundVsPaymentColor(Refund $record): string
    {
        if ($record->payment === null) {
            return 'gray';
        }

        $paymentAmount = (float) $record->payment->amount;

        if ($paymentAmount <= 0) {
            return 'gray';
        }

        $refundAmount = (float) $record->amount;
        $percentage = ($refundAmount / $paymentAmount) * 100;

        if ($percentage >= 100) {
            return 'danger';
        }
        if ($percentage >= 50) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Get Stripe processing status.
     */
    protected function getStripeProcessingStatus(Refund $record): string
    {
        if ($record->stripe_refund_id !== null && $record->isProcessed()) {
            return 'Processed';
        }

        if ($record->stripe_refund_id !== null && $record->isPending()) {
            return 'Pending Confirmation';
        }

        if ($record->isFailed()) {
            return 'Failed';
        }

        if ($record->canProcessStripeRefund()) {
            return 'Ready to Process';
        }

        return 'Pending';
    }

    /**
     * Get Stripe processing status color.
     */
    protected function getStripeProcessingStatusColor(Refund $record): string
    {
        if ($record->stripe_refund_id !== null && $record->isProcessed()) {
            return 'success';
        }

        if ($record->isFailed()) {
            return 'danger';
        }

        if ($record->canProcessStripeRefund()) {
            return 'info';
        }

        return 'warning';
    }

    /**
     * Get bank processing status.
     */
    protected function getBankProcessingStatus(Refund $record): string
    {
        if ($record->bank_reference !== null && $record->isProcessed()) {
            return 'Completed';
        }

        if ($record->isFailed()) {
            return 'Failed';
        }

        if ($record->canMarkBankRefundProcessed()) {
            return 'Awaiting Bank Reference';
        }

        return 'Pending';
    }

    /**
     * Get bank processing status color.
     */
    protected function getBankProcessingStatusColor(Refund $record): string
    {
        if ($record->bank_reference !== null && $record->isProcessed()) {
            return 'success';
        }

        if ($record->isFailed()) {
            return 'danger';
        }

        return 'warning';
    }

    /**
     * Format audit log changes for display.
     */
    protected function formatAuditChanges(AuditLog $log): string
    {
        $changes = [];

        if ($log->event === AuditLog::EVENT_CREATED) {
            return '<span class="text-success-600">Refund created</span>';
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
            $this->getMarkBankRefundProcessedAction(),
        ];
    }

    /**
     * Mark Bank Refund Processed action - visible for bank transfer refunds in pending status.
     *
     * US-E072: Bank refund tracking
     * - If method = bank_transfer: Form requires bank_reference (post-processing)
     * - Status flow: pending to processed (after reference input)
     * - Action "Mark Processed" with bank_reference input
     */
    protected function getMarkBankRefundProcessedAction(): Action
    {
        return Action::make('markProcessed')
            ->label('Mark Processed')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (): bool => $this->getRefund()->canMarkBankRefundProcessed())
            ->form([
                Placeholder::make('info')
                    ->label('')
                    ->content(new \Illuminate\Support\HtmlString(
                        '<div class="p-4 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-700">'.
                        '<div class="flex items-start gap-3">'.
                        '<svg class="w-6 h-6 text-info-600 dark:text-info-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">'.
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'.
                        '</svg>'.
                        '<div>'.
                        '<h4 class="font-semibold text-info-800 dark:text-info-200">Bank Transfer Refund</h4>'.
                        '<p class="text-sm text-info-700 dark:text-info-300 mt-1">'.
                        'Enter the bank reference after you have completed the bank transfer to mark this refund as processed.'.
                        '</p>'.
                        '</div>'.
                        '</div>'.
                        '</div>'
                    )),
                Placeholder::make('refund_details')
                    ->label('Refund Details')
                    ->content(function (): string {
                        $refund = $this->getRefund();

                        return "Refund ID: #{$refund->id}\n".
                            "Amount: {$refund->currency} {$refund->amount}\n".
                            'Invoice: '.($refund->invoice !== null ? $refund->invoice->invoice_number : 'N/A')."\n".
                            'Customer: '.($refund->invoice !== null && $refund->invoice->customer !== null ? $refund->invoice->customer->name : 'N/A');
                    }),
                TextInput::make('bank_reference')
                    ->label('Bank Reference')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Enter the bank transfer reference number...')
                    ->helperText('The reference number from the bank transfer confirmation')
                    ->rules(['required', 'string', 'min:3', 'max:255']),
                DateTimePicker::make('processed_at')
                    ->label('Processed At')
                    ->required()
                    ->default(now())
                    ->maxDate(now())
                    ->helperText('The date and time when the bank transfer was completed. Defaults to now.'),
            ])
            ->requiresConfirmation()
            ->modalHeading('Mark Refund as Processed')
            ->modalDescription(function (): string {
                $refund = $this->getRefund();

                return "Mark this bank transfer refund of {$refund->currency} {$refund->amount} as processed?\n\n".
                    'This will update the status from Pending to Processed.';
            })
            ->modalSubmitActionLabel('Mark as Processed')
            ->action(function (array $data): void {
                $refund = $this->getRefund();

                // Validate refund can be marked as processed
                if (! $refund->canMarkBankRefundProcessed()) {
                    Notification::make()
                        ->title('Cannot Mark as Processed')
                        ->body('This refund cannot be marked as processed. It may already be processed or is not a bank transfer refund.')
                        ->danger()
                        ->send();

                    return;
                }

                $oldStatus = $refund->status->value;

                // Update the refund
                $refund->update([
                    'bank_reference' => $data['bank_reference'],
                    'status' => RefundStatus::Processed,
                    'processed_at' => $data['processed_at'],
                    'processed_by' => auth()->id(),
                ]);

                // Log the status change in the audit trail
                $refund->auditLogs()->create([
                    'event' => AuditLog::EVENT_UPDATED,
                    'old_values' => [
                        'status' => $oldStatus,
                        'bank_reference' => null,
                        'processed_at' => null,
                        'processed_by' => null,
                    ],
                    'new_values' => [
                        'status' => RefundStatus::Processed->value,
                        'bank_reference' => $data['bank_reference'],
                        'processed_at' => $data['processed_at'],
                        'processed_by' => auth()->id(),
                    ],
                    'user_id' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Refund Marked as Processed')
                    ->body("Bank transfer refund has been marked as processed with reference: {$data['bank_reference']}")
                    ->success()
                    ->send();

                // Refresh the page to show updated status
                $this->redirect(RefundResource::getUrl('view', ['record' => $refund]));
            });
    }
}
