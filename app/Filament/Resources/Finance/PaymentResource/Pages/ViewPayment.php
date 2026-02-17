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
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
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

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                $this->getHeaderSection(),
                $this->getDuplicateWarningSection(),
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
                                ->size(TextSize::Large)
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
                                    ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
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
                                ->size(TextSize::Large),
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
     * Section for duplicate payment warnings.
     *
     * Shows potential duplicate payments (same amount, same customer, same day).
     * Only visible when duplicates are detected.
     */
    protected function getDuplicateWarningSection(): Section
    {
        /** @var Payment $record */
        $record = $this->record;
        $duplicates = $this->getPotentialDuplicates();

        return Section::make('Potential Duplicate Payment')
            ->description('Payments with similar characteristics detected')
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('warning')
            ->visible(fn (): bool => $duplicates->isNotEmpty())
            ->schema([
                TextEntry::make('duplicate_warning')
                    ->label('')
                    ->getStateUsing(fn (): string => $this->formatDuplicateWarning($duplicates))
                    ->html(),
            ])
            ->collapsed(false);
    }

    /**
     * Get potential duplicate payments for the current record.
     *
     * @return Collection<int, Payment>
     */
    protected function getPotentialDuplicates(): Collection
    {
        /** @var PaymentService $paymentService */
        $paymentService = app(PaymentService::class);

        return $paymentService->checkForDuplicates($this->getPayment());
    }

    /**
     * Format the duplicate warning HTML.
     *
     * @param  Collection<int, Payment>  $duplicates
     */
    protected function formatDuplicateWarning(Collection $duplicates): string
    {
        $payment = $this->getPayment();
        $count = $duplicates->count();

        $lines = [];
        $lines[] = '<div class="space-y-4">';

        // Warning header
        $lines[] = '<div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-amber-800 dark:text-amber-200">Possible Duplicate Payment Detected</h4>';
        $lines[] = '<p class="mt-1 text-sm text-amber-700 dark:text-amber-300">';
        $lines[] = "Found {$count} other payment(s) with the same amount ({$payment->getFormattedAmount()})";

        if ($payment->customer_id !== null) {
            $lines[] = ', same customer';
        }

        $lines[] = ', received on the same day ('.$payment->received_at->format('M j, Y').').';
        $lines[] = '</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        // List of potential duplicates
        $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">';
        $lines[] = '<h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Similar Payments</h4>';
        $lines[] = '<div class="overflow-x-auto">';
        $lines[] = '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
        $lines[] = '<thead>';
        $lines[] = '<tr>';
        $lines[] = '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reference</th>';
        $lines[] = '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Source</th>';
        $lines[] = '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>';
        $lines[] = '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>';
        $lines[] = '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Received</th>';
        $lines[] = '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>';
        $lines[] = '</tr>';
        $lines[] = '</thead>';
        $lines[] = '<tbody class="divide-y divide-gray-200 dark:divide-gray-700">';

        foreach ($duplicates as $duplicate) {
            $viewUrl = PaymentResource::getUrl('view', ['record' => $duplicate]);
            $statusColor = match ($duplicate->status->value) {
                'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                'failed' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
            };

            $lines[] = '<tr>';
            $lines[] = '<td class="px-3 py-2 text-sm font-mono">'.e($duplicate->payment_reference).'</td>';
            $lines[] = '<td class="px-3 py-2 text-sm">'.e($duplicate->source->label()).'</td>';
            $lines[] = '<td class="px-3 py-2 text-sm font-semibold">'.e($duplicate->getFormattedAmount()).'</td>';
            $lines[] = '<td class="px-3 py-2 text-sm"><span class="px-2 py-1 rounded-full text-xs '.$statusColor.'">'.e($duplicate->status->label()).'</span></td>';
            $lines[] = '<td class="px-3 py-2 text-sm">'.$duplicate->received_at->format('H:i:s').'</td>';
            $lines[] = '<td class="px-3 py-2 text-sm"><a href="'.$viewUrl.'" class="text-primary-600 hover:underline" target="_blank">View</a></td>';
            $lines[] = '</tr>';
        }

        $lines[] = '</tbody>';
        $lines[] = '</table>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        // Action guidance
        $lines[] = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">';
        $lines[] = '<h4 class="font-semibold text-blue-800 dark:text-blue-200">What to do?</h4>';
        $lines[] = '<ul class="mt-2 space-y-1 text-sm text-blue-700 dark:text-blue-300">';
        $lines[] = '<li>• <strong>Review the payments:</strong> Check if these are truly duplicates or legitimate separate payments.</li>';
        $lines[] = '<li>• <strong>Confirm as unique:</strong> If this payment is not a duplicate, use the "Confirm Not Duplicate" action to dismiss this warning.</li>';
        $lines[] = '<li>• <strong>Mark as duplicate:</strong> If this is a duplicate payment, use the "Mark as Duplicate" action. This will flag the payment for refund processing.</li>';
        $lines[] = '</ul>';
        $lines[] = '</div>';

        $lines[] = '</div>';

        return implode("\n", $lines);
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
        $lines[] = '<li>• <strong>Force Match:</strong> Override the mismatch and apply the payment to an invoice</li>';
        $lines[] = '<li>• <strong>Create Exception:</strong> Document why this mismatch cannot be resolved</li>';
        $lines[] = '<li>• <strong>Refund:</strong> Mark this payment for refund processing</li>';
        $lines[] = '</ul>';
        $lines[] = '<p class="mt-2 text-xs text-blue-600 dark:text-blue-400">Use the action buttons above to resolve this mismatch.</p>';
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
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getApplyToInvoiceAction(),
            $this->getApplyToMultipleInvoicesAction(),
            $this->getForceMatchAction(),
            $this->getCreateExceptionAction(),
            $this->getMarkForRefundAction(),
            $this->getConfirmNotDuplicateAction(),
            $this->getMarkAsDuplicateAction(),
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
            ->schema([
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
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $this->onInvoiceSelected($set, $state)),

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
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
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
    protected function onInvoiceSelected(Set $set, ?string $invoiceId): void
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
    protected function validateAmount(mixed $value, ?string $invoiceId, Closure $fail): void
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

    // =========================================================================
    // Apply to Multiple Invoices Action (US-E057)
    // =========================================================================

    /**
     * Action to apply this payment to multiple invoices at once.
     *
     * Visible only when:
     * - Payment status is confirmed (can be applied)
     * - Payment has unapplied amount > 0
     */
    protected function getApplyToMultipleInvoicesAction(): Action
    {
        return Action::make('applyToMultipleInvoices')
            ->label('Apply to Multiple Invoices')
            ->icon('heroicon-o-rectangle-stack')
            ->color('primary')
            ->visible(fn (): bool => $this->canApplyToMultipleInvoices())
            ->requiresConfirmation()
            ->modalHeading('Apply Payment to Multiple Invoices')
            ->modalDescription(fn (): string => $this->getApplyToMultipleModalDescription())
            ->modalIcon('heroicon-o-rectangle-stack')
            ->modalWidth('4xl')
            ->schema([
                Placeholder::make('multi_payment_info')
                    ->label('')
                    ->content(fn (): HtmlString => new HtmlString($this->getMultiPaymentInfoHtml()))
                    ->columnSpanFull(),

                Repeater::make('applications')
                    ->label('Invoice Applications')
                    ->schema([
                        Select::make('invoice_id')
                            ->label('Invoice')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => $this->getAvailableInvoicesForPayment())
                            ->helperText('Select an invoice')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => $this->onMultiInvoiceSelected($set, $get, $state))
                            ->columnSpan(2),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0.01)
                            ->prefix(fn (): string => $this->getPayment()->currency)
                            ->live(debounce: 500)
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->addActionLabel('Add Invoice')
                    ->minItems(1)
                    ->maxItems(10)
                    ->reorderable(false)
                    ->defaultItems(1)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get) => $this->updateMultiTotals($set, $get)),

                Placeholder::make('multi_totals')
                    ->label('')
                    ->content(fn (Get $get): HtmlString => new HtmlString($this->getMultiTotalsHtml($get('applications'))))
                    ->columnSpanFull(),

                Placeholder::make('multi_validation_warning')
                    ->label('')
                    ->content(fn (Get $get): HtmlString => new HtmlString($this->getMultiValidationWarning($get('applications'))))
                    ->visible(fn (Get $get): bool => $this->hasMultiValidationWarning($get('applications')))
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $this->applyPaymentToMultipleInvoices($data);
            });
    }

    /**
     * Check if the current payment can be applied to multiple invoices.
     */
    protected function canApplyToMultipleInvoices(): bool
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
     * Get the modal description for the Apply to Multiple Invoices action.
     */
    protected function getApplyToMultipleModalDescription(): string
    {
        $payment = $this->getPayment();

        return "Split payment {$payment->payment_reference} across multiple invoices. The sum of amounts cannot exceed the payment amount.";
    }

    /**
     * Get HTML for multi-payment info in the modal.
     */
    protected function getMultiPaymentInfoHtml(): string
    {
        $payment = $this->getPayment();

        $lines = [];
        $lines[] = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-blue-800 dark:text-blue-200">Payment Details</h4>';
        $lines[] = '<p class="mt-1 text-sm text-blue-700 dark:text-blue-300">';
        $lines[] = 'Add invoices and specify amounts for each. The total applied cannot exceed the unapplied payment balance.';
        $lines[] = '</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        $lines[] = '<div class="mt-3 grid grid-cols-3 gap-4 text-sm">';
        $lines[] = '<div>';
        $lines[] = '<span class="text-blue-600 dark:text-blue-400">Reference:</span>';
        $lines[] = '<span class="font-semibold ml-2">'.e($payment->payment_reference).'</span>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<span class="text-blue-600 dark:text-blue-400">Total Amount:</span>';
        $lines[] = '<span class="font-semibold ml-2">'.e($payment->getFormattedAmount()).'</span>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<span class="text-blue-600 dark:text-blue-400">Available to Apply:</span>';
        $lines[] = '<span class="font-semibold ml-2 text-success-600">'.e($payment->getFormattedUnappliedAmount()).'</span>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Handle invoice selection in multi-invoice mode - auto-fill amount.
     */
    protected function onMultiInvoiceSelected(Set $set, Get $get, ?string $invoiceId): void
    {
        if ($invoiceId === null) {
            return;
        }

        $invoice = Invoice::find($invoiceId);
        if ($invoice === null) {
            return;
        }

        $payment = $this->getPayment();

        // Calculate remaining unapplied amount after other applications in the form
        /** @var array<int, array{invoice_id?: string, amount?: string}>|null $applications */
        $applications = $get('../../applications');
        $otherApplicationsTotal = '0';

        if ($applications !== null) {
            foreach ($applications as $app) {
                // Skip the current item (don't count its amount)
                if (($app['invoice_id'] ?? null) !== $invoiceId && isset($app['amount']) && $app['amount'] !== '') {
                    $otherApplicationsTotal = bcadd($otherApplicationsTotal, (string) $app['amount'], 2);
                }
            }
        }

        $remaining = bcsub($payment->getUnappliedAmount(), $otherApplicationsTotal, 2);
        $outstanding = $invoice->getOutstandingAmount();

        // Set default to the lesser of: invoice outstanding or remaining unapplied
        $defaultAmount = bccomp($outstanding, $remaining, 2) <= 0 ? $outstanding : $remaining;
        $defaultAmount = bccomp($defaultAmount, '0', 2) > 0 ? $defaultAmount : '0';

        $set('amount', $defaultAmount);
    }

    /**
     * Update totals when applications change.
     */
    protected function updateMultiTotals(Set $set, Get $get): void
    {
        // Totals are recalculated via Placeholder content callback
        // This method exists for future enhancements
    }

    /**
     * Get HTML for displaying totals in multi-invoice mode.
     *
     * @param  array<int, array{invoice_id?: string, amount?: string}>|null  $applications
     */
    protected function getMultiTotalsHtml(?array $applications): string
    {
        $payment = $this->getPayment();
        $unapplied = $payment->getUnappliedAmount();

        // Calculate total being applied
        $totalApplying = '0';
        $validCount = 0;

        if ($applications !== null) {
            foreach ($applications as $application) {
                $amount = $application['amount'] ?? '';
                if ($amount !== '' && bccomp((string) $amount, '0', 2) > 0) {
                    $totalApplying = bcadd($totalApplying, (string) $amount, 2);
                    $validCount++;
                }
            }
        }

        $remaining = bcsub($unapplied, $totalApplying, 2);
        $isOverBudget = bccomp($totalApplying, $unapplied, 2) > 0;

        $lines = [];
        $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mt-4">';
        $lines[] = '<h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Application Summary</h4>';
        $lines[] = '<div class="grid grid-cols-4 gap-4 text-sm">';

        // Available
        $lines[] = '<div class="text-center p-3 bg-white dark:bg-gray-700 rounded">';
        $lines[] = '<div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Available</div>';
        $lines[] = '<div class="font-bold text-lg text-gray-900 dark:text-gray-100">'.e($payment->currency).' '.e($unapplied).'</div>';
        $lines[] = '</div>';

        // Total Applying
        $applyingColor = $isOverBudget ? 'text-red-600' : 'text-primary-600';
        $lines[] = '<div class="text-center p-3 bg-white dark:bg-gray-700 rounded">';
        $lines[] = '<div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Applying</div>';
        $lines[] = '<div class="font-bold text-lg '.$applyingColor.'">'.e($payment->currency).' '.e($totalApplying).'</div>';
        $lines[] = '</div>';

        // Remaining
        $remainingColor = bccomp($remaining, '0', 2) < 0 ? 'text-red-600' : (bccomp($remaining, '0', 2) > 0 ? 'text-amber-600' : 'text-success-600');
        $lines[] = '<div class="text-center p-3 bg-white dark:bg-gray-700 rounded">';
        $lines[] = '<div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Remaining</div>';
        $lines[] = '<div class="font-bold text-lg '.$remainingColor.'">'.e($payment->currency).' '.e($remaining).'</div>';
        $lines[] = '</div>';

        // Invoice Count
        $lines[] = '<div class="text-center p-3 bg-white dark:bg-gray-700 rounded">';
        $lines[] = '<div class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Invoices</div>';
        $lines[] = '<div class="font-bold text-lg text-gray-900 dark:text-gray-100">'.$validCount.'</div>';
        $lines[] = '</div>';

        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Check if there's a validation warning for multi-invoice applications.
     *
     * @param  array<int, array{invoice_id?: string, amount?: string}>|null  $applications
     */
    protected function hasMultiValidationWarning(?array $applications): bool
    {
        if ($applications === null) {
            return false;
        }

        $payment = $this->getPayment();
        $unapplied = $payment->getUnappliedAmount();

        // Check if total exceeds unapplied amount
        $totalApplying = '0';
        foreach ($applications as $application) {
            $amount = $application['amount'] ?? '';
            if ($amount !== '') {
                $totalApplying = bcadd($totalApplying, (string) $amount, 2);
            }
        }

        if (bccomp($totalApplying, $unapplied, 2) > 0) {
            return true;
        }

        // Check for duplicate invoices
        $invoiceIds = [];
        foreach ($applications as $application) {
            $invoiceId = $application['invoice_id'] ?? null;
            if ($invoiceId !== null) {
                if (in_array($invoiceId, $invoiceIds, true)) {
                    return true;
                }
                $invoiceIds[] = $invoiceId;
            }
        }

        return false;
    }

    /**
     * Get validation warning HTML for multi-invoice applications.
     *
     * @param  array<int, array{invoice_id?: string, amount?: string}>|null  $applications
     */
    protected function getMultiValidationWarning(?array $applications): string
    {
        if ($applications === null) {
            return '';
        }

        $warnings = [];
        $payment = $this->getPayment();
        $unapplied = $payment->getUnappliedAmount();

        // Check if total exceeds unapplied amount
        $totalApplying = '0';
        foreach ($applications as $application) {
            $amount = $application['amount'] ?? '';
            if ($amount !== '') {
                $totalApplying = bcadd($totalApplying, (string) $amount, 2);
            }
        }

        if (bccomp($totalApplying, $unapplied, 2) > 0) {
            $over = bcsub($totalApplying, $unapplied, 2);
            $warnings[] = "Total amount ({$payment->currency} {$totalApplying}) exceeds available balance ({$payment->currency} {$unapplied}) by {$payment->currency} {$over}.";
        }

        // Check for duplicate invoices
        $invoiceIds = [];
        foreach ($applications as $application) {
            $invoiceId = $application['invoice_id'] ?? null;
            if ($invoiceId !== null) {
                if (in_array($invoiceId, $invoiceIds, true)) {
                    $warnings[] = 'Same invoice selected multiple times. Each invoice can only appear once.';
                    break;
                }
                $invoiceIds[] = $invoiceId;
            }
        }

        if (empty($warnings)) {
            return '';
        }

        $lines = [];
        $lines[] = '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-red-800 dark:text-red-200">Validation Errors</h4>';
        $lines[] = '<ul class="mt-1 text-sm text-red-700 dark:text-red-300 list-disc list-inside">';
        foreach ($warnings as $warning) {
            $lines[] = '<li>'.e($warning).'</li>';
        }
        $lines[] = '</ul>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Execute applying payment to multiple invoices.
     *
     * @param  array{applications: array<int, array{invoice_id: string, amount: string}>}  $data
     */
    protected function applyPaymentToMultipleInvoices(array $data): void
    {
        $payment = $this->getPayment();

        /** @var array<int, array<string, mixed>> $applications */
        $applications = $data['applications'];

        // Filter out any empty entries
        $validApplications = [];
        foreach ($applications as $application) {
            $invoiceId = $application['invoice_id'] ?? null;
            $amount = $application['amount'] ?? null;

            if (
                $invoiceId !== null &&
                $amount !== null &&
                $invoiceId !== '' &&
                $amount !== '' &&
                bccomp((string) $amount, '0', 2) > 0
            ) {
                $validApplications[] = [
                    'invoice_id' => (string) $invoiceId,
                    'amount' => (string) $amount,
                ];
            }
        }

        if (empty($validApplications)) {
            Notification::make()
                ->title('No Valid Applications')
                ->body('Please add at least one invoice with a valid amount.')
                ->danger()
                ->send();

            return;
        }

        // Check for duplicate invoices
        $invoiceIds = array_column($validApplications, 'invoice_id');
        if (count($invoiceIds) !== count(array_unique($invoiceIds))) {
            Notification::make()
                ->title('Duplicate Invoices')
                ->body('Each invoice can only be selected once.')
                ->danger()
                ->send();

            return;
        }

        try {
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            // Apply the payment to multiple invoices
            $invoicePayments = $paymentService->applyToMultipleInvoices($payment, $validApplications);

            // Mark payment as reconciled if fully applied
            $freshPayment = $payment->fresh();
            if ($freshPayment !== null && $freshPayment->isFullyApplied()) {
                $paymentService->markReconciled($freshPayment, ReconciliationStatus::Matched);
            }

            $totalApplied = array_reduce(
                $validApplications,
                fn ($carry, $app) => bcadd($carry, $app['amount'], 2),
                '0'
            );

            Notification::make()
                ->title('Payment Applied Successfully')
                ->body("{$payment->currency} {$totalApplied} applied across ".count($invoicePayments).' invoices.')
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

    // =========================================================================
    // Mismatch Resolution Actions (US-E055)
    // =========================================================================

    /**
     * Action to force match a mismatched payment to an invoice.
     *
     * Visible only when payment has reconciliation_status = mismatched.
     */
    protected function getForceMatchAction(): Action
    {
        return Action::make('forceMatch')
            ->label('Force Match')
            ->icon('heroicon-o-link')
            ->color('warning')
            ->visible(fn (): bool => $this->canForceMatch())
            ->requiresConfirmation()
            ->modalHeading('Force Match Payment')
            ->modalDescription(fn (): string => 'This will override the mismatch and apply the payment to the selected invoice.')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->schema([
                Placeholder::make('mismatch_info')
                    ->label('')
                    ->content(fn (): string => $this->getForceMatchInfoHtml())
                    ->columnSpanFull(),

                Select::make('invoice_id')
                    ->label('Invoice to Match')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => $this->getAvailableInvoicesForForceMatch())
                    ->helperText('Select the invoice to force match this payment to')
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $this->onForceMatchInvoiceSelected($set, $state)),

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
                    ->helperText('The amount to apply from this payment'),

                Textarea::make('reason')
                    ->label('Reason for Force Match')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000)
                    ->helperText('Explain why this payment is being force matched despite the mismatch')
                    ->placeholder('Enter the reason for overriding the mismatch...'),

                Checkbox::make('confirm_override')
                    ->label('I confirm this override is appropriate and documented')
                    ->required()
                    ->accepted(),
            ])
            ->action(function (array $data): void {
                $this->executeForceMatch($data);
            });
    }

    /**
     * Check if force match action should be visible.
     */
    protected function canForceMatch(): bool
    {
        $payment = $this->getPayment();

        return $payment->hasMismatch()
            && $payment->canBeAppliedToInvoice()
            && bccomp($payment->getUnappliedAmount(), '0', 2) > 0;
    }

    /**
     * Get HTML for force match info in the modal.
     */
    protected function getForceMatchInfoHtml(): string
    {
        $payment = $this->getPayment();

        $lines = [];
        $lines[] = '<div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-amber-800 dark:text-amber-200">Current Mismatch</h4>';
        $lines[] = '<p class="mt-1 text-sm text-amber-700 dark:text-amber-300">'.e($payment->getMismatchReason()).'</p>';

        $mismatchType = $payment->getMismatchTypeLabel();
        if ($mismatchType !== null) {
            $lines[] = '<p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Type: '.e($mismatchType).'</p>';
        }

        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        // Payment summary
        $lines[] = '<div class="grid grid-cols-2 gap-4 text-sm">';
        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Payment Reference:</span>';
        $lines[] = '<span class="font-semibold ml-2">'.e($payment->payment_reference).'</span>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Unapplied Amount:</span>';
        $lines[] = '<span class="font-semibold ml-2 text-amber-600">'.e($payment->getFormattedUnappliedAmount()).'</span>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Get available invoices for force match (all open invoices, not filtered by customer).
     *
     * @return array<string, string>
     */
    protected function getAvailableInvoicesForForceMatch(): array
    {
        $payment = $this->getPayment();

        // Get all open invoices in matching currency (not filtered by customer for force match)
        $invoices = Invoice::where('currency', $payment->currency)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->orderBy('due_date', 'asc')
            ->orderBy('issued_at', 'asc')
            ->with('customer')
            ->get();

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
     * Handle invoice selection for force match.
     */
    protected function onForceMatchInvoiceSelected(Set $set, ?string $invoiceId): void
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
     * Execute the force match action.
     *
     * @param  array{invoice_id: string, amount: string, reason: string, confirm_override: bool}  $data
     */
    protected function executeForceMatch(array $data): void
    {
        $payment = $this->getPayment();
        $invoice = Invoice::findOrFail($data['invoice_id']);
        $amount = (string) $data['amount'];
        $reason = (string) $data['reason'];

        try {
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            $paymentService->forceMatch($payment, $invoice, $reason, $amount);

            Notification::make()
                ->title('Payment Force Matched')
                ->body("Payment force matched to invoice {$invoice->invoice_number}. Amount: {$payment->currency} {$amount}")
                ->success()
                ->send();

            $this->redirect(PaymentResource::getUrl('view', ['record' => $payment]));

        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Force Match Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Action to create an exception for a mismatched payment.
     *
     * Visible only when payment has reconciliation_status = mismatched.
     */
    protected function getCreateExceptionAction(): Action
    {
        return Action::make('createException')
            ->label('Create Exception')
            ->icon('heroicon-o-flag')
            ->color('gray')
            ->visible(fn (): bool => $this->canCreateException())
            ->requiresConfirmation()
            ->modalHeading('Create Payment Exception')
            ->modalDescription('Document why this mismatch cannot be resolved through normal means.')
            ->modalIcon('heroicon-o-flag')
            ->schema([
                Placeholder::make('mismatch_info')
                    ->label('')
                    ->content(fn (): string => $this->getExceptionInfoHtml())
                    ->columnSpanFull(),

                Select::make('exception_type')
                    ->label('Exception Type')
                    ->required()
                    ->options(PaymentService::getExceptionTypes())
                    ->helperText('Select the type of exception'),

                Textarea::make('reason')
                    ->label('Exception Reason')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000)
                    ->helperText('Explain why this payment cannot be reconciled through normal means')
                    ->placeholder('Enter the reason for creating this exception...'),

                Checkbox::make('confirm_exception')
                    ->label('I confirm this exception has been properly reviewed')
                    ->required()
                    ->accepted(),
            ])
            ->action(function (array $data): void {
                $this->executeCreateException($data);
            });
    }

    /**
     * Check if create exception action should be visible.
     */
    protected function canCreateException(): bool
    {
        $payment = $this->getPayment();

        return $payment->hasMismatch();
    }

    /**
     * Get HTML for exception info in the modal.
     */
    protected function getExceptionInfoHtml(): string
    {
        $payment = $this->getPayment();

        $lines = [];
        $lines[] = '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-4">';
        $lines[] = '<h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Payment Details</h4>';
        $lines[] = '<div class="grid grid-cols-2 gap-2 text-sm">';
        $lines[] = '<div><span class="text-gray-500 dark:text-gray-400">Reference:</span> <strong>'.e($payment->payment_reference).'</strong></div>';
        $lines[] = '<div><span class="text-gray-500 dark:text-gray-400">Amount:</span> <strong>'.e($payment->getFormattedAmount()).'</strong></div>';
        $lines[] = '<div><span class="text-gray-500 dark:text-gray-400">Current Mismatch:</span> '.e($payment->getMismatchReason()).'</div>';

        $mismatchType = $payment->getMismatchTypeLabel();
        if ($mismatchType !== null) {
            $lines[] = '<div><span class="text-gray-500 dark:text-gray-400">Mismatch Type:</span> '.e($mismatchType).'</div>';
        }

        $lines[] = '</div>';
        $lines[] = '</div>';

        // Info about what exception means
        $lines[] = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">';
        $lines[] = '<h4 class="font-semibold text-blue-800 dark:text-blue-200">What is an Exception?</h4>';
        $lines[] = '<p class="mt-1 text-sm text-blue-700 dark:text-blue-300">';
        $lines[] = 'Creating an exception documents that this payment mismatch has been reviewed but cannot be resolved ';
        $lines[] = 'through normal means. The payment will remain as mismatched but with documentation explaining why.';
        $lines[] = '</p>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Execute the create exception action.
     *
     * @param  array{exception_type: string, reason: string, confirm_exception: bool}  $data
     */
    protected function executeCreateException(array $data): void
    {
        $payment = $this->getPayment();
        $reason = (string) $data['reason'];
        $exceptionType = (string) $data['exception_type'];

        try {
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            $paymentService->createException($payment, $reason, $exceptionType);

            Notification::make()
                ->title('Exception Created')
                ->body('Payment exception has been documented.')
                ->success()
                ->send();

            $this->redirect(PaymentResource::getUrl('view', ['record' => $payment]));

        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Failed to Create Exception')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Action to mark a mismatched payment for refund.
     *
     * Visible only when payment has reconciliation_status = mismatched and can be refunded.
     */
    protected function getMarkForRefundAction(): Action
    {
        return Action::make('markForRefund')
            ->label('Refund')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->visible(fn (): bool => $this->canMarkForRefund())
            ->requiresConfirmation()
            ->modalHeading('Mark Payment for Refund')
            ->modalDescription('This will mark the payment for refund processing. The actual refund will be processed separately.')
            ->modalIcon('heroicon-o-arrow-uturn-left')
            ->schema([
                Placeholder::make('refund_warning')
                    ->label('')
                    ->content(fn (): string => $this->getRefundWarningHtml())
                    ->columnSpanFull(),

                Textarea::make('reason')
                    ->label('Refund Reason')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000)
                    ->helperText('Explain why this payment needs to be refunded')
                    ->placeholder('Enter the reason for the refund...'),

                Checkbox::make('confirm_refund')
                    ->label('I understand this will initiate a refund process')
                    ->required()
                    ->accepted(),
            ])
            ->action(function (array $data): void {
                $this->executeMarkForRefund($data);
            });
    }

    /**
     * Check if mark for refund action should be visible.
     */
    protected function canMarkForRefund(): bool
    {
        $payment = $this->getPayment();

        return $payment->hasMismatch() && $payment->canBeRefunded();
    }

    /**
     * Get HTML for refund warning in the modal.
     */
    protected function getRefundWarningHtml(): string
    {
        $payment = $this->getPayment();

        $lines = [];

        // Warning about refund
        $lines[] = '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-red-800 dark:text-red-200">Refund Warning</h4>';
        $lines[] = '<p class="mt-1 text-sm text-red-700 dark:text-red-300">';
        $lines[] = 'Marking this payment for refund will initiate the refund process. ';

        if ($payment->isFromStripe()) {
            $lines[] = 'For Stripe payments, the refund will be processed automatically.';
        } else {
            $lines[] = 'For bank transfers, a manual refund will need to be processed.';
        }

        $lines[] = '</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        // Payment details
        $lines[] = '<div class="grid grid-cols-2 gap-4 text-sm">';
        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Payment Reference:</span>';
        $lines[] = '<span class="font-semibold ml-2">'.e($payment->payment_reference).'</span>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Amount to Refund:</span>';
        $lines[] = '<span class="font-semibold ml-2 text-red-600">'.e($payment->getFormattedAmount()).'</span>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Payment Source:</span>';
        $lines[] = '<span class="font-semibold ml-2">'.e($payment->source->label()).'</span>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<span class="text-gray-500 dark:text-gray-400">Current Mismatch:</span>';
        $lines[] = '<span class="ml-2">'.e($payment->getMismatchReason()).'</span>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Execute the mark for refund action.
     *
     * @param  array{reason: string, confirm_refund: bool}  $data
     */
    protected function executeMarkForRefund(array $data): void
    {
        $payment = $this->getPayment();
        $reason = (string) $data['reason'];

        try {
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            $paymentService->markForRefund($payment, $reason);

            Notification::make()
                ->title('Payment Marked for Refund')
                ->body('The payment has been marked for refund processing.')
                ->success()
                ->send();

            $this->redirect(PaymentResource::getUrl('view', ['record' => $payment]));

        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Failed to Mark for Refund')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // =========================================================================
    // Duplicate Payment Actions (US-E060)
    // =========================================================================

    /**
     * Action to confirm this payment is not a duplicate.
     *
     * Visible only when potential duplicates are detected.
     */
    protected function getConfirmNotDuplicateAction(): Action
    {
        return Action::make('confirmNotDuplicate')
            ->label('Confirm Not Duplicate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (): bool => $this->hasPotentialDuplicates())
            ->requiresConfirmation()
            ->modalHeading('Confirm Payment is Not a Duplicate')
            ->modalDescription('This will dismiss the duplicate warning for this payment.')
            ->modalIcon('heroicon-o-check-circle')
            ->schema([
                Placeholder::make('info')
                    ->label('')
                    ->content(fn (): string => $this->getConfirmNotDuplicateInfoHtml())
                    ->columnSpanFull(),

                Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->maxLength(500)
                    ->helperText('Optionally explain why this is not a duplicate')
                    ->placeholder('e.g., Customer made two separate orders on the same day...'),

                Checkbox::make('confirm')
                    ->label('I have verified this is not a duplicate payment')
                    ->required()
                    ->accepted(),
            ])
            ->action(function (array $data): void {
                $this->executeConfirmNotDuplicate($data);
            });
    }

    /**
     * Check if there are potential duplicates for this payment.
     */
    protected function hasPotentialDuplicates(): bool
    {
        return $this->getPotentialDuplicates()->isNotEmpty();
    }

    /**
     * Get HTML for confirm not duplicate info.
     */
    protected function getConfirmNotDuplicateInfoHtml(): string
    {
        $payment = $this->getPayment();
        $duplicates = $this->getPotentialDuplicates();

        $lines = [];
        $lines[] = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-blue-800 dark:text-blue-200">Confirming as Unique Payment</h4>';
        $lines[] = '<p class="mt-1 text-sm text-blue-700 dark:text-blue-300">';
        $lines[] = 'This will mark payment <strong>'.e($payment->payment_reference).'</strong> as verified and not a duplicate. ';
        $lines[] = 'The '.e($duplicates->count()).' similar payment(s) will still show their own duplicate warnings until reviewed.';
        $lines[] = '</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Execute the confirm not duplicate action.
     *
     * @param  array{reason?: string, confirm: bool}  $data
     */
    protected function executeConfirmNotDuplicate(array $data): void
    {
        $payment = $this->getPayment();
        $reason = trim((string) ($data['reason'] ?? ''));

        try {
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            $paymentService->confirmNotDuplicate($payment, $reason !== '' ? $reason : null);

            Notification::make()
                ->title('Payment Confirmed as Unique')
                ->body('The duplicate warning has been dismissed for this payment.')
                ->success()
                ->send();

            $this->redirect(PaymentResource::getUrl('view', ['record' => $payment]));

        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Failed to Confirm')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Action to mark this payment as a duplicate.
     *
     * Visible only when potential duplicates are detected.
     */
    protected function getMarkAsDuplicateAction(): Action
    {
        return Action::make('markAsDuplicate')
            ->label('Mark as Duplicate')
            ->icon('heroicon-o-document-duplicate')
            ->color('danger')
            ->visible(fn (): bool => $this->hasPotentialDuplicates())
            ->requiresConfirmation()
            ->modalHeading('Mark Payment as Duplicate')
            ->modalDescription('This will flag the payment as a duplicate and prepare it for refund.')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->schema([
                Placeholder::make('warning')
                    ->label('')
                    ->content(fn (): string => $this->getMarkAsDuplicateWarningHtml())
                    ->columnSpanFull(),

                Select::make('original_payment_id')
                    ->label('Original Payment')
                    ->required()
                    ->options(fn (): array => $this->getDuplicatePaymentOptions())
                    ->helperText('Select which payment is the original (non-duplicate)'),

                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000)
                    ->helperText('Explain why this is a duplicate payment')
                    ->placeholder('e.g., Customer accidentally submitted payment twice...'),

                Checkbox::make('initiate_refund')
                    ->label('Initiate refund for this duplicate payment')
                    ->default(true)
                    ->helperText('If checked, this payment will be marked for refund processing'),

                Checkbox::make('confirm')
                    ->label('I confirm this payment is a duplicate and should be processed accordingly')
                    ->required()
                    ->accepted(),
            ])
            ->action(function (array $data): void {
                $this->executeMarkAsDuplicate($data);
            });
    }

    /**
     * Get HTML for mark as duplicate warning.
     */
    protected function getMarkAsDuplicateWarningHtml(): string
    {
        $payment = $this->getPayment();

        $lines = [];
        $lines[] = '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">';
        $lines[] = '<div class="flex items-start gap-3">';
        $lines[] = '<div class="flex-shrink-0">';
        $lines[] = '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">';
        $lines[] = '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>';
        $lines[] = '</svg>';
        $lines[] = '</div>';
        $lines[] = '<div>';
        $lines[] = '<h4 class="font-semibold text-red-800 dark:text-red-200">Marking as Duplicate</h4>';
        $lines[] = '<p class="mt-1 text-sm text-red-700 dark:text-red-300">';
        $lines[] = 'This will mark payment <strong>'.e($payment->payment_reference).'</strong> ('.e($payment->getFormattedAmount()).') as a duplicate. ';
        $lines[] = 'The payment will be flagged with a duplicate mismatch status and optionally marked for refund.';
        $lines[] = '</p>';
        $lines[] = '</div>';
        $lines[] = '</div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Get options for selecting the original payment.
     *
     * @return array<string, string>
     */
    protected function getDuplicatePaymentOptions(): array
    {
        $duplicates = $this->getPotentialDuplicates();
        $options = [];

        foreach ($duplicates as $duplicate) {
            $options[$duplicate->id] = $duplicate->payment_reference.' - '.$duplicate->getFormattedAmount().' ('.$duplicate->received_at->format('M j, Y H:i').')';
        }

        return $options;
    }

    /**
     * Execute the mark as duplicate action.
     *
     * @param  array{original_payment_id: string, reason: string, initiate_refund: bool, confirm: bool}  $data
     */
    protected function executeMarkAsDuplicate(array $data): void
    {
        $payment = $this->getPayment();
        $originalPaymentId = (string) $data['original_payment_id'];
        $reason = (string) $data['reason'];
        $initiateRefund = (bool) $data['initiate_refund'];

        try {
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            $paymentService->markAsDuplicate($payment, $originalPaymentId, $reason, $initiateRefund);

            $message = 'Payment has been marked as a duplicate.';
            if ($initiateRefund) {
                $message .= ' It has also been marked for refund processing.';
            }

            Notification::make()
                ->title('Payment Marked as Duplicate')
                ->body($message)
                ->success()
                ->send();

            $this->redirect(PaymentResource::getUrl('view', ['record' => $payment]));

        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Failed to Mark as Duplicate')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
