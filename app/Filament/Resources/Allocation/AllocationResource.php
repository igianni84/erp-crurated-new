<?php

namespace App\Filament\Resources\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Filament\Resources\Allocation\AllocationResource\Pages;
use App\Models\Allocation\Allocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AllocationResource extends Resource
{
    protected static ?string $model = Allocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'Allocations';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Allocations';

    protected static ?string $modelLabel = 'Allocation';

    protected static ?string $pluralModelLabel = 'Allocations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form schema will be implemented in US-008 through US-012
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Allocation ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Allocation ID copied'),

                Tables\Columns\TextColumn::make('bottle_sku')
                    ->label('Bottle SKU')
                    ->state(fn (Allocation $record): string => $record->getBottleSkuLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('wineVariant', function (Builder $query) use ($search): void {
                            $query->whereHas('wineMaster', function (Builder $query) use ($search): void {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('producer', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\Pim\WineMaster::query()
                                ->select('name')
                                ->join('wine_variants', 'wine_masters.id', '=', 'wine_variants.wine_master_id')
                                ->whereColumn('wine_variants.id', 'allocations.wine_variant_id')
                                ->limit(1),
                            $direction
                        );
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('supply_form')
                    ->label('Supply Form')
                    ->badge()
                    ->formatStateUsing(fn (AllocationSupplyForm $state): string => $state->label())
                    ->color(fn (AllocationSupplyForm $state): string => $state->color())
                    ->icon(fn (AllocationSupplyForm $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source Type')
                    ->badge()
                    ->formatStateUsing(fn (AllocationSourceType $state): string => $state->label())
                    ->color(fn (AllocationSourceType $state): string => $state->color())
                    ->icon(fn (AllocationSourceType $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (AllocationStatus $state): string => $state->label())
                    ->color(fn (AllocationStatus $state): string => $state->color())
                    ->icon(fn (AllocationStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Sold Qty')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('remaining_quantity')
                    ->label('Remaining Qty')
                    ->state(fn (Allocation $record): int => $record->remaining_quantity)
                    ->numeric()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('(total_quantity - sold_quantity) '.$direction);
                    })
                    ->alignEnd()
                    ->color(fn (Allocation $record): string => $record->isNearExhaustion() ? 'danger' : 'success')
                    ->weight(fn (Allocation $record): string => $record->isNearExhaustion() ? 'bold' : 'normal')
                    ->icon(fn (Allocation $record): ?string => $record->isNearExhaustion() ? 'heroicon-o-exclamation-triangle' : null),

                Tables\Columns\TextColumn::make('availability_window')
                    ->label('Availability')
                    ->state(fn (Allocation $record): string => $record->getAvailabilityWindowLabel())
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('expected_availability_start', $direction);
                    }),

                Tables\Columns\TextColumn::make('constraint_summary')
                    ->label('Constraints')
                    ->state(function (Allocation $record): string {
                        $constraint = $record->constraint;

                        return $constraint !== null ? $constraint->getSummary() : 'Not set';
                    })
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(AllocationStatus::cases())
                        ->mapWithKeys(fn (AllocationStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        AllocationStatus::Draft->value,
                        AllocationStatus::Active->value,
                        AllocationStatus::Exhausted->value,
                    ])
                    ->multiple()
                    ->label('Status'),

                Tables\Filters\SelectFilter::make('source_type')
                    ->options(collect(AllocationSourceType::cases())
                        ->mapWithKeys(fn (AllocationSourceType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Source Type'),

                Tables\Filters\SelectFilter::make('supply_form')
                    ->options(collect(AllocationSupplyForm::cases())
                        ->mapWithKeys(fn (AllocationSupplyForm $form) => [$form->value => $form->label()])
                        ->toArray())
                    ->label('Supply Form'),

                Tables\Filters\Filter::make('wine_variant')
                    ->form([
                        Forms\Components\Select::make('wine_variant_id')
                            ->label('Bottle SKU (Wine Variant)')
                            ->relationship('wineVariant', 'id')
                            ->getOptionLabelFromRecordUsing(function (\App\Models\Pim\WineVariant $record): string {
                                $wineMaster = $record->wineMaster;
                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                $vintage = $record->vintage_year ?? 'NV';

                                return "{$wineName} {$vintage}";
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['wine_variant_id'] ?? null,
                            fn (Builder $query, string $wineVariantId): Builder => $query->where('wine_variant_id', $wineVariantId)
                        );
                    }),

                Tables\Filters\Filter::make('near_exhaustion')
                    ->label('Near Exhaustion')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('(total_quantity - sold_quantity) < (total_quantity * 0.10)'))
                    ->toggle(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['wineVariant.wineMaster', 'format', 'constraint']));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-013
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAllocations::route('/'),
            'create' => Pages\CreateAllocation::route('/create'),
            'view' => Pages\ViewAllocation::route('/{record}'),
            'edit' => Pages\EditAllocation::route('/{record}/edit'),
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
