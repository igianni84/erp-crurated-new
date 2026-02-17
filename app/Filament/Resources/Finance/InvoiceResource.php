<?php

namespace App\Filament\Resources\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Filament\Resources\Finance\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\Finance\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\Finance\InvoiceResource\Pages\ViewInvoice;
use App\Models\Finance\Invoice;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Form schema will be implemented in US-E018
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Invoice number copied')
                    ->placeholder('Draft'),

                TextColumn::make('invoice_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceType $state): string => $state->code())
                    ->color(fn (InvoiceType $state): string => $state->color())
                    ->icon(fn (InvoiceType $state): string => $state->icon())
                    ->tooltip(fn (InvoiceType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (Invoice $record): ?string => $record->customer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money(fn (Invoice $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd()
                    ->description(fn (Invoice $record): string => $record->currency !== 'EUR'
                        ? $record->currency.' ('.$record->getCurrencySymbol().')'
                        : ''),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())
                    ->color(fn (InvoiceStatus $state): string => $state->color())
                    ->icon(fn (InvoiceStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->label('Issue Date')
                    ->date()
                    ->sortable()
                    ->placeholder('Not issued'),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->placeholder('N/A')
                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null),

                TextColumn::make('xero_invoice_id')
                    ->label('Xero Ref')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('Not synced'),

                TextColumn::make('flags')
                    ->label('Flags')
                    ->state(function (Invoice $record): string {
                        $flags = [];
                        if ($record->isOverdue()) {
                            $flags[] = 'Overdue';
                        }

                        return implode(', ', $flags);
                    })
                    ->badge()
                    ->separator(',')
                    ->color('danger')
                    ->visible(fn (): bool => true)
                    ->placeholder(''),
            ])
            ->filters([
                SelectFilter::make('invoice_type')
                    ->options(collect(InvoiceType::cases())
                        ->mapWithKeys(fn (InvoiceType $type) => [$type->value => $type->code().' - '.$type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Invoice Type'),

                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())
                        ->mapWithKeys(fn (InvoiceStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                Filter::make('customer')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['customer_id'] ?? null,
                            fn (Builder $query, string $customerId): Builder => $query->where('customer_id', $customerId)
                        );
                    }),

                Filter::make('issued_at')
                    ->schema([
                        DatePicker::make('issued_from')
                            ->label('Issued From'),
                        DatePicker::make('issued_until')
                            ->label('Issued Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '>=', $date)
                            )
                            ->when(
                                $data['issued_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['issued_from'] ?? null) {
                            $indicators['issued_from'] = 'Issued from '.$data['issued_from'];
                        }
                        if ($data['issued_until'] ?? null) {
                            $indicators['issued_until'] = 'Issued until '.$data['issued_until'];
                        }

                        return $indicators;
                    }),

                SelectFilter::make('currency')
                    ->options(Invoice::getSupportedCurrencies())
                    ->label('Currency'),

                TernaryFilter::make('overdue')
                    ->label('Overdue Status')
                    ->placeholder('All invoices')
                    ->trueLabel('Overdue only')
                    ->falseLabel('Not overdue')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->where('status', InvoiceStatus::Issued)
                            ->whereNotNull('due_date')
                            ->where('due_date', '<', now()->startOfDay()),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                            $q->where('status', '!=', InvoiceStatus::Issued)
                                ->orWhereNull('due_date')
                                ->orWhere('due_date', '>=', now()->startOfDay());
                        }),
                    ),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('export_csv')
                    ->label('Export to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records): StreamedResponse {
                        return response()->streamDownload(function () use ($records): void {
                            $handle = fopen('php://output', 'w');
                            if ($handle !== false) {
                                fputcsv($handle, [
                                    'Invoice Number',
                                    'Type',
                                    'Customer',
                                    'Currency',
                                    'Subtotal',
                                    'Tax Amount',
                                    'Total Amount',
                                    'Amount Paid',
                                    'Status',
                                    'Issued At',
                                    'Due Date',
                                    'Xero Invoice ID',
                                ]);
                                foreach ($records as $record) {
                                    /** @var Invoice $record */
                                    fputcsv($handle, [
                                        $record->invoice_number ?? 'Draft',
                                        $record->invoice_type->code(),
                                        $record->customer !== null ? $record->customer->name : 'N/A',
                                        $record->currency,
                                        $record->subtotal,
                                        $record->tax_amount,
                                        $record->total_amount,
                                        $record->amount_paid,
                                        $record->status->label(),
                                        $record->issued_at?->format('Y-m-d'),
                                        $record->due_date?->format('Y-m-d'),
                                        $record->xero_invoice_id,
                                    ]);
                                }
                                fclose($handle);
                            }
                        }, 'invoices-'.now()->format('Y-m-d').'.csv');
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('retry_xero_sync')
                    ->label('Retry Xero Sync')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Collection $records): void {
                        // TODO: Implement Xero sync retry in US-E101
                        Notification::make()
                            ->title('Xero sync retry queued')
                            ->body('Sync retry has been queued for '.count($records).' invoice(s).')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
            ]));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-E014
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'xero_invoice_id', 'customer.name', 'customer.email'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Invoice $record */
        return $record->invoice_number ?? 'Draft Invoice #'.$record->id;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Invoice $record */
        return [
            'Customer' => $record->customer !== null ? $record->customer->name : 'N/A',
            'Amount' => $record->getFormattedTotal(),
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }
}
