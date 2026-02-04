<?php

namespace App\Filament\Resources\Finance\InvoiceResource\Pages;

use App\Filament\Resources\Finance\InvoiceResource;
use App\Models\Finance\Invoice;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

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
                Section::make('Invoice Overview')
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Invoice Number')
                            ->placeholder('Draft - Not yet issued'),

                        TextEntry::make('invoice_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state->code().' - '.$state->label())
                            ->color(fn ($state): string => $state->color()),

                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->url(fn (Invoice $record): ?string => $record->customer
                                ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
                                : null),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state->label())
                            ->color(fn ($state): string => $state->color()),

                        TextEntry::make('currency')
                            ->label('Currency'),

                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money(fn (Invoice $record): string => $record->currency),

                        TextEntry::make('tax_amount')
                            ->label('Tax Amount')
                            ->money(fn (Invoice $record): string => $record->currency),

                        TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->money(fn (Invoice $record): string => $record->currency)
                            ->weight('bold'),

                        TextEntry::make('amount_paid')
                            ->label('Amount Paid')
                            ->money(fn (Invoice $record): string => $record->currency),

                        TextEntry::make('outstanding')
                            ->label('Outstanding')
                            ->state(fn (Invoice $record): string => $record->getOutstandingAmount())
                            ->money(fn (Invoice $record): string => $record->currency)
                            ->color(fn (Invoice $record): string => $record->isOverdue() ? 'danger' : 'warning'),

                        TextEntry::make('issued_at')
                            ->label('Issue Date')
                            ->dateTime()
                            ->placeholder('Not issued'),

                        TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date()
                            ->placeholder('N/A')
                            ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null),

                        TextEntry::make('xero_invoice_id')
                            ->label('Xero Invoice ID')
                            ->placeholder('Not synced'),

                        TextEntry::make('xero_synced_at')
                            ->label('Last Xero Sync')
                            ->dateTime()
                            ->placeholder('Never'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Actions will be implemented in US-E015
        ];
    }
}
