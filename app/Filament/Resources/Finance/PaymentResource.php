<?php

namespace App\Filament\Resources\Finance;

use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Filament\Resources\Finance\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\Finance\PaymentResource\Pages\ViewPayment;
use App\Models\Finance\Payment;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
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

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $modelLabel = 'Payment';

    protected static ?string $pluralModelLabel = 'Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Form schema will be implemented in later stories
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Payment reference copied'),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (PaymentSource $state): string => $state->label())
                    ->color(fn (PaymentSource $state): string => $state->color())
                    ->icon(fn (PaymentSource $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn (Payment $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->icon(fn (PaymentStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('reconciliation_status')
                    ->label('Reconciliation')
                    ->badge()
                    ->formatStateUsing(fn (ReconciliationStatus $state): string => $state->label())
                    ->color(fn (ReconciliationStatus $state): string => $state->color())
                    ->icon(fn (ReconciliationStatus $state): string => $state->icon())
                    ->sortable()
                    ->tooltip(fn (Payment $record): ?string => $record->hasMismatch()
                        ? 'Mismatch: '.$record->getMismatchReason()
                        : null),

                TextColumn::make('mismatch_type')
                    ->label('Issue')
                    ->getStateUsing(fn (Payment $record): ?string => $record->hasMismatch()
                        ? $record->getMismatchTypeLabel()
                        : null)
                    ->badge()
                    ->color(fn (Payment $record): string => $record->hasMismatch() ? 'danger' : 'gray')
                    ->icon(fn (Payment $record): string => $record->hasMismatch()
                        ? 'heroicon-o-exclamation-triangle'
                        : 'heroicon-o-check')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->placeholder('Unassigned')
                    ->url(fn (Payment $record): ?string => $record->customer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('received_at')
                    ->label('Received At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('stripe_payment_intent_id')
                    ->label('Stripe PI')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('N/A'),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->options(collect(PaymentSource::cases())
                        ->mapWithKeys(fn (PaymentSource $source) => [$source->value => $source->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Source'),

                SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())
                        ->mapWithKeys(fn (PaymentStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('reconciliation_status')
                    ->options(collect(ReconciliationStatus::cases())
                        ->mapWithKeys(fn (ReconciliationStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Reconciliation Status'),

                Filter::make('received_at')
                    ->schema([
                        DatePicker::make('received_from')
                            ->label('Received From'),
                        DatePicker::make('received_until')
                            ->label('Received Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['received_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('received_at', '>=', $date)
                            )
                            ->when(
                                $data['received_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('received_at', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['received_from'] ?? null) {
                            $indicators['received_from'] = 'Received from '.$data['received_from'];
                        }
                        if ($data['received_until'] ?? null) {
                            $indicators['received_until'] = 'Received until '.$data['received_until'];
                        }

                        return $indicators;
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
                                    'Payment Reference',
                                    'Source',
                                    'Amount',
                                    'Currency',
                                    'Status',
                                    'Reconciliation Status',
                                    'Customer',
                                    'Stripe Payment Intent ID',
                                    'Bank Reference',
                                    'Received At',
                                ]);
                                foreach ($records as $record) {
                                    /** @var Payment $record */
                                    fputcsv($handle, [
                                        $record->payment_reference,
                                        $record->source->label(),
                                        $record->amount,
                                        $record->currency,
                                        $record->status->label(),
                                        $record->reconciliation_status->label(),
                                        $record->customer !== null ? $record->customer->name : 'Unassigned',
                                        $record->stripe_payment_intent_id,
                                        $record->bank_reference,
                                        $record->received_at->format('Y-m-d H:i:s'),
                                    ]);
                                }
                                fclose($handle);
                            }
                        }, 'payments-'.now()->format('Y-m-d').'.csv');
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('received_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
            ]));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in later stories
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
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
        return ['payment_reference', 'stripe_payment_intent_id', 'customer.name', 'customer.email'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Payment $record */
        return $record->payment_reference;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Payment $record */
        return [
            'Customer' => $record->customer !== null ? $record->customer->name : 'Unassigned',
            'Amount' => $record->getFormattedAmount(),
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }
}
