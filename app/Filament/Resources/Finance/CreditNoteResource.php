<?php

namespace App\Filament\Resources\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceType;
use App\Filament\Resources\Finance\CreditNoteResource\Pages\ListCreditNotes;
use App\Filament\Resources\Finance\CreditNoteResource\Pages\ViewCreditNote;
use App\Models\Finance\CreditNote;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CreditNoteResource extends Resource
{
    protected static ?string $model = CreditNote::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Credit Notes';

    protected static ?string $modelLabel = 'Credit Note';

    protected static ?string $pluralModelLabel = 'Credit Notes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Form schema will be implemented in US-E065
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('credit_note_number')
                    ->label('Credit Note #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Credit note number copied')
                    ->placeholder('Draft'),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->url(fn (CreditNote $record): ?string => $record->invoice
                        ? route('filament.admin.resources.finance.invoices.view', ['record' => $record->invoice])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('N/A'),

                TextColumn::make('original_invoice_type')
                    ->label('Invoice Type')
                    ->badge()
                    ->formatStateUsing(fn (?InvoiceType $state): string => $state !== null ? $state->code().' - '.$state->label() : 'N/A')
                    ->color(fn (?InvoiceType $state): string => $state !== null ? $state->color() : 'gray')
                    ->icon(fn (?InvoiceType $state): ?string => $state !== null ? $state->icon() : null)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (CreditNote $record): ?string => $record->customer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn (CreditNote $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CreditNoteStatus $state): string => $state->label())
                    ->color(fn (CreditNoteStatus $state): string => $state->color())
                    ->icon(fn (CreditNoteStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->label('Issued At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Not issued'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(fn (CreditNote $record): string => $record->reason)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CreditNoteStatus::cases())
                        ->mapWithKeys(fn (CreditNoteStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('original_invoice_type')
                    ->options(collect(InvoiceType::cases())
                        ->mapWithKeys(fn (InvoiceType $type) => [$type->value => $type->code().' - '.$type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Original Invoice Type'),

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
                                    'Credit Note Number',
                                    'Invoice Number',
                                    'Original Invoice Type',
                                    'Customer',
                                    'Amount',
                                    'Currency',
                                    'Status',
                                    'Issued At',
                                    'Applied At',
                                    'Reason',
                                    'Xero Credit Note ID',
                                ]);
                                foreach ($records as $record) {
                                    /** @var CreditNote $record */
                                    $originalType = $record->getOriginalInvoiceType();
                                    fputcsv($handle, [
                                        $record->credit_note_number ?? 'Draft',
                                        $record->invoice !== null ? $record->invoice->invoice_number : 'N/A',
                                        $originalType !== null ? $originalType->code().' - '.$originalType->label() : 'N/A',
                                        $record->customer !== null ? $record->customer->name : 'N/A',
                                        $record->amount,
                                        $record->currency,
                                        $record->status->label(),
                                        $record->issued_at?->format('Y-m-d H:i:s'),
                                        $record->applied_at?->format('Y-m-d H:i:s'),
                                        $record->reason,
                                        $record->xero_credit_note_id,
                                    ]);
                                }
                                fclose($handle);
                            }
                        }, 'credit-notes-'.now()->format('Y-m-d').'.csv');
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
                'invoice',
            ]));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-E064
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditNotes::route('/'),
            'view' => ViewCreditNote::route('/{record}'),
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
        return ['credit_note_number', 'invoice.invoice_number', 'customer.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'invoice']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var CreditNote $record */
        return $record->credit_note_number ?? 'Draft Credit Note #'.$record->id;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var CreditNote $record */
        return [
            'Invoice' => $record->invoice !== null ? $record->invoice->invoice_number : 'N/A',
            'Customer' => $record->customer !== null ? $record->customer->name : 'N/A',
            'Amount' => $record->getFormattedAmount(),
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }
}
