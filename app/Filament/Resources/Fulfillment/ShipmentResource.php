<?php

namespace App\Filament\Resources\Fulfillment;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Filament\Resources\Fulfillment\ShipmentResource\Pages\ListShipments;
use App\Filament\Resources\Fulfillment\ShipmentResource\Pages\ViewShipment;
use App\Models\Fulfillment\Shipment;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string|\UnitEnum|null $navigationGroup = 'Fulfillment';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Shipments';

    protected static ?string $modelLabel = 'Shipment';

    protected static ?string $pluralModelLabel = 'Shipments';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shipment Information')
                    ->schema([
                        Select::make('shipping_order_id')
                            ->label('Shipping Order')
                            ->relationship('shippingOrder', 'id')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(),

                        TextInput::make('carrier')
                            ->label('Carrier')
                            ->maxLength(255)
                            ->disabled(),

                        TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->maxLength(255)
                            ->disabled(),

                        Select::make('status')
                            ->label('Status')
                            ->options(collect(ShipmentStatus::cases())
                                ->mapWithKeys(fn (ShipmentStatus $status) => [$status->value => $status->label()])
                                ->toArray())
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Shipping Details')
                    ->schema([
                        Select::make('origin_warehouse_id')
                            ->label('Origin Warehouse')
                            ->relationship('originWarehouse', 'name')
                            ->disabled(),

                        Textarea::make('destination_address')
                            ->label('Destination Address')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Timestamps')
                    ->schema([
                        DateTimePicker::make('shipped_at')
                            ->label('Shipped At')
                            ->disabled(),

                        DateTimePicker::make('delivered_at')
                            ->label('Delivered At')
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Additional Information')
                    ->schema([
                        TextInput::make('weight')
                            ->label('Weight (kg)')
                            ->numeric()
                            ->disabled(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Shipment ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Shipment ID copied')
                    ->limit(8)
                    ->tooltip(fn (Shipment $record): string => $record->id),

                TextColumn::make('shippingOrder.id')
                    ->label('SO ID')
                    ->searchable()
                    ->sortable()
                    ->limit(8)
                    ->url(fn (Shipment $record): ?string => $record->shippingOrder
                        ? route('filament.admin.resources.fulfillment.shipping-orders.view', ['record' => $record->shippingOrder])
                        : null)
                    ->color('primary')
                    ->tooltip(fn (Shipment $record): string => $record->shipping_order_id),

                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tracking_number')
                    ->label('Tracking Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Tracking number copied')
                    ->placeholder('-'),

                TextColumn::make('shipped_at')
                    ->label('Shipped At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ShipmentStatus $state): string => $state->label())
                    ->color(fn (ShipmentStatus $state): string => $state->color())
                    ->icon(fn (ShipmentStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('bottles_count')
                    ->label('Bottles')
                    ->getStateUsing(fn (Shipment $record): int => $record->getBottleCount())
                    ->badge()
                    ->color('info')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("JSON_LENGTH(shipped_bottle_serials) {$direction}");
                    }),

                TextColumn::make('delivered_at')
                    ->label('Delivered At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                TextColumn::make('originWarehouse.name')
                    ->label('Origin Warehouse')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ShipmentStatus::cases())
                        ->mapWithKeys(fn (ShipmentStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('carrier')
                    ->options(fn (): array => Shipment::query()
                        ->whereNotNull('carrier')
                        ->distinct()
                        ->pluck('carrier', 'carrier')
                        ->toArray())
                    ->searchable()
                    ->label('Carrier'),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('shipped_from')
                            ->label('Shipped From'),
                        DatePicker::make('shipped_until')
                            ->label('Shipped Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['shipped_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('shipped_at', '>=', $date),
                            )
                            ->when(
                                $data['shipped_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('shipped_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['shipped_from'] ?? null) {
                            $indicators['shipped_from'] = 'Shipped from '.$data['shipped_from'];
                        }
                        if ($data['shipped_until'] ?? null) {
                            $indicators['shipped_until'] = 'Shipped until '.$data['shipped_until'];
                        }

                        return $indicators;
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Export CSV
                    BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records): StreamedResponse {
                            return response()->streamDownload(function () use ($records): void {
                                $handle = fopen('php://output', 'w');
                                if ($handle === false) {
                                    return;
                                }

                                // CSV header
                                fputcsv($handle, [
                                    'Shipment ID',
                                    'SO ID',
                                    'Carrier',
                                    'Tracking Number',
                                    'Status',
                                    'Bottles Count',
                                    'Origin Warehouse',
                                    'Destination Address',
                                    'Shipped At',
                                    'Delivered At',
                                    'Weight',
                                    'Notes',
                                    'Created At',
                                ]);

                                // CSV rows
                                foreach ($records as $record) {
                                    /** @var Shipment $record */
                                    $warehouseName = $record->originWarehouse !== null ? $record->originWarehouse->name : '-';

                                    fputcsv($handle, [
                                        $record->id,
                                        $record->shipping_order_id,
                                        $record->carrier,
                                        $record->tracking_number ?? '-',
                                        $record->status->label(),
                                        $record->getBottleCount(),
                                        $warehouseName,
                                        $record->destination_address,
                                        $record->shipped_at?->format('Y-m-d H:i:s') ?? '-',
                                        $record->delivered_at?->format('Y-m-d H:i:s') ?? '-',
                                        $record->weight ?? '-',
                                        $record->notes ?? '-',
                                        $record->created_at?->format('Y-m-d H:i:s'),
                                    ]);
                                }

                                fclose($handle);
                            }, 'shipments-'.now()->format('Y-m-d-His').'.csv');
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('shipped_at', 'desc')
            ->searchPlaceholder('Search by shipment ID, SO ID, or tracking number...')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['shippingOrder', 'originWarehouse']));
    }

    /**
     * Get the global search result details.
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Shipment $record */
        return [
            'SO ID' => $record->shipping_order_id,
            'Status' => $record->status->label(),
            'Tracking' => $record->tracking_number ?? '-',
        ];
    }

    /**
     * Get the globally searchable attributes.
     *
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'shipping_order_id', 'tracking_number', 'carrier'];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShipments::route('/'),
            'view' => ViewShipment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Shipments cannot be created directly - they are created from ShippingOrders.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Shipments have limited edit capability - most fields are read-only.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Shipments cannot be deleted.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
