<?php

namespace App\Filament\Resources\Customer\CustomerResource\RelationManagers;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Voucher;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VouchersRelationManager extends RelationManager
{
    protected static string $relationship = 'vouchers';

    protected static ?string $title = 'Vouchers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Voucher ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Voucher ID copied')
                    ->limit(8)
                    ->tooltip(fn (Voucher $record): string => $record->id),

                TextColumn::make('wine_label')
                    ->label('SKU')
                    ->getStateUsing(function (Voucher $record): string {
                        $variant = $record->wineVariant;
                        if ($variant === null) {
                            return 'Unknown';
                        }
                        $master = $variant->wineMaster;
                        $name = $master !== null ? $master->name : 'Unknown Wine';
                        $vintage = $variant->vintage_year ?? 'NV';

                        return "{$name} {$vintage}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('wineVariant', function (Builder $q) use ($search): void {
                            $q->whereHas('wineMaster', function (Builder $q2) use ($search): void {
                                $q2->where('name', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->wrap(),

                TextColumn::make('lifecycle_state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (VoucherLifecycleState $state): string => $state->label())
                    ->color(fn (VoucherLifecycleState $state): string => $state->color())
                    ->icon(fn (VoucherLifecycleState $state): string => $state->icon())
                    ->sortable(),

                IconColumn::make('tradable')
                    ->label('Tradable')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('giftable')
                    ->label('Giftable')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('lifecycle_state')
                    ->options(collect(VoucherLifecycleState::cases())
                        ->mapWithKeys(fn (VoucherLifecycleState $state) => [$state->value => $state->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                TernaryFilter::make('tradable')
                    ->label('Tradable'),

                TernaryFilter::make('giftable')
                    ->label('Giftable'),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['wineVariant.wineMaster']));
    }
}
