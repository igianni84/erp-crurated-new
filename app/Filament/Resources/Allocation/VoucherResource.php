<?php

namespace App\Filament\Resources\Allocation;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\VoucherResource\Pages;
use App\Models\Allocation\Voucher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Vouchers';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Vouchers';

    protected static ?string $modelLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Vouchers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // No form schema - vouchers are created only from sale confirmation
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Voucher ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Voucher ID copied'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (Voucher $record): ?string => $record->customer
                        ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('bottle_sku')
                    ->label('Bottle SKU')
                    ->state(fn (Voucher $record): string => $record->getBottleSkuLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('wineVariant', function (Builder $query) use ($search): void {
                            $query->whereHas('wineMaster', function (Builder $query) use ($search): void {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('producer', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('sellableSku.name')
                    ->label('Sellable SKU')
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('allocation_id')
                    ->label('Allocation')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Voucher $record): string => route('filament.admin.resources.allocations.view', ['record' => $record->allocation_id]))
                    ->openUrlInNewTab()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('lifecycle_state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (VoucherLifecycleState $state): string => $state->label())
                    ->color(fn (VoucherLifecycleState $state): string => $state->color())
                    ->icon(fn (VoucherLifecycleState $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('flags')
                    ->label('Flags')
                    ->state(function (Voucher $record): string {
                        $flags = [];
                        if ($record->suspended) {
                            $flags[] = 'Suspended';
                        }
                        if ($record->tradable) {
                            $flags[] = 'Tradable';
                        }
                        if ($record->giftable) {
                            $flags[] = 'Giftable';
                        }

                        return implode(', ', $flags);
                    })
                    ->badge()
                    ->separator(',')
                    ->color(fn (Voucher $record): string => $record->suspended ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lifecycle_state')
                    ->options(collect(VoucherLifecycleState::cases())
                        ->mapWithKeys(fn (VoucherLifecycleState $state) => [$state->value => $state->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        VoucherLifecycleState::Issued->value,
                        VoucherLifecycleState::Locked->value,
                    ])
                    ->multiple()
                    ->label('Lifecycle State'),

                Tables\Filters\Filter::make('allocation')
                    ->form([
                        Forms\Components\Select::make('allocation_id')
                            ->label('Allocation')
                            ->relationship('allocation', 'id')
                            ->getOptionLabelFromRecordUsing(function (\App\Models\Allocation\Allocation $record): string {
                                return "#{$record->id} - ".$record->getBottleSkuLabel();
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['allocation_id'] ?? null,
                            fn (Builder $query, int $allocationId): Builder => $query->where('allocation_id', $allocationId)
                        );
                    }),

                Tables\Filters\Filter::make('customer')
                    ->form([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['customer_id'] ?? null,
                            fn (Builder $query, int $customerId): Builder => $query->where('customer_id', $customerId)
                        );
                    }),

                Tables\Filters\TernaryFilter::make('suspended')
                    ->label('Suspended')
                    ->placeholder('All vouchers')
                    ->trueLabel('Suspended only')
                    ->falseLabel('Not suspended'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions - vouchers require careful individual handling
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
                'wineVariant.wineMaster',
                'format',
                'sellableSku',
                'allocation',
            ]));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-023
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVouchers::route('/'),
            'view' => Pages\ViewVoucher::route('/{record}'),
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
     * Vouchers cannot be created from the admin panel.
     * They are created only from sale confirmation via VoucherService.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
