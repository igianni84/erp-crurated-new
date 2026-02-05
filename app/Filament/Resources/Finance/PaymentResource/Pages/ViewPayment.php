<?php

namespace App\Filament\Resources\Finance\PaymentResource\Pages;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Filament\Resources\Finance\PaymentResource;
use App\Models\AuditLog;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Payment $record */
        $record = $this->record;

        return 'Payment: '.$record->payment_reference;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getHeaderSection(),
                $this->getSourceDetailsSection(),
                $this->getAppliedInvoicesSection(),
                $this->getReconciliationSection(),
                $this->getMetadataSection(),
                $this->getAuditSection(),
            ]);
    }

    /**
     * Header section with payment_reference, source, amount, status.
     */
    protected function getHeaderSection(): Section
    {
        return Section::make('Payment Overview')
            ->schema([
                Grid::make(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('payment_reference')
                                ->label('Payment Reference')
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large)
                                ->copyable()
                                ->copyMessage('Payment reference copied'),
                            TextEntry::make('source')
                                ->label('Payment Source')
                                ->badge()
                                ->formatStateUsing(fn (PaymentSource $state): string => $state->label())
                                ->color(fn (PaymentSource $state): string => $state->color())
                                ->icon(fn (PaymentSource $state): string => $state->icon()),
                            TextEntry::make('customer.name')
                                ->label('Customer')
                                ->url(fn (Payment $record): ?string => $record->customer !== null
                                    ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
                                    : null)
                                ->color('primary')
                                ->placeholder('Unassigned'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('status')
                                ->label('Payment Status')
                                ->badge()
                                ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                                ->color(fn (PaymentStatus $state): string => $state->color())
                                ->icon(fn (PaymentStatus $state): string => $state->icon()),
                            TextEntry::make('reconciliation_status')
                                ->label('Reconciliation Status')
                                ->badge()
                                ->formatStateUsing(fn (ReconciliationStatus $state): string => $state->label())
                                ->color(fn (ReconciliationStatus $state): string => $state->color())
                                ->icon(fn (ReconciliationStatus $state): string => $state->icon()),
                            TextEntry::make('received_at')
                                ->label('Date Received')
                                ->dateTime(),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('amount')
                                ->label('Payment Amount')
                                ->money(fn (Payment $record): string => $record->currency)
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large),
                            TextEntry::make('currency')
                                ->label('Currency')
                                ->badge()
                                ->color('gray'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('total_applied')
                                ->label('Amount Applied')
                                ->getStateUsing(fn (Payment $record): string => $record->getTotalAppliedAmount())
                                ->money(fn (Payment $record): string => $record->currency)
                                ->color('success'),
                            TextEntry::make('unapplied')
                                ->label('Unapplied Amount')
                                ->getStateUsing(fn (Payment $record): string => $record->getUnappliedAmount())
                                ->money(fn (Payment $record): string => $record->currency)
                                ->color(fn (Payment $record): string => bccomp($record->getUnappliedAmount(), '0', 2) > 0 ? 'warning' : 'gray'),
                            TextEntry::make('application_status')
                                ->label('Application Status')
                                ->getStateUsing(fn (Payment $record): string => $record->isFullyApplied() ? 'Fully Applied' : 'Partially Applied')
                                ->badge()
                                ->color(fn (Payment $record): string => $record->isFullyApplied() ? 'success' : 'warning'),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    /**
     * Section 1 - Source Details: Stripe IDs OR bank reference.
     */
    protected function getSourceDetailsSection(): Section
    {
        /** @var Payment $record */
        $record = $this->record;

        if ($record->isFromStripe()) {
            return $this->getStripeSourceSection();
        }

        return $this->getBankSourceSection();
    }

    /**
     * Stripe source details section.
     */
    protected function getStripeSourceSection(): Section
    {
        return Section::make('Stripe Payment Details')
            ->description('Payment received via Stripe')
            ->icon('heroicon-o-credit-card')
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('stripe_payment_intent_id')
                            ->label('Payment Intent ID')
                            ->copyable()
                            ->copyMessage('Payment Intent ID copied')
                            ->placeholder('Not available')
                            ->helperText('Stripe PaymentIntent identifier'),
                        TextEntry::make('stripe_charge_id')
                            ->label('Charge ID')
                            ->copyable()
                            ->copyMessage('Charge ID copied')
                            ->placeholder('Not available')
                            ->helperText('Stripe Charge identifier'),
                        TextEntry::make('stripe_link')
                            ->label('View in Stripe')
                            ->getStateUsing(fn (Payment $record): string => $record->stripe_payment_intent_id !== null ? 'Open Stripe Dashboard' : 'N/A')
                            ->url(fn (Payment $record): ?string => $record->stripe_payment_intent_id !== null
                                ? 'https://dashboard.stripe.com/payments/'.$record->stripe_payment_intent_id
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (Payment $record): bool => $record->stripe_payment_intent_id !== null)
                            ->color('primary')
                            ->icon('heroicon-o-arrow-top-right-on-square'),
                    ]),
            ]);
    }

    /**
     * Bank transfer source details section.
     */
    protected function getBankSourceSection(): Section
    {
        return Section::make('Bank Transfer Details')
            ->description('Payment received via bank transfer')
            ->icon('heroicon-o-building-library')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('bank_reference')
                            ->label('Bank Reference')
                            ->copyable()
                            ->copyMessage('Bank reference copied')
                            ->placeholder('Not recorded')
                            ->helperText('Bank transaction reference number'),
                        TextEntry::make('received_at')
                            ->label('Date Received')
                            ->dateTime()
                            ->helperText('Date the bank payment was received'),
                    ]),
            ]);
    }

    /**
     * Section 2 - Applied Invoices: list of InvoicePayments.
     */
    protected function getAppliedInvoicesSection(): Section
    {
        /** @var Payment $record */
        $record = $this->record;
        $applicationsCount = $record->invoicePayments()->count();
        $countLabel = $applicationsCount > 0 ? " ({$applicationsCount})" : '';

        return Section::make('Applied Invoices'.$countLabel)
            ->description('Invoices this payment has been applied to')
            ->icon('heroicon-o-document-text')
            ->schema([
                // Summary
                Grid::make(3)
                    ->schema([
                        TextEntry::make('payment_amount')
                            ->label('Payment Amount')
                            ->getStateUsing(fn (Payment $record): string => $record->amount)
                            ->money(fn (Payment $record): string => $record->currency)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('total_applied_amount')
                            ->label('Total Applied')
                            ->getStateUsing(fn (Payment $record): string => $record->getTotalAppliedAmount())
                            ->money(fn (Payment $record): string => $record->currency)
                            ->color('success')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('remaining_amount')
                            ->label('Remaining')
                            ->getStateUsing(fn (Payment $record): string => $record->getUnappliedAmount())
                            ->money(fn (Payment $record): string => $record->currency)
                            ->color(fn (Payment $record): string => bccomp($record->getUnappliedAmount(), '0', 2) > 0 ? 'warning' : 'success')
                            ->weight(FontWeight::Bold),
                    ]),

                // Applications list
                Section::make('Payment Applications')
                    ->schema([
                        RepeatableEntry::make('invoicePayments')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('invoice.invoice_number')
                                            ->label('Invoice')
                                            ->weight(FontWeight::Bold)
                                            ->url(fn (InvoicePayment $invoicePayment): ?string => $invoicePayment->invoice !== null
                                                ? route('filament.admin.resources.finance.invoices.view', ['record' => $invoicePayment->invoice])
                                                : null)
                                            ->color('primary')
                                            ->placeholder('Draft Invoice'),
                                        TextEntry::make('invoice.status')
                                            ->label('Invoice Status')
                                            ->badge()
                                            ->formatStateUsing(fn (?InvoiceStatus $state): string => $state !== null ? $state->label() : 'N/A')
                                            ->color(fn (?InvoiceStatus $state): string => $state !== null ? $state->color() : 'gray')
                                            ->icon(fn (?InvoiceStatus $state): string => $state !== null ? $state->icon() : 'heroicon-o-question-mark-circle'),
                                        TextEntry::make('amount_applied')
                                            ->label('Amount Applied')
                                            ->money(fn (InvoicePayment $invoicePayment): string => $invoicePayment->invoice !== null ? $invoicePayment->invoice->currency : 'EUR')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('applied_at')
                                            ->label('Applied At')
                                            ->dateTime(),
                                        TextEntry::make('appliedByUser.name')
                                            ->label('Applied By')
                                            ->placeholder('System'),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No invoices have been associated with this payment yet'),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Section 3 - Reconciliation: status and mismatch details.
     */
    protected function getReconciliationSection(): Section
    {
        /** @var Payment $record */
        $record = $this->record;

        return Section::make('Reconciliation')
            ->description('Payment reconciliation status and details')
            ->icon('heroicon-o-scale')
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('reconciliation_status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (ReconciliationStatus $state): string => $state->label())
                            ->color(fn (ReconciliationStatus $state): string => $state->color())
                            ->icon(fn (ReconciliationStatus $state): string => $state->icon()),
                        TextEntry::make('requires_attention')
                            ->label('Requires Attention')
                            ->getStateUsing(fn (Payment $record): string => $record->requiresReconciliationAttention() ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (Payment $record): string => $record->requiresReconciliationAttention() ? 'danger' : 'success')
                            ->icon(fn (Payment $record): string => $record->requiresReconciliationAttention()
                                ? 'heroicon-o-exclamation-triangle'
                                : 'heroicon-o-check-circle'),
                        TextEntry::make('can_trigger_business')
                            ->label('Can Trigger Business Events')
                            ->getStateUsing(fn (Payment $record): string => $record->canTriggerBusinessEvents() ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (Payment $record): string => $record->canTriggerBusinessEvents() ? 'success' : 'gray')
                            ->helperText('Only matched and confirmed payments trigger downstream events'),
                    ]),

                // Mismatch details (only shown when mismatched)
                Section::make('Mismatch Details')
                    ->description('Information about the reconciliation mismatch')
                    ->visible(fn (Payment $record): bool => $record->hasMismatch())
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        TextEntry::make('mismatch_info')
                            ->label('')
                            ->getStateUsing(fn (Payment $record): string => $this->formatMismatchDetails($record))
                            ->html(),
                    ]),

                // Pending reconciliation info (only shown when pending)
                Section::make('Pending Reconciliation')
                    ->description('This payment is awaiting reconciliation')
                    ->visible(fn (Payment $record): bool => $record->isReconciliationPending())
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('pending_info')
                            ->label('')
                            ->getStateUsing(fn (Payment $record): string => $this->formatPendingReconciliationInfo($record))
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Format mismatch details for display.
     */
    protected function formatMismatchDetails(Payment $record): string
    {
        $mismatchReason = $record->getMismatchReason();
        $mismatchDetails = $record->getMismatchDetails();

        $lines = [];
        $lines[] = '<div class="space-y-4">';

        // Mismatch reason
        $lines[] = '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-red-800 dark:text-red-200">Mismatch Reason</h4>';
        $lines[] = '<p class="mt-1 text-red-700 dark:text-red-300">'.e($mismatchReason).'</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        // Details if available
        if (! empty($mismatchDetails)) {
            $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">';
            $lines[] = '<h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Additional Details</h4>';
            $lines[] = '<dl class="grid grid-cols-2 gap-2 text-sm">';

            foreach ($mismatchDetails as $key => $value) {
                $displayKey = ucfirst(str_replace('_', ' ', $key));
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                $lines[] = '<dt class="text-gray-600 dark:text-gray-400">'.e($displayKey).':</dt>';
                $lines[] = '<dd class="font-mono">'.e($displayValue).'</dd>';
            }

            $lines[] = '</dl>';
            $lines[] = '</div>';
        }

        // Resolution guidance
        $lines[] = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">';
        $lines[] = '<h4 class="font-semibold text-blue-800 dark:text-blue-200">Resolution Options</h4>';
        $lines[] = '<ul class="mt-2 space-y-1 text-sm text-blue-700 dark:text-blue-300">';
        $lines[] = '<li>• <strong>Force Match:</strong> Override the mismatch and apply the payment</li>';
        $lines[] = '<li>• <strong>Create Exception:</strong> Log this as an exception for review</li>';
        $lines[] = '<li>• <strong>Refund:</strong> Process a refund for this payment</li>';
        $lines[] = '</ul>';
        $lines[] = '<p class="mt-2 text-xs text-blue-600 dark:text-blue-400">Resolution actions will be available in US-E055.</p>';
        $lines[] = '</div>';

        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Format pending reconciliation info for display.
     */
    protected function formatPendingReconciliationInfo(Payment $record): string
    {
        $lines = [];
        $lines[] = '<div class="space-y-4">';

        // Pending status explanation
        $lines[] = '<div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-amber-800 dark:text-amber-200">Awaiting Reconciliation</h4>';
        $lines[] = '<p class="mt-1 text-amber-700 dark:text-amber-300">';
        $lines[] = 'This payment has not yet been matched to an invoice. ';

        if ($record->isFromStripe()) {
            $lines[] = 'Stripe payments are typically auto-reconciled when the payment amount matches an open invoice.';
        } else {
            $lines[] = 'Bank transfers require manual reconciliation by a finance operator.';
        }

        $lines[] = '</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        // Customer info if available
        if ($record->customer !== null) {
            $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">';
            $lines[] = '<h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Customer Information</h4>';
            $lines[] = '<p class="text-sm text-gray-600 dark:text-gray-400">';
            $lines[] = 'This payment is associated with customer: <strong>'.e($record->customer->name).'</strong>';
            $lines[] = '</p>';
            $lines[] = '<p class="mt-2 text-sm text-gray-600 dark:text-gray-400">';
            $lines[] = 'You can apply this payment to any of the customer\'s open invoices.';
            $lines[] = '</p>';
            $lines[] = '</div>';
        } else {
            $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">';
            $lines[] = '<h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">No Customer Assigned</h4>';
            $lines[] = '<p class="text-sm text-gray-600 dark:text-gray-400">';
            $lines[] = 'This payment is not yet linked to a customer. Assign a customer to enable invoice matching.';
            $lines[] = '</p>';
            $lines[] = '</div>';
        }

        // Next steps
        $lines[] = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">';
        $lines[] = '<h4 class="font-semibold text-blue-800 dark:text-blue-200">Next Steps</h4>';
        $lines[] = '<ul class="mt-2 space-y-1 text-sm text-blue-700 dark:text-blue-300">';
        $lines[] = '<li>• Use <strong>Apply to Invoice</strong> action to manually match this payment</li>';
        $lines[] = '<li>• Review the payment amount against open invoices</li>';
        $lines[] = '<li>• Verify the customer is correct before applying</li>';
        $lines[] = '</ul>';
        $lines[] = '<p class="mt-2 text-xs text-blue-600 dark:text-blue-400">Manual reconciliation actions will be available in US-E054.</p>';
        $lines[] = '</div>';

        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Section 4 - Metadata: raw metadata JSON.
     */
    protected function getMetadataSection(): Section
    {
        return Section::make('Metadata')
            ->description('Raw metadata associated with this payment')
            ->icon('heroicon-o-code-bracket')
            ->collapsed()
            ->schema([
                TextEntry::make('metadata_display')
                    ->label('')
                    ->getStateUsing(fn (Payment $record): string => $this->formatMetadata($record))
                    ->html(),
            ]);
    }

    /**
     * Format metadata for display.
     */
    protected function formatMetadata(Payment $record): string
    {
        $metadata = $record->metadata;

        if ($metadata === null || empty($metadata)) {
            return '<span class="text-gray-500">No metadata available</span>';
        }

        $lines = [];
        $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">';
        $lines[] = '<pre class="text-sm font-mono overflow-x-auto whitespace-pre-wrap">';
        $lines[] = e(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $lines[] = '</pre>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Section 5 - Audit: event timeline.
     */
    protected function getAuditSection(): Section
    {
        /** @var Payment $record */
        $record = $this->record;
        $auditCount = $record->auditLogs()->count();
        $countLabel = $auditCount > 0 ? " ({$auditCount})" : '';

        return Section::make('Audit Trail'.$countLabel)
            ->description('Immutable record of all changes to this payment')
            ->icon('heroicon-o-clock')
            ->collapsed()
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
            ]);
    }

    /**
     * Format audit log changes for display.
     */
    protected function formatAuditChanges(AuditLog $log): string
    {
        $changes = [];

        if ($log->event === AuditLog::EVENT_CREATED) {
            return '<span class="text-success-600">Payment created</span>';
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
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getApplyToInvoiceAction(),
        ];
    }

    /**
     * Action to apply this payment to an invoice (manual reconciliation).
     *
     * Visible only when:
     * - Payment status is confirmed (can be applied)
     * - Payment has unapplied amount > 0
     * - Payment reconciliation is pending or mismatched (not already fully matched)
     */
    protected function getApplyToInvoiceAction(): Action
    {
        return Action::make('applyToInvoice')
            ->label('Apply to Invoice')
            ->icon('heroicon-o-link')
            ->color('primary')
            ->visible(fn (): bool => $this->canApplyToInvoice())
            ->requiresConfirmation()
            ->modalHeading('Apply Payment to Invoice')
            ->modalDescription(fn (): string => $this->getApplyToInvoiceModalDescription())
            ->modalIcon('heroicon-o-link')
            ->form([
                Placeholder::make('payment_info')
                    ->label('')
                    ->content(fn (): string => $this->getPaymentInfoHtml())
                    ->columnSpanFull(),

                Select::make('invoice_id')
                    ->label('Invoice')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => $this->getAvailableInvoicesForPayment())
                    ->helperText('Select an open invoice to apply this payment to')
                    ->live()
                    ->afterStateUpdated(fn (callable $set, ?string $state) => $this->onInvoiceSelected($set, $state)),

                Placeholder::make('invoice_info')
                    ->label('')
                    ->content(fn (Get $get): string => $this->getInvoiceInfoHtml($get('invoice_id')))
                    ->visible(fn (Get $get): bool => $get('invoice_id') !== null)
                    ->columnSpanFull(),

                TextInput::make('amount')
                    ->label('Amount to Apply')
                    ->required()
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0.01)
                    ->prefix(fn (): string => $this->getPayment()->currency)
                    ->helperText(fn (Get $get): string => $this->getAmountHelperText($get('invoice_id')))
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $this->validateAmount($value, $get('invoice_id'), $fail);
                        },
                    ]),

                Placeholder::make('partial_warning')
                    ->label('')
                    ->content(fn (Get $get): string => $this->getPartialApplicationWarning($get('invoice_id'), $get('amount')))
                    ->visible(fn (Get $get): bool => $this->showPartialWarning($get('invoice_id'), $get('amount')))
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $this->applyPaymentToInvoice($data);
            });
    }

    /**
     * Check if the current payment can be applied to an invoice.
     */
    protected function canApplyToInvoice(): bool
    {
        $payment = $this->getPayment();

        // Must be able to be applied (confirmed status)
        if (! $payment->canBeAppliedToInvoice()) {
            return false;
        }

        // Must have unapplied amount
        if (bccomp($payment->getUnappliedAmount(), '0', 2) <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Get the modal description for the Apply to Invoice action.
     */
    protected function getApplyToInvoiceModalDescription(): string
    {
        $payment = $this->getPayment();

        return "Apply payment {$payment->payment_reference} to an invoice. This creates a link between the payment and the selected invoice.";
    }

    /**
     * Get HTML for payment info in the modal.
     */
    protected function getPaymentInfoHtml(): string
    {
        $payment = $this->getPayment();

        $lines = [];
        $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-4">';
        $lines[] = '<div class="grid grid-cols-2 gap-4 text-sm">';

        // Payment details
        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Payment Reference:</span>';
        $lines[] = '<span class="font-semibold ml-2">'.e($payment->payment_reference).'</span>';
        $lines[] = '</div>';

        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Total Amount:</span>';
        $lines[] = '<span class="font-semibold ml-2">'.e($payment->getFormattedAmount()).'</span>';
        $lines[] = '</div>';

        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Unapplied Amount:</span>';
        $lines[] = '<span class="font-semibold ml-2 text-amber-600">'.e($payment->getFormattedUnappliedAmount()).'</span>';
        $lines[] = '</div>';

        if ($payment->customer !== null) {
            $lines[] = '<div>';
            $lines[] = '<span class="text-gray-500 dark:text-gray-400">Customer:</span>';
            $lines[] = '<span class="font-semibold ml-2">'.e($payment->customer->name).'</span>';
            $lines[] = '</div>';
        }

        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Get available invoices that can receive this payment.
     *
     * @return array<string, string>
     */
    protected function getAvailableInvoicesForPayment(): array
    {
        $payment = $this->getPayment();

        // Build query for open invoices in matching currency
        $query = Invoice::where('currency', $payment->currency)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->orderBy('due_date', 'asc')
            ->orderBy('issued_at', 'asc');

        // If payment has customer, prioritize their invoices
        if ($payment->customer_id !== null) {
            $query->where('customer_id', $payment->customer_id);
        }

        $invoices = $query->with('customer')->get();

        $options = [];
        foreach ($invoices as $invoice) {
            $outstanding = $invoice->getOutstandingAmount();
            $customerName = $invoice->customer !== null ? $invoice->customer->name : 'Unknown';
            $invoiceNumber = $invoice->invoice_number ?? 'Draft';
            $dueInfo = $invoice->due_date !== null ? ' (Due: '.$invoice->due_date->format('M j, Y').')' : '';

            $label = "{$invoiceNumber} - {$customerName} - {$invoice->currency} {$outstanding} outstanding{$dueInfo}";

            if ($invoice->isOverdue()) {
                $label .= ' [OVERDUE]';
            }

            $options[$invoice->id] = $label;
        }

        return $options;
    }

    /**
     * Handle invoice selection - set default amount.
     */
    protected function onInvoiceSelected(callable $set, ?string $invoiceId): void
    {
        if ($invoiceId === null) {
            return;
        }

        $invoice = Invoice::find($invoiceId);
        if ($invoice === null) {
            return;
        }

        $payment = $this->getPayment();

        // Set default amount to the lesser of: invoice outstanding or unapplied payment amount
        $outstanding = $invoice->getOutstandingAmount();
        $unapplied = $payment->getUnappliedAmount();

        $defaultAmount = bccomp($outstanding, $unapplied, 2) <= 0 ? $outstanding : $unapplied;
        $set('amount', $defaultAmount);
    }

    /**
     * Get HTML for invoice info in the modal.
     */
    protected function getInvoiceInfoHtml(?string $invoiceId): string
    {
        if ($invoiceId === null) {
            return '';
        }

        $invoice = Invoice::with('customer')->find($invoiceId);
        if ($invoice === null) {
            return '';
        }

        $lines = [];
        $lines[] = '<div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-4">';
        $lines[] = '<h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Selected Invoice Details</h4>';
        $lines[] = '<div class="grid grid-cols-2 gap-2 text-sm">';

        $lines[] = '<div><span class="text-blue-600 dark:text-blue-400">Invoice Number:</span> <strong>'.e($invoice->invoice_number ?? 'Draft').'</strong></div>';
        $lines[] = '<div><span class="text-blue-600 dark:text-blue-400">Type:</span> '.e($invoice->getTypeCode().' - '.$invoice->getTypeLabel()).'</div>';
        $lines[] = '<div><span class="text-blue-600 dark:text-blue-400">Total Amount:</span> '.e($invoice->getFormattedTotal()).'</div>';
        $lines[] = '<div><span class="text-blue-600 dark:text-blue-400">Amount Paid:</span> '.e($invoice->currency.' '.number_format((float) $invoice->amount_paid, 2)).'</div>';
        $lines[] = '<div><span class="text-blue-600 dark:text-blue-400">Outstanding:</span> <strong class="text-amber-600">'.e($invoice->getFormattedOutstanding()).'</strong></div>';

        if ($invoice->due_date !== null) {
            $dueDateClass = $invoice->isOverdue() ? 'text-red-600' : 'text-blue-600 dark:text-blue-400';
            $lines[] = '<div><span class="'.$dueDateClass.'">Due Date:</span> '.e($invoice->due_date->format('M j, Y')).($invoice->isOverdue() ? ' <span class="text-red-600 font-semibold">(OVERDUE)</span>' : '').'</div>';
        }

        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Get helper text for the amount field.
     */
    protected function getAmountHelperText(?string $invoiceId): string
    {
        $payment = $this->getPayment();
        $unapplied = $payment->getUnappliedAmount();

        if ($invoiceId === null) {
            return "Maximum: {$payment->currency} {$unapplied} (unapplied payment balance)";
        }

        $invoice = Invoice::find($invoiceId);
        if ($invoice === null) {
            return "Maximum: {$payment->currency} {$unapplied} (unapplied payment balance)";
        }

        $outstanding = $invoice->getOutstandingAmount();
        $max = bccomp($outstanding, $unapplied, 2) <= 0 ? $outstanding : $unapplied;

        return "Maximum: {$payment->currency} {$max} (limited by ".
            (bccomp($outstanding, $unapplied, 2) <= 0 ? 'invoice outstanding' : 'unapplied payment balance').')';
    }

    /**
     * Validate the amount being applied.
     */
    protected function validateAmount(mixed $value, ?string $invoiceId, \Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $amount = (string) $value;
        $payment = $this->getPayment();

        // Check against unapplied payment amount
        $unapplied = $payment->getUnappliedAmount();
        if (bccomp($amount, $unapplied, 2) > 0) {
            $fail("Amount cannot exceed the unapplied payment balance ({$payment->currency} {$unapplied}).");

            return;
        }

        // Check against invoice outstanding if selected
        if ($invoiceId !== null) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice !== null) {
                $outstanding = $invoice->getOutstandingAmount();
                if (bccomp($amount, $outstanding, 2) > 0) {
                    $fail("Amount cannot exceed the invoice outstanding amount ({$invoice->currency} {$outstanding}).");
                }
            }
        }
    }

    /**
     * Check if partial application warning should be shown.
     */
    protected function showPartialWarning(?string $invoiceId, mixed $amount): bool
    {
        if ($invoiceId === null || $amount === null || $amount === '') {
            return false;
        }

        $invoice = Invoice::find($invoiceId);
        if ($invoice === null) {
            return false;
        }

        $outstanding = $invoice->getOutstandingAmount();

        return bccomp((string) $amount, $outstanding, 2) < 0;
    }

    /**
     * Get warning text for partial application.
     */
    protected function getPartialApplicationWarning(?string $invoiceId, mixed $amount): string
    {
        if ($invoiceId === null || $amount === null || $amount === '') {
            return '';
        }

        $invoice = Invoice::find($invoiceId);
        if ($invoice === null) {
            return '';
        }

        $outstanding = $invoice->getOutstandingAmount();
        $remaining = bcsub($outstanding, (string) $amount, 2);

        $lines = [];
        $lines[] = '<div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-amber-800 dark:text-amber-200">Partial Payment</h4>';
        $lines[] = '<p class="mt-1 text-sm text-amber-700 dark:text-amber-300">';
        $lines[] = 'This is a partial payment. The invoice will have '.e($invoice->currency.' '.$remaining).' remaining after this application.';
        $lines[] = '</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Execute the payment application.
     *
     * @param  array{invoice_id: string, amount: string}  $data
     */
    protected function applyPaymentToInvoice(array $data): void
    {
        $payment = $this->getPayment();
        $invoice = Invoice::findOrFail($data['invoice_id']);
        $amount = (string) $data['amount'];

        try {
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            // Apply the payment to the invoice
            $invoicePayment = $paymentService->applyToInvoice($payment, $invoice, $amount);

            // Mark payment as reconciled if this was the full amount
            if ($payment->fresh()?->isFullyApplied()) {
                $paymentService->markReconciled($payment->fresh(), ReconciliationStatus::Matched);
            }

            Notification::make()
                ->title('Payment Applied Successfully')
                ->body("{$payment->currency} {$amount} applied to invoice {$invoice->invoice_number}")
                ->success()
                ->send();

            // Refresh the page to show updated data
            $this->redirect(PaymentResource::getUrl('view', ['record' => $payment]));

        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Failed to Apply Payment')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Get the current payment record.
     */
    protected function getPayment(): Payment
    {
        /** @var Payment $record */
        $record = $this->record;

        return $record;
    }
}
