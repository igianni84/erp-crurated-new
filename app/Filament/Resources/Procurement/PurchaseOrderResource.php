<?php

namespace App\Filament\Resources\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Resources\Procurement\PurchaseOrderResource\Pages;
use App\Models\Customer\Party;
use App\Models\Procurement\PurchaseOrder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Purchase Orders';

    protected static ?string $modelLabel = 'Purchase Order';

    protected static ?string $pluralModelLabel = 'Purchase Orders';

    protected static ?string $slug = 'procurement/purchase-orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form schema will be implemented in wizard stories (US-020 to US-024)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('PO ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('PO ID copied')
                    ->limit(8)
                    ->tooltip(fn (PurchaseOrder $record): string => $record->id),

                Tables\Columns\TextColumn::make('supplier.legal_name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder('No supplier'),

                Tables\Columns\TextColumn::make('product')
                    ->label('Product')
                    ->state(fn (PurchaseOrder $record): string => $record->getProductLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            // Search in SellableSku via sku_code
                            $query->whereHas('productReference', function (Builder $query) use ($search): void {
                                $query->where('sku_code', 'like', "%{$search}%");
                            })
                            // Search in wine master name through sellable_skus
                                ->orWhere(function (Builder $query) use ($search): void {
                                    $query->where('product_reference_type', 'sellable_skus')
                                        ->whereExists(function ($subquery) use ($search): void {
                                            $subquery->selectRaw('1')
                                                ->from('sellable_skus')
                                                ->join('wine_variants', 'sellable_skus.wine_variant_id', '=', 'wine_variants.id')
                                                ->join('wine_masters', 'wine_variants.wine_master_id', '=', 'wine_masters.id')
                                                ->whereColumn('sellable_skus.id', 'purchase_orders.product_reference_id')
                                                ->where('wine_masters.name', 'like', "%{$search}%");
                                        });
                                })
                            // Search in wine master name through liquid_products
                                ->orWhere(function (Builder $query) use ($search): void {
                                    $query->where('product_reference_type', 'liquid_products')
                                        ->whereExists(function ($subquery) use ($search): void {
                                            $subquery->selectRaw('1')
                                                ->from('liquid_products')
                                                ->join('wine_variants', 'liquid_products.wine_variant_id', '=', 'wine_variants.id')
                                                ->join('wine_masters', 'wine_variants.wine_master_id', '=', 'wine_masters.id')
                                                ->whereColumn('liquid_products.id', 'purchase_orders.product_reference_id')
                                                ->where('wine_masters.name', 'like', "%{$search}%");
                                        });
                                });
                        });
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money(fn (PurchaseOrder $record): string => $record->currency ?? 'EUR')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('ownership_transfer')
                    ->label('Ownership')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (PurchaseOrder $record): string => $record->ownership_transfer
                        ? 'Ownership transfers on delivery'
                        : 'No ownership transfer'),

                Tables\Columns\TextColumn::make('delivery_window')
                    ->label('Delivery Window')
                    ->state(fn (PurchaseOrder $record): string => $record->getDeliveryWindowLabel())
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_overdue')
                    ->label('Overdue')
                    ->boolean()
                    ->state(fn (PurchaseOrder $record): bool => $record->isDeliveryOverdue())
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip(fn (PurchaseOrder $record): ?string => $record->isDeliveryOverdue()
                        ? 'Delivery window has passed'
                        : null),

                Tables\Columns\TextColumn::make('variance_status')
                    ->label('Variance')
                    ->state(function (PurchaseOrder $record): string {
                        if (! $record->hasInbounds()) {
                            return 'No Inbounds';
                        }

                        $variance = $record->getVariance();
                        $status = $record->getVarianceStatus();
                        $percent = $record->getVariancePercentage();

                        if ($variance === 0) {
                            return $status;
                        }

                        $sign = $variance > 0 ? '+' : '';
                        $percentStr = $percent !== null ? ' ('.number_format($percent, 1).'%)' : '';

                        return "{$status}: {$sign}{$variance}{$percentStr}";
                    })
                    ->badge()
                    ->color(function (PurchaseOrder $record): string {
                        if (! $record->hasInbounds()) {
                            return 'gray';
                        }

                        $variance = $record->getVariance();

                        if ($variance === 0) {
                            return 'success';
                        }

                        // Check if variance > 10%
                        if ($record->hasSignificantVariance(10.0)) {
                            return 'danger';
                        }

                        return 'warning';
                    })
                    ->icon(function (PurchaseOrder $record): ?string {
                        if (! $record->hasInbounds()) {
                            return null;
                        }

                        $variance = $record->getVariance();

                        return match (true) {
                            $variance === 0 => 'heroicon-o-check-circle',
                            $variance > 0 => 'heroicon-o-arrow-up-circle',
                            default => 'heroicon-o-arrow-down-circle',
                        };
                    })
                    ->tooltip(function (PurchaseOrder $record): ?string {
                        if (! $record->hasInbounds()) {
                            return 'No inbound records linked to this PO';
                        }

                        if ($record->hasSignificantVariance(10.0)) {
                            return 'Variance exceeds 10% threshold!';
                        }

                        return null;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withSum('inbounds', 'quantity')
                            ->orderByRaw("(COALESCE(inbounds_sum_quantity, 0) - quantity) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PurchaseOrderStatus $state): string => $state->label())
                    ->color(fn (PurchaseOrderStatus $state): string => $state->color())
                    ->icon(fn (PurchaseOrderStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PurchaseOrderStatus::cases())
                        ->mapWithKeys(fn (PurchaseOrderStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        PurchaseOrderStatus::Draft->value,
                        PurchaseOrderStatus::Sent->value,
                        PurchaseOrderStatus::Confirmed->value,
                    ])
                    ->multiple()
                    ->label('Status'),

                Tables\Filters\SelectFilter::make('supplier_party_id')
                    ->label('Supplier')
                    ->options(function (): array {
                        return Party::query()
                            ->whereHas('purchaseOrdersAsSupplier')
                            ->pluck('legal_name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('ownership_transfer')
                    ->label('Ownership Transfer')
                    ->placeholder('All')
                    ->trueLabel('With Ownership Transfer')
                    ->falseLabel('Without Ownership Transfer'),

                Tables\Filters\Filter::make('delivery_period')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('delivery_from')
                            ->label('Delivery From'),
                        \Filament\Forms\Components\DatePicker::make('delivery_to')
                            ->label('Delivery To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['delivery_from'],
                                fn (Builder $query, $date): Builder => $query->where('expected_delivery_start', '>=', $date)
                            )
                            ->when(
                                $data['delivery_to'],
                                fn (Builder $query, $date): Builder => $query->where('expected_delivery_end', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['delivery_from'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Delivery from '.\Carbon\Carbon::parse($data['delivery_from'])->format('M j, Y'))
                                ->removeField('delivery_from');
                        }

                        if ($data['delivery_to'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Delivery to '.\Carbon\Carbon::parse($data['delivery_to'])->format('M j, Y'))
                                ->removeField('delivery_to');
                        }

                        return $indicators;
                    }),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Deliveries')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('expected_delivery_end')
                        ->where('expected_delivery_end', '<', now())
                        ->where('status', '!=', PurchaseOrderStatus::Closed->value))
                    ->toggle(),

                Tables\Filters\SelectFilter::make('variance')
                    ->label('Delivery Variance')
                    ->options([
                        'exact_match' => 'Exact Match',
                        'over_delivery' => 'Over Delivery',
                        'short_delivery' => 'Short Delivery',
                        'significant_variance' => 'Variance > 10%',
                        'no_inbounds' => 'No Inbounds',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (empty($value)) {
                            return $query;
                        }

                        return match ($value) {
                            'exact_match' => $query
                                ->whereHas('inbounds')
                                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) = purchase_orders.quantity'),
                            'over_delivery' => $query
                                ->whereHas('inbounds')
                                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) > purchase_orders.quantity'),
                            'short_delivery' => $query
                                ->whereHas('inbounds')
                                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) < purchase_orders.quantity'),
                            'significant_variance' => $query
                                ->whereHas('inbounds')
                                ->where('quantity', '>', 0)
                                ->whereRaw('ABS((SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) - purchase_orders.quantity) / purchase_orders.quantity * 100 > 10'),
                            'no_inbounds' => $query->whereDoesntHave('inbounds'),
                            default => $query,
                        };
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['supplier', 'productReference', 'procurementIntent']));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-025 (detail tabs)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
