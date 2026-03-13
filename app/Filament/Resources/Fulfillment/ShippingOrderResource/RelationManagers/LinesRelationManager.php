<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\RelationManagers;

use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Models\Fulfillment\ShippingOrderLine;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Order Lines';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_id')
                    ->label('Voucher')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(8)
                    ->tooltip(fn (ShippingOrderLine $record): string => $record->voucher_id),

                TextColumn::make('wine_label')
                    ->label('SKU')
                    ->getStateUsing(function (ShippingOrderLine $record): string {
                        $voucher = $record->voucher;
                        if ($voucher === null) {
                            return 'Unknown';
                        }
                        $variant = $voucher->wineVariant;
                        if ($variant === null) {
                            return 'Unknown';
                        }
                        $master = $variant->wineMaster;
                        $name = $master !== null ? $master->name : 'Unknown Wine';
                        $vintage = $variant->vintage_year ?? 'NV';

                        return "{$name} {$vintage}";
                    })
                    ->wrap(),

                TextColumn::make('bound_bottle_serial')
                    ->label('Bound Serial')
                    ->searchable()
                    ->placeholder('Not bound'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ShippingOrderLineStatus $state): string => $state->label())
                    ->color(fn (ShippingOrderLineStatus $state): string => $state->color())
                    ->icon(fn (ShippingOrderLineStatus $state): string => $state->icon())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ShippingOrderLineStatus::cases())
                        ->mapWithKeys(fn (ShippingOrderLineStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),
            ])
            ->defaultSort('created_at', 'asc')
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['voucher.wineVariant.wineMaster']));
    }
}
