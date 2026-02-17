<?php

namespace App\Filament\Resources\Finance\CreditNoteResource\Pages;

use App\Enums\Finance\CreditNoteStatus;
use App\Filament\Resources\Finance\CreditNoteResource;
use App\Models\AuditLog;
use App\Models\Finance\CreditNote;
use App\Services\Finance\CreditNoteService;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewCreditNote extends ViewRecord
{
    protected static string $resource = CreditNoteResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var CreditNote $record */
        $record = $this->record;

        return 'Credit Note: '.($record->credit_note_number ?? 'Draft #'.$record->id);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                $this->getHeaderSection(),
                Tabs::make('Credit Note Details')
                    ->tabs([
                        $this->getOriginalInvoiceTab(),
                        $this->getReasonTab(),
                        $this->getApplicationTab(),
                        $this->getAccountingTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Header section with credit_note_number, amount, status.
     */
    protected function getHeaderSection(): Section
    {
        return Section::make('Credit Note Overview')
            ->schema([
                Grid::make(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('credit_note_number')
                                ->label('Credit Note Number')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->copyable()
                                ->copyMessage('Credit note number copied')
                                ->placeholder('Draft - Not yet issued'),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn (CreditNoteStatus $state): string => $state->label())
                                ->color(fn (CreditNoteStatus $state): string => $state->color())
                                ->icon(fn (CreditNoteStatus $state): string => $state->icon()),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('customer.name')
                                ->label('Customer')
                                ->url(fn (CreditNote $record): ?string => $record->customer
                                    ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                                    : null)
                                ->color('primary'),
                            TextEntry::make('customer.email')
                                ->label('Email')
                                ->copyable()
                                ->copyMessage('Email copied')
                                ->placeholder('N/A'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('amount')
                                ->label('Credit Note Amount')
                                ->money(fn (CreditNote $record): string => $record->currency)
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->color('warning'),
                            TextEntry::make('currency')
                                ->label('Currency')
                                ->badge()
                                ->color('gray'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('issued_at')
                                ->label('Issued At')
                                ->dateTime()
                                ->placeholder('Not issued'),
                            TextEntry::make('issuedByUser.name')
                                ->label('Issued By')
                                ->placeholder('N/A'),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    /**
     * Tab 1: Original Invoice - link to invoice, type, amount.
     */
    protected function getOriginalInvoiceTab(): Tab
    {
        return Tab::make('Original Invoice')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Linked Invoice')
                    ->description('The original invoice this credit note is issued against')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('invoice.invoice_number')
                                    ->label('Invoice Number')
                                    ->weight(FontWeight::Bold)
                                    ->url(fn (CreditNote $record): ?string => $record->invoice
                                        ? route('filament.admin.resources.finance.invoices.view', ['record' => $record->invoice])
                                        : null)
                                    ->color('primary')
                                    ->placeholder('N/A'),
                                TextEntry::make('original_invoice_type')
                                    ->label('Invoice Type')
                                    ->getStateUsing(fn (CreditNote $record): string => $this->formatInvoiceType($record))
                                    ->badge()
                                    ->color(fn (CreditNote $record): string => $this->getInvoiceTypeColor($record)),
                                TextEntry::make('invoice.status')
                                    ->label('Invoice Status')
                                    ->badge()
                                    ->formatStateUsing(fn (CreditNote $record): string => $record->invoice !== null
                                        ? $record->invoice->status->label()
                                        : 'N/A')
                                    ->color(fn (CreditNote $record): string => $record->invoice !== null
                                        ? $record->invoice->status->color()
                                        : 'gray')
                                    ->placeholder('N/A'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('invoice.subtotal')
                                    ->label('Invoice Subtotal')
                                    ->money(fn (CreditNote $record): string => $record->invoice !== null
                                        ? $record->invoice->currency
                                        : $record->currency)
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.tax_amount')
                                    ->label('Invoice Tax')
                                    ->money(fn (CreditNote $record): string => $record->invoice !== null
                                        ? $record->invoice->currency
                                        : $record->currency)
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.total_amount')
                                    ->label('Invoice Total')
                                    ->money(fn (CreditNote $record): string => $record->invoice !== null
                                        ? $record->invoice->currency
                                        : $record->currency)
                                    ->weight(FontWeight::Bold)
                                    ->placeholder('N/A'),
                                TextEntry::make('credit_percentage')
                                    ->label('Credit Percentage')
                                    ->getStateUsing(fn (CreditNote $record): string => $this->calculateCreditPercentage($record))
                                    ->badge()
                                    ->color(fn (CreditNote $record): string => $this->getCreditPercentageColor($record)),
                            ]),
                    ]),
                Section::make('Invoice Dates')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('invoice.issued_at')
                                    ->label('Invoice Issued At')
                                    ->dateTime()
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.due_date')
                                    ->label('Invoice Due Date')
                                    ->date()
                                    ->placeholder('N/A'),
                                TextEntry::make('invoice.created_at')
                                    ->label('Invoice Created At')
                                    ->dateTime()
                                    ->placeholder('N/A'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Reason - full reason text.
     */
    protected function getReasonTab(): Tab
    {
        return Tab::make('Reason')
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->schema([
                Section::make('Credit Note Reason')
                    ->description('The reason provided for this credit note')
                    ->schema([
                        TextEntry::make('reason')
                            ->label('')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
                Section::make('Additional Context')
                    ->description('When and by whom this credit note was created')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                TextEntry::make('issuedByUser.name')
                                    ->label('Issued By')
                                    ->placeholder('Not yet issued'),
                                TextEntry::make('issued_at')
                                    ->label('Issued At')
                                    ->dateTime()
                                    ->placeholder('Not yet issued'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 3: Application - applied_at, related refund (if any).
     */
    protected function getApplicationTab(): Tab
    {
        /** @var CreditNote $record */
        $record = $this->record;
        $refundsCount = $record->refunds()->count();

        return Tab::make('Application')
            ->icon('heroicon-o-arrow-path')
            ->badge($refundsCount > 0 ? (string) $refundsCount : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Application Status')
                    ->description('How this credit note has been applied')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (CreditNoteStatus $state): string => $state->label())
                                    ->color(fn (CreditNoteStatus $state): string => $state->color())
                                    ->icon(fn (CreditNoteStatus $state): string => $state->icon()),
                                TextEntry::make('applied_at')
                                    ->label('Applied At')
                                    ->dateTime()
                                    ->placeholder('Not applied'),
                                TextEntry::make('application_status_description')
                                    ->label('Application Status')
                                    ->getStateUsing(fn (CreditNote $record): string => $this->getApplicationStatusDescription($record))
                                    ->color(fn (CreditNote $record): string => $this->getApplicationStatusColor($record)),
                            ]),
                    ]),
                Section::make('Related Refunds')
                    ->description('Refunds associated with this credit note')
                    ->schema([
                        RepeatableEntry::make('refunds')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Refund ID')
                                            ->weight(FontWeight::Bold)
                                            ->url(fn ($record): ?string => $record
                                                ? route('filament.admin.resources.finance.refunds.view', ['record' => $record])
                                                : null)
                                            ->color('primary'),
                                        TextEntry::make('amount')
                                            ->label('Amount')
                                            ->money(fn ($record): string => $record->currency)
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('method')
                                            ->label('Method')
                                            ->badge()
                                            ->formatStateUsing(fn ($record): string => $record->method->label())
                                            ->color(fn ($record): string => $record->method->color()),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn ($record): string => $record->status->label())
                                            ->color(fn ($record): string => $record->status->color()),
                                        TextEntry::make('processed_at')
                                            ->label('Processed At')
                                            ->dateTime()
                                            ->placeholder('Pending'),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No refunds associated with this credit note'),
                    ]),
            ]);
    }

    /**
     * Tab 4: Accounting - xero_credit_note_id, sync status.
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
                                TextEntry::make('xero_credit_note_id')
                                    ->label('Xero Credit Note ID')
                                    ->copyable()
                                    ->copyMessage('Xero Credit Note ID copied')
                                    ->placeholder('Not synced with Xero'),
                                TextEntry::make('xero_synced_at')
                                    ->label('Last Synced')
                                    ->dateTime()
                                    ->placeholder('Never synced'),
                                TextEntry::make('xero_sync_status')
                                    ->label('Sync Status')
                                    ->getStateUsing(fn (CreditNote $record): string => $this->getXeroSyncStatus($record))
                                    ->badge()
                                    ->color(fn (CreditNote $record): string => $this->getXeroSyncStatusColor($record)),
                            ]),
                    ]),
                Section::make('Financial Reference')
                    ->description('Accounting and financial tracking information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('credit_note_number')
                                    ->label('Statutory Credit Note Number')
                                    ->placeholder('Not issued')
                                    ->helperText('Sequential number generated at issuance'),
                                TextEntry::make('original_invoice_type_for_reporting')
                                    ->label('Original Invoice Type')
                                    ->getStateUsing(fn (CreditNote $record): string => $this->formatInvoiceTypeForReporting($record))
                                    ->helperText('For reporting by invoice type'),
                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ]),
                Section::make('Amounts Summary')
                    ->description('Financial summary for this credit note')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount')
                                    ->label('Credit Note Amount')
                                    ->money(fn (CreditNote $record): string => $record->currency)
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('invoice.total_amount')
                                    ->label('Original Invoice Total')
                                    ->money(fn (CreditNote $record): string => $record->invoice !== null
                                        ? $record->invoice->currency
                                        : $record->currency)
                                    ->placeholder('N/A'),
                                TextEntry::make('net_invoice_amount')
                                    ->label('Net Invoice Amount')
                                    ->getStateUsing(fn (CreditNote $record): string => $this->calculateNetInvoiceAmount($record))
                                    ->money(fn (CreditNote $record): string => $record->currency)
                                    ->helperText('Invoice total minus credit note'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - timeline.
     */
    protected function getAuditTab(): Tab
    {
        /** @var CreditNote $record */
        $record = $this->record;
        $auditCount = $record->auditLogs()->count();

        return Tab::make('Audit')
            ->icon('heroicon-o-clock')
            ->badge($auditCount > 0 ? (string) $auditCount : null)
            ->badgeColor('gray')
            ->schema([
                Section::make('Audit Timeline')
                    ->description('Immutable record of all changes to this credit note')
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
     * Format the invoice type for display.
     */
    protected function formatInvoiceType(CreditNote $record): string
    {
        $type = $record->getOriginalInvoiceType();

        if ($type === null) {
            return 'N/A';
        }

        return $type->code().' - '.$type->label();
    }

    /**
     * Get the invoice type color.
     */
    protected function getInvoiceTypeColor(CreditNote $record): string
    {
        $type = $record->getOriginalInvoiceType();

        if ($type === null) {
            return 'gray';
        }

        return $type->color();
    }

    /**
     * Format the invoice type for reporting.
     */
    protected function formatInvoiceTypeForReporting(CreditNote $record): string
    {
        $type = $record->getOriginalInvoiceType();

        if ($type === null) {
            return 'Unknown';
        }

        return $type->code().' ('.$type->label().')';
    }

    /**
     * Calculate the credit percentage relative to invoice total.
     */
    protected function calculateCreditPercentage(CreditNote $record): string
    {
        if ($record->invoice === null) {
            return 'N/A';
        }

        $invoiceTotal = (float) $record->invoice->total_amount;

        if ($invoiceTotal <= 0) {
            return 'N/A';
        }

        $creditAmount = (float) $record->amount;
        $percentage = ($creditAmount / $invoiceTotal) * 100;

        return number_format($percentage, 1).'%';
    }

    /**
     * Get color for credit percentage badge.
     */
    protected function getCreditPercentageColor(CreditNote $record): string
    {
        if ($record->invoice === null) {
            return 'gray';
        }

        $invoiceTotal = (float) $record->invoice->total_amount;

        if ($invoiceTotal <= 0) {
            return 'gray';
        }

        $creditAmount = (float) $record->amount;
        $percentage = ($creditAmount / $invoiceTotal) * 100;

        if ($percentage >= 100) {
            return 'danger';
        }
        if ($percentage >= 50) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Get the application status description.
     */
    protected function getApplicationStatusDescription(CreditNote $record): string
    {
        return match ($record->status) {
            CreditNoteStatus::Draft => 'Credit note is in draft. Issue it to apply to the invoice.',
            CreditNoteStatus::Issued => 'Credit note has been issued and is ready to be applied.',
            CreditNoteStatus::Applied => 'Credit note has been fully applied to the invoice.',
        };
    }

    /**
     * Get the application status color.
     */
    protected function getApplicationStatusColor(CreditNote $record): string
    {
        return match ($record->status) {
            CreditNoteStatus::Draft => 'gray',
            CreditNoteStatus::Issued => 'warning',
            CreditNoteStatus::Applied => 'success',
        };
    }

    /**
     * Get the Xero sync status.
     */
    protected function getXeroSyncStatus(CreditNote $record): string
    {
        if ($record->xero_credit_note_id !== null) {
            return 'Synced';
        }

        if ($record->isDraft()) {
            return 'Draft - Not eligible';
        }

        return 'Pending';
    }

    /**
     * Get the Xero sync status color.
     */
    protected function getXeroSyncStatusColor(CreditNote $record): string
    {
        if ($record->xero_credit_note_id !== null) {
            return 'success';
        }

        if ($record->isDraft()) {
            return 'gray';
        }

        return 'warning';
    }

    /**
     * Calculate the net invoice amount after credit.
     */
    protected function calculateNetInvoiceAmount(CreditNote $record): string
    {
        if ($record->invoice === null) {
            return $record->amount;
        }

        $invoiceTotal = $record->invoice->total_amount;
        $creditAmount = $record->amount;

        return bcsub($invoiceTotal, $creditAmount, 2);
    }

    /**
     * Format audit log changes for display.
     */
    protected function formatAuditChanges(AuditLog $log): string
    {
        $changes = [];

        if ($log->event === AuditLog::EVENT_CREATED) {
            return '<span class="text-success-600">Credit note created</span>';
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
     * Get the current credit note record.
     */
    protected function getCreditNote(): CreditNote
    {
        /** @var CreditNote $record */
        $record = $this->record;

        return $record;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getIssueAction(),
        ];
    }

    /**
     * Issue action - Issues a draft credit note.
     *
     * Visible only when credit note is in draft status.
     * Generates sequential credit_note_number (format: CN-YYYY-NNNNNN),
     * sets issued_at, triggers Xero sync, and updates invoice status if fully credited.
     */
    protected function getIssueAction(): Action
    {
        return Action::make('issue')
            ->label('Issue Credit Note')
            ->icon('heroicon-o-document-check')
            ->color('success')
            ->visible(fn (): bool => $this->getCreditNote()->isDraft())
            ->requiresConfirmation()
            ->modalHeading('Issue Credit Note')
            ->modalDescription(fn (): string => $this->getIssueConfirmationMessage())
            ->modalIcon('heroicon-o-document-check')
            ->modalIconColor('success')
            ->modalSubmitActionLabel('Issue Credit Note')
            ->action(function (): void {
                $creditNote = $this->getCreditNote();

                try {
                    /** @var CreditNoteService $service */
                    $service = app(CreditNoteService::class);
                    $service->issue($creditNote);

                    Notification::make()
                        ->title('Credit Note Issued')
                        ->body("Credit note {$creditNote->credit_note_number} has been issued successfully.")
                        ->success()
                        ->send();

                    // Refresh the page to show updated data
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $creditNote]));
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Failed to Issue Credit Note')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Get the confirmation message for the Issue action.
     */
    protected function getIssueConfirmationMessage(): string
    {
        $creditNote = $this->getCreditNote();
        $invoice = $creditNote->invoice;

        $message = "You are about to issue this credit note for {$creditNote->getFormattedAmount()}.";

        if ($invoice !== null) {
            $message .= " This credit note is against invoice {$invoice->invoice_number}.";

            // Check if this would fully credit the invoice
            /** @var CreditNoteService $service */
            $service = app(CreditNoteService::class);
            $totalCredited = $service->getTotalCreditedAmount($invoice);
            $newTotal = bcadd($totalCredited, $creditNote->amount, 2);

            if (bccomp($newTotal, $invoice->total_amount, 2) >= 0) {
                $message .= "\n\n**Note:** This will mark the invoice as fully credited.";
            }
        }

        $message .= "\n\nOnce issued:\n";
        $message .= "- A sequential credit note number will be generated\n";
        $message .= "- The credit note will be synced to Xero\n";
        $message .= "- The credit note details become immutable\n";
        $message .= "\nAre you sure you want to proceed?";

        return $message;
    }
}
