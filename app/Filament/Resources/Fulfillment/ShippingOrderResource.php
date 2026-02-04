<?php

namespace App\Filament\Resources\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;
use App\Models\Fulfillment\ShippingOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;

class ShippingOrderResource extends Resource
{
    protected static ?string $model = ShippingOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Fulfillment';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Shipping Orders';

    protected static ?string $modelLabel = 'Shipping Order';

    protected static ?string $pluralModelLabel = 'Shipping Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form will be implemented in US-C018-C022 (creation wizard)
                // For now, provide basic fields for view/edit
                Forms\Components\Section::make('Customer & Destination')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\Select::make('source_warehouse_id')
                            ->label('Source Warehouse')
                            ->relationship('sourceWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\Textarea::make('destination_address')
                            ->label('Destination Address')
                            ->rows(4)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft())
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Shipping Method')
                    ->schema([
                        Forms\Components\TextInput::make('carrier')
                            ->label('Carrier')
                            ->maxLength(255)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\TextInput::make('shipping_method')
                            ->label('Shipping Method')
                            ->maxLength(255)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\Select::make('incoterms')
                            ->label('Incoterms')
                            ->options([
                                'EXW' => 'EXW - Ex Works',
                                'FCA' => 'FCA - Free Carrier',
                                'DDP' => 'DDP - Delivered Duty Paid',
                                'DAP' => 'DAP - Delivered at Place',
                            ])
                            ->native(false)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\DatePicker::make('requested_ship_date')
                            ->label('Requested Ship Date')
                            ->minDate(now())
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Packaging & Instructions')
                    ->schema([
                        Forms\Components\Select::make('packaging_preference')
                            ->label('Packaging Preference')
                            ->options(fn (): array => collect(\App\Enums\Fulfillment\PackagingPreference::cases())
                                ->mapWithKeys(fn (\App\Enums\Fulfillment\PackagingPreference $pref): array => [$pref->value => $pref->label()])
                                ->toArray())
                            ->native(false)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\Textarea::make('special_instructions')
                            ->label('Special Instructions')
                            ->rows(3)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('SO ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('SO ID copied')
                    ->limit(8)
                    ->tooltip(fn (ShippingOrder $record): string => $record->id),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->url(fn (ShippingOrder $record): ?string => $record->customer
                        ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                // Destination country placeholder - will be populated when Module K addresses implemented
                Tables\Columns\TextColumn::make('destination_country')
                    ->label('Destination')
                    ->getStateUsing(fn (ShippingOrder $record): string => $record->shipments->first()?->destination_address ? 'See shipment' : '-')
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Vouchers')
                    ->counts('lines')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('sourceWarehouse.name')
                    ->label('Source Warehouse')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(fn (ShippingOrderStatus $state): string => $state->label())
                    ->color(fn (ShippingOrderStatus $state): string => $state->color())
                    ->icon(fn (ShippingOrderStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_ship_date')
                    ->label('Requested Ship Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('carrier')
                    ->label('Carrier')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ShippingOrderStatus::cases())
                        ->mapWithKeys(fn (ShippingOrderStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->default(fn (): array => [
                        ShippingOrderStatus::Draft->value,
                        ShippingOrderStatus::Planned->value,
                        ShippingOrderStatus::Picking->value,
                        ShippingOrderStatus::Shipped->value,
                        ShippingOrderStatus::OnHold->value,
                    ])
                    ->label('Status'),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Customer'),

                Tables\Filters\SelectFilter::make('source_warehouse_id')
                    ->relationship('sourceWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Source Warehouse'),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
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

                Tables\Filters\SelectFilter::make('carrier')
                    ->options(fn (): array => ShippingOrder::query()
                        ->whereNotNull('carrier')
                        ->distinct()
                        ->pluck('carrier', 'carrier')
                        ->toArray())
                    ->searchable()
                    ->label('Carrier'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (ShippingOrder $record): bool => $record->isDraft()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Export CSV - always available
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records): \Symfony\Component\HttpFoundation\StreamedResponse {
                            return response()->streamDownload(function () use ($records): void {
                                $handle = fopen('php://output', 'w');
                                if ($handle === false) {
                                    return;
                                }

                                // CSV header
                                fputcsv($handle, [
                                    'SO ID',
                                    'Customer',
                                    'Status',
                                    'Voucher Count',
                                    'Source Warehouse',
                                    'Carrier',
                                    'Shipping Method',
                                    'Incoterms',
                                    'Requested Ship Date',
                                    'Packaging Preference',
                                    'Created At',
                                    'Destination Address',
                                    'Special Instructions',
                                ]);

                                // CSV rows
                                foreach ($records as $record) {
                                    /** @var ShippingOrder $record */
                                    $customerName = $record->customer !== null ? $record->customer->name : '-';
                                    $warehouseName = $record->sourceWarehouse !== null ? $record->sourceWarehouse->name : '-';

                                    fputcsv($handle, [
                                        $record->id,
                                        $customerName,
                                        $record->status->label(),
                                        $record->lines()->count(),
                                        $warehouseName,
                                        $record->carrier ?? '-',
                                        $record->shipping_method ?? '-',
                                        $record->incoterms ?? '-',
                                        $record->requested_ship_date?->format('Y-m-d') ?? '-',
                                        $record->packaging_preference->label(),
                                        $record->created_at?->format('Y-m-d H:i:s'),
                                        $record->destination_address ?? '-',
                                        $record->special_instructions ?? '-',
                                    ]);
                                }

                                fclose($handle);
                            }, 'shipping-orders-'.now()->format('Y-m-d-His').'.csv');
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Cancel - only on draft/planned, requires confirmation
                    Tables\Actions\BulkAction::make('cancel')
                        ->label('Cancel Orders')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Selected Shipping Orders')
                        ->modalDescription('Are you sure you want to cancel these shipping orders? This action cannot be undone. Only draft and planned orders will be cancelled.')
                        ->modalIcon('heroicon-o-exclamation-triangle')
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label('Cancellation Reason')
                                ->required()
                                ->placeholder('Enter the reason for cancelling these orders...')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $shippingOrderService = App::make(\App\Services\Fulfillment\ShippingOrderService::class);
                            $cancelled = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                /** @var ShippingOrder $record */
                                // Only cancel draft or planned orders
                                if ($record->isDraft() || $record->isPlanned()) {
                                    try {
                                        $shippingOrderService->cancel($record, $data['cancellation_reason']);
                                        $cancelled++;
                                    } catch (\Throwable) {
                                        $skipped++;
                                    }
                                } else {
                                    $skipped++;
                                }
                            }

                            if ($cancelled > 0) {
                                Notification::make()
                                    ->title('Orders Cancelled')
                                    ->body("{$cancelled} order(s) have been cancelled successfully.")
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title('Some Orders Skipped')
                                    ->body("{$skipped} order(s) could not be cancelled (only draft/planned orders can be cancelled).")
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search by SO ID, customer name, or tracking number...')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['customer', 'sourceWarehouse', 'shipments'])
                ->withCount('lines'));
    }

    /**
     * Get the global search result details.
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        /** @var ShippingOrder $record */
        return [
            'Customer' => $record->customer?->name ?: '-',
            'Status' => $record->status->label(),
        ];
    }

    /**
     * Get the globally searchable attributes.
     *
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'customer.name', 'shipments.tracking_number'];
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in later US stories
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingOrders::route('/'),
            'create' => Pages\CreateShippingOrder::route('/create'),
            'view' => Pages\ViewShippingOrder::route('/{record}'),
            'edit' => Pages\EditShippingOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    /**
     * Check if the current user can create shipping orders.
     */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * Check if a record can be deleted.
     * Only draft orders can be deleted.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var ShippingOrder $record */
        return $record->isDraft();
    }

    /**
     * Check if a record can be edited.
     * Only draft orders can be edited.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var ShippingOrder $record */
        return $record->isDraft();
    }
}
