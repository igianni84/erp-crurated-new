<?php

namespace App\Filament\Resources\Customer\CustomerResource\RelationManagers;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Finance\Invoice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('Draft'),

                TextColumn::make('invoice_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceType $state): string => $state->code())
                    ->color(fn (InvoiceType $state): string => $state->color())
                    ->icon(fn (InvoiceType $state): string => $state->icon())
                    ->tooltip(fn (InvoiceType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())
                    ->color(fn (InvoiceStatus $state): string => $state->color())
                    ->icon(fn (InvoiceStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money(fn (Invoice $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->placeholder('N/A')
                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('invoice_type')
                    ->options(collect(InvoiceType::cases())
                        ->mapWithKeys(fn (InvoiceType $type) => [$type->value => $type->code().' - '.$type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Type'),

                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())
                        ->mapWithKeys(fn (InvoiceStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
