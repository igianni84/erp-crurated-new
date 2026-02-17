<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Customer\BlockStatus;
use App\Enums\Customer\BlockType;
use App\Filament\Exports\Customer\OperationalBlockExporter;
use App\Filament\Resources\Customer\OperationalBlockResource\Pages\ListOperationalBlocks;
use App\Models\Customer\Account;
use App\Models\Customer\Customer;
use App\Models\Customer\OperationalBlock;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OperationalBlockResource extends Resource
{
    protected static ?string $model = OperationalBlock::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Operational Blocks';

    protected static ?string $modelLabel = 'Operational Block';

    protected static ?string $pluralModelLabel = 'Operational Blocks';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('blockable_name')
                    ->label('Customer/Account')
                    ->state(function (OperationalBlock $record): string {
                        $blockable = $record->blockable;
                        if ($blockable instanceof Customer) {
                            return $blockable->getName();
                        }
                        if ($blockable instanceof Account) {
                            $customer = $blockable->customer;

                            return $customer !== null
                                ? $customer->getName().' ('.$blockable->name.')'
                                : $blockable->name;
                        }

                        return '-';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            // Search in Customer names (both direct and via Party)
                            $q->whereHasMorph('blockable', [Customer::class], function (Builder $customerQuery) use ($search): void {
                                $customerQuery->where('name', 'like', "%{$search}%")
                                    ->orWhereHas('party', function (Builder $partyQuery) use ($search): void {
                                        $partyQuery->where('legal_name', 'like', "%{$search}%");
                                    });
                            })
                            // Search in Account names
                                ->orWhereHasMorph('blockable', [Account::class], function (Builder $accountQuery) use ($search): void {
                                    $accountQuery->where('name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Sort by blockable_type first, then by ID for consistency
                        return $query->orderBy('blockable_type', $direction)
                            ->orderBy('blockable_id', $direction);
                    })
                    ->weight('bold'),

                TextColumn::make('blockable_type_label')
                    ->label('Type')
                    ->state(function (OperationalBlock $record): string {
                        $blockable = $record->blockable;
                        if ($blockable instanceof Customer) {
                            return 'Customer';
                        }
                        if ($blockable instanceof Account) {
                            return 'Account';
                        }

                        return '-';
                    })
                    ->badge()
                    ->color(function (OperationalBlock $record): string {
                        $blockable = $record->blockable;
                        if ($blockable instanceof Customer) {
                            return 'primary';
                        }
                        if ($blockable instanceof Account) {
                            return 'info';
                        }

                        return 'gray';
                    })
                    ->toggleable(),

                TextColumn::make('block_type')
                    ->label('Block Type')
                    ->badge()
                    ->formatStateUsing(fn (?BlockType $state): string => $state?->label() ?? '-')
                    ->color(fn (?BlockType $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?BlockType $state): ?string => $state?->icon())
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(fn (OperationalBlock $record): string => $record->reason)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?BlockStatus $state): string => $state?->label() ?? '-')
                    ->color(fn (?BlockStatus $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?BlockStatus $state): ?string => $state?->icon())
                    ->sortable(),

                TextColumn::make('appliedByUser.name')
                    ->label('Applied By')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Applied At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('removed_at')
                    ->label('Removed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('removedByUser.name')
                    ->label('Removed By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('block_type')
                    ->label('Block Type')
                    ->options(collect(BlockType::cases())->mapWithKeys(fn (BlockType $type) => [
                        $type->value => $type->label(),
                    ])),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(BlockStatus::cases())->mapWithKeys(fn (BlockStatus $status) => [
                        $status->value => $status->label(),
                    ]))
                    ->default(BlockStatus::Active->value),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('applied_from')
                            ->label('Applied From'),
                        DatePicker::make('applied_until')
                            ->label('Applied Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['applied_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['applied_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['applied_from'] ?? null) {
                            $indicators['applied_from'] = 'Applied from '.Carbon::parse($data['applied_from'])->toFormattedDateString();
                        }

                        if ($data['applied_until'] ?? null) {
                            $indicators['applied_until'] = 'Applied until '.Carbon::parse($data['applied_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),

                SelectFilter::make('blockable_type')
                    ->label('Entity Type')
                    ->options([
                        Customer::class => 'Customer',
                        Account::class => 'Account',
                    ]),
            ])
            ->recordActions([
                Action::make('view_customer')
                    ->label('View Customer')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(function (OperationalBlock $record): ?string {
                        $blockable = $record->blockable;
                        if ($blockable instanceof Customer) {
                            return CustomerResource::getUrl('view', ['record' => $blockable]);
                        }
                        if ($blockable instanceof Account) {
                            $customer = $blockable->customer;
                            if ($customer !== null) {
                                return CustomerResource::getUrl('view', ['record' => $customer]);
                            }
                        }

                        return null;
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(OperationalBlockExporter::class)
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(OperationalBlockExporter::class)
                        ->label('Export Selected'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationalBlocks::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['blockable', 'appliedByUser', 'removedByUser']);
    }
}
