<?php

namespace App\Filament\Resources\Allocation;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\VoucherResource\Pages\ListVouchers;
use App\Filament\Resources\Allocation\VoucherResource\Pages\ViewVoucher;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Vouchers';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Vouchers';

    protected static ?string $modelLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Vouchers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // No form schema - vouchers are created only from sale confirmation
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Voucher ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Voucher ID copied'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (Voucher $record): ?string => $record->customer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('bottle_sku')
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

                TextColumn::make('sellableSku.name')
                    ->label('Sellable SKU')
                    ->placeholder('N/A')
                    ->toggleable(),

                TextColumn::make('allocation_id')
                    ->label('Allocation')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Voucher $record): string => route('filament.admin.resources.allocation.allocations.view', ['record' => $record->allocation_id]))
                    ->openUrlInNewTab()
                    ->color('primary'),

                TextColumn::make('lifecycle_state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (VoucherLifecycleState $state): string => $state->label())
                    ->color(fn (VoucherLifecycleState $state): string => $state->color())
                    ->icon(fn (VoucherLifecycleState $state): string => $state->icon())
                    ->sortable(),

                IconColumn::make('requires_attention')
                    ->label('Anomaly')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip(fn (Voucher $record): ?string => $record->requires_attention
                        ? "Requires Attention: {$record->getAttentionReason()}"
                        : null)
                    ->sortable(),

                TextColumn::make('flags')
                    ->label('Flags')
                    ->state(function (Voucher $record): string {
                        $flags = [];
                        if ($record->isSuspendedForTrading()) {
                            $flags[] = 'Trading';
                        } elseif ($record->suspended) {
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
                    ->color(fn (Voucher $record): string => $record->isSuspendedForTrading()
                        ? 'warning'
                        : ($record->suspended ? 'danger' : 'gray')),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('lifecycle_state')
                    ->options(collect(VoucherLifecycleState::cases())
                        ->mapWithKeys(fn (VoucherLifecycleState $state) => [$state->value => $state->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        VoucherLifecycleState::Issued->value,
                        VoucherLifecycleState::Locked->value,
                    ])
                    ->multiple()
                    ->label('Lifecycle State'),

                Filter::make('allocation')
                    ->schema([
                        Select::make('allocation_id')
                            ->label('Allocation')
                            ->relationship('allocation', 'id')
                            ->getOptionLabelFromRecordUsing(function (Allocation $record): string {
                                return "#{$record->id} - ".$record->getBottleSkuLabel();
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['allocation_id'] ?? null,
                            fn (Builder $query, string $allocationId): Builder => $query->where('allocation_id', $allocationId)
                        );
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

                TernaryFilter::make('suspended')
                    ->label('Suspended')
                    ->placeholder('All vouchers')
                    ->trueLabel('Suspended only')
                    ->falseLabel('Not suspended'),

                TernaryFilter::make('requires_attention')
                    ->label('Anomalous')
                    ->placeholder('All vouchers')
                    ->trueLabel('Requires attention only')
                    ->falseLabel('Normal vouchers only'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'customer.name', 'customer.email', 'allocation_id'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'wineVariant.wineMaster']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Voucher $record */
        return 'Voucher #'.substr($record->id, 0, 8);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Voucher $record */
        return [
            'Customer' => $record->customer !== null ? $record->customer->name : 'N/A',
            'Wine' => $record->getBottleSkuLabel(),
            'State' => $record->lifecycle_state->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
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
            'index' => ListVouchers::route('/'),
            'view' => ViewVoucher::route('/{record}'),
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
     * Vouchers cannot be created from the admin panel.
     * They are created only from sale confirmation via VoucherService.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
