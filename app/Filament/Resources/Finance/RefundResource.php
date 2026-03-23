<?php

namespace App\Filament\Resources\Finance;

use App\Enums\Finance\RefundMethod;
use App\Enums\Finance\RefundStatus;
use App\Enums\Finance\RefundType;
use App\Filament\Resources\Finance\RefundResource\Pages\CreateRefund;
use App\Filament\Resources\Finance\RefundResource\Pages\EditRefund;
use App\Filament\Resources\Finance\RefundResource\Pages\ListRefunds;
use App\Filament\Resources\Finance\RefundResource\Pages\ViewRefund;
use App\Models\Finance\Refund;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Refunds';

    protected static ?string $modelLabel = 'Refund';

    protected static ?string $pluralModelLabel = 'Refunds';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Refund Links')
                    ->columns(2)
                    ->schema([
                        Select::make('invoice_id')
                            ->label('Invoice')
                            ->relationship('invoice', 'invoice_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->disabled(fn (?Refund $record): bool => $record !== null),

                        Select::make('payment_id')
                            ->label('Payment')
                            ->relationship('payment', 'payment_reference')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Refund $record): bool => $record !== null),

                        Select::make('credit_note_id')
                            ->label('Credit Note')
                            ->relationship('creditNote', 'credit_note_number')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn (?Refund $record): bool => $record !== null && ! $record->isPending()),
                    ]),

                Section::make('Refund Details')
                    ->columns(2)
                    ->schema([
                        Select::make('refund_type')
                            ->label('Refund Type')
                            ->options(collect(RefundType::cases())
                                ->mapWithKeys(fn (RefundType $e) => [$e->value => $e->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Refund $record): bool => $record !== null && ! $record->isPending()),

                        Select::make('method')
                            ->label('Method')
                            ->options(collect(RefundMethod::cases())
                                ->mapWithKeys(fn (RefundMethod $e) => [$e->value => $e->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Refund $record): bool => $record !== null && ! $record->isPending()),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->disabled(fn (?Refund $record): bool => $record !== null && ! $record->isPending()),

                        TextInput::make('currency')
                            ->label('Currency')
                            ->default('EUR')
                            ->disabled(),
                    ]),

                Section::make('Reason')
                    ->columns(1)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(2000)
                            ->disabled(fn (?Refund $record): bool => $record !== null && ! $record->isPending()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Refund ID')
                    ->sortable()
                    ->searchable(isIndividual: false)
                    ->prefix('#'),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Refund $record): ?string => $record->invoice
                        ? route('filament.admin.resources.finance.invoices.view', ['record' => $record->invoice])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('N/A'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn (Refund $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (RefundMethod $state): string => $state->label())
                    ->color(fn (RefundMethod $state): string => $state->color())
                    ->icon(fn (RefundMethod $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RefundStatus $state): string => $state->label())
                    ->color(fn (RefundStatus $state): string => $state->color())
                    ->icon(fn (RefundStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Not processed'),

                TextColumn::make('stripe_refund_id')
                    ->label('Stripe Refund ID')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('Stripe refund ID copied')
                    ->placeholder('N/A'),

                TextColumn::make('refund_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color())
                    ->icon(fn ($state): string => $state->icon())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->options(collect(RefundMethod::cases())
                        ->mapWithKeys(fn (RefundMethod $method) => [$method->value => $method->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Method'),

                SelectFilter::make('status')
                    ->options(collect(RefundStatus::cases())
                        ->mapWithKeys(fn (RefundStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                Filter::make('processed_at')
                    ->schema([
                        DatePicker::make('processed_from')
                            ->label('Processed From'),
                        DatePicker::make('processed_until')
                            ->label('Processed Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['processed_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('processed_at', '>=', $date)
                            )
                            ->when(
                                $data['processed_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('processed_at', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['processed_from'] ?? null) {
                            $indicators['processed_from'] = 'Processed from '.$data['processed_from'];
                        }
                        if ($data['processed_until'] ?? null) {
                            $indicators['processed_until'] = 'Processed until '.$data['processed_until'];
                        }

                        return $indicators;
                    }),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label('Created From'),
                        DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Created from '.$data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Created until '.$data['created_until'];
                        }

                        return $indicators;
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Refund $record): bool => $record->isPending()),
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
                                    'Refund ID',
                                    'Invoice Number',
                                    'Payment Reference',
                                    'Amount',
                                    'Currency',
                                    'Type',
                                    'Method',
                                    'Status',
                                    'Reason',
                                    'Stripe Refund ID',
                                    'Bank Reference',
                                    'Processed At',
                                    'Created At',
                                ]);
                                foreach ($records as $record) {
                                    /** @var Refund $record */
                                    fputcsv($handle, [
                                        $record->id,
                                        $record->invoice !== null ? $record->invoice->invoice_number : 'N/A',
                                        $record->payment !== null ? $record->payment->payment_reference : 'N/A',
                                        $record->amount,
                                        $record->currency,
                                        $record->refund_type->label(),
                                        $record->method->label(),
                                        $record->status->label(),
                                        $record->reason,
                                        $record->stripe_refund_id,
                                        $record->bank_reference,
                                        $record->processed_at?->format('Y-m-d H:i:s'),
                                        $record->created_at->format('Y-m-d H:i:s'),
                                    ]);
                                }
                                fclose($handle);
                            }
                        }, 'refunds-'.now()->format('Y-m-d').'.csv');
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'invoice',
                'payment',
            ]));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-E069
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
            'create' => CreateRefund::route('/create'),
            'view' => ViewRefund::route('/{record}'),
            'edit' => EditRefund::route('/{record}/edit'),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Finance\Refund> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice.invoice_number', 'stripe_refund_id'];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Finance\Refund> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['invoice', 'payment']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Refund $record */
        return 'Refund #'.$record->id;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Refund $record */
        return [
            'Invoice' => $record->invoice !== null ? ($record->invoice->invoice_number ?? 'N/A') : 'N/A',
            'Amount' => $record->getFormattedAmount(),
            'Method' => $record->method->label(),
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }
}
