<?php

namespace App\Filament\Resources\Finance;

use App\Enums\Finance\StorageBillingStatus;
use App\Filament\Resources\Finance\StorageBillingResource\Pages\EditStorageBilling;
use App\Filament\Resources\Finance\StorageBillingResource\Pages\ListStorageBilling;
use App\Filament\Resources\Finance\StorageBillingResource\Pages\ViewStorageBilling;
use App\Models\Finance\StorageBillingPeriod;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

class StorageBillingResource extends Resource
{
    protected static ?string $model = StorageBillingPeriod::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 70;

    protected static ?string $navigationLabel = 'Storage Billing';

    protected static ?string $modelLabel = 'Storage Billing Period';

    protected static ?string $pluralModelLabel = 'Storage Billing Periods';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Storage Billing Edit')
                    ->description('Only editable while billing period is in Pending status.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('unit_rate')
                            ->label('Unit Rate')
                            ->numeric()
                            ->step(0.0001)
                            ->prefix('EUR')
                            ->disabled(fn (?StorageBillingPeriod $record): bool => $record !== null && ! $record->isPending()),

                        TextInput::make('calculated_amount')
                            ->label('Calculated Amount')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('EUR')
                            ->disabled(fn (?StorageBillingPeriod $record): bool => $record !== null && ! $record->isPending()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period')
                    ->label('Period')
                    ->getStateUsing(fn (StorageBillingPeriod $record): string => $record->getPeriodLabel())
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('period_start', $direction);
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('period_start', 'like', "%{$search}%")
                                ->orWhere('period_end', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (StorageBillingPeriod $record): ?string => $record->customer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('bottle_count')
                    ->label('Bottles')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('bottle_days')
                    ->label('Bottle Days')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(fn (StorageBillingPeriod $record): string => number_format($record->getAverageBottlesPerDay(), 1).' avg/day'),

                TextColumn::make('calculated_amount')
                    ->label('Amount')
                    ->money(fn (StorageBillingPeriod $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (StorageBillingStatus $state): string => $state->label())
                    ->color(fn (StorageBillingStatus $state): string => $state->color())
                    ->icon(fn (StorageBillingStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('Not invoiced')
                    ->url(fn (StorageBillingPeriod $record): ?string => $record->invoice
                        ? route('filament.admin.resources.finance.invoices.view', ['record' => $record->invoice])
                        : null)
                    ->color('primary'),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('All locations')
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('calculated_at')
                    ->label('Calculated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('period_from')
                            ->label('Period From'),
                        DatePicker::make('period_to')
                            ->label('Period To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['period_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('period_start', '>=', $date)
                            )
                            ->when(
                                $data['period_to'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('period_end', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['period_from'] ?? null) {
                            $indicators['period_from'] = 'Period from '.$data['period_from'];
                        }
                        if ($data['period_to'] ?? null) {
                            $indicators['period_to'] = 'Period to '.$data['period_to'];
                        }

                        return $indicators;
                    }),

                SelectFilter::make('status')
                    ->options(collect(StorageBillingStatus::cases())
                        ->mapWithKeys(fn (StorageBillingStatus $status) => [$status->value => $status->label()])
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

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (StorageBillingPeriod $record): bool => $record->isPending()),
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
                                    'Period Start',
                                    'Period End',
                                    'Customer',
                                    'Location',
                                    'Bottle Count',
                                    'Bottle Days',
                                    'Unit Rate',
                                    'Amount',
                                    'Currency',
                                    'Status',
                                    'Invoice #',
                                ]);
                                foreach ($records as $record) {
                                    /** @var StorageBillingPeriod $record */
                                    fputcsv($handle, [
                                        $record->period_start->format('Y-m-d'),
                                        $record->period_end->format('Y-m-d'),
                                        $record->customer !== null ? $record->customer->name : 'N/A',
                                        $record->location !== null ? $record->location->name : 'All',
                                        $record->bottle_count,
                                        $record->bottle_days,
                                        $record->unit_rate,
                                        $record->calculated_amount,
                                        $record->currency,
                                        $record->status->label(),
                                        $record->invoice !== null ? $record->invoice->invoice_number : '',
                                    ]);
                                }
                                fclose($handle);
                            }
                        }, 'storage-billing-'.now()->format('Y-m-d').'.csv');
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('period_start', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
                'invoice',
                'location',
            ]));
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'customer.name'];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Finance\StorageBillingPeriod> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var StorageBillingPeriod $record */
        return 'Storage Billing #'.substr($record->id, 0, 8);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var StorageBillingPeriod $record */
        return [
            'Customer' => $record->customer !== null ? ($record->customer->name ?? 'N/A') : 'N/A',
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-E088
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorageBilling::route('/'),
            'view' => ViewStorageBilling::route('/{record}'),
            'edit' => EditStorageBilling::route('/{record}/edit'),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Finance\StorageBillingPeriod> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Get the current billing period start date.
     */
    public static function getCurrentPeriodStart(): Carbon
    {
        // Assume monthly billing periods starting on the 1st
        return Carbon::now()->startOfMonth();
    }

    /**
     * Get the current billing period end date.
     */
    public static function getCurrentPeriodEnd(): Carbon
    {
        return Carbon::now()->endOfMonth();
    }
}
