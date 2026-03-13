<?php

namespace App\Filament\Resources\Finance\InvoiceResource\RelationManagers;

use App\Models\Finance\InvoicePayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoicePayments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment.payment_reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount_applied')
                    ->label('Amount Applied')
                    ->money(fn (InvoicePayment $record): string => $record->invoice !== null ? $record->invoice->currency : 'EUR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('payment.source')
                    ->label('Method')
                    ->badge()
                    ->sortable(),

                TextColumn::make('payment.status')
                    ->label('Payment Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('applied_at')
                    ->label('Applied At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payment', 'invoice']));
    }
}
