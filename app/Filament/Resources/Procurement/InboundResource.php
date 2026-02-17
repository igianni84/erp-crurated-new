<?php

namespace App\Filament\Resources\Procurement;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Filament\Resources\Procurement\InboundResource\Pages\CreateInbound;
use App\Filament\Resources\Procurement\InboundResource\Pages\ListInbounds;
use App\Filament\Resources\Procurement\InboundResource\Pages\ViewInbound;
use App\Models\Procurement\Inbound;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InboundResource extends Resource
{
    protected static ?string $model = Inbound::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Inbounds';

    protected static ?string $modelLabel = 'Inbound';

    protected static ?string $pluralModelLabel = 'Inbounds';

    protected static ?string $slug = 'procurement/inbounds';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Form schema will be implemented in wizard stories (US-038 to US-041)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Inbound ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Inbound ID copied')
                    ->limit(8)
                    ->tooltip(fn (Inbound $record): string => $record->id),

                TextColumn::make('warehouse')
                    ->label('Warehouse')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('product')
                    ->label('Product')
                    ->state(fn (Inbound $record): string => $record->getProductLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            // Search in sellable_skus via sku_code
                            $query->whereExists(function ($subquery) use ($search): void {
                                $subquery->selectRaw('1')
                                    ->from('sellable_skus')
                                    ->whereColumn('sellable_skus.id', 'inbounds.product_reference_id')
                                    ->where('inbounds.product_reference_type', 'sellable_skus')
                                    ->where('sellable_skus.sku_code', 'like', "%{$search}%");
                            });

                            // Search in liquid_products via wine_variants and wine_masters
                            $query->orWhereExists(function ($subquery) use ($search): void {
                                $subquery->selectRaw('1')
                                    ->from('liquid_products')
                                    ->join('wine_variants', 'liquid_products.wine_variant_id', '=', 'wine_variants.id')
                                    ->join('wine_masters', 'wine_variants.wine_master_id', '=', 'wine_masters.id')
                                    ->whereColumn('liquid_products.id', 'inbounds.product_reference_id')
                                    ->where('inbounds.product_reference_type', 'liquid_products')
                                    ->where('wine_masters.name', 'like', "%{$search}%");
                            });

                            // Search in sellable_skus via wine_variants and wine_masters
                            $query->orWhereExists(function ($subquery) use ($search): void {
                                $subquery->selectRaw('1')
                                    ->from('sellable_skus')
                                    ->join('wine_variants', 'sellable_skus.wine_variant_id', '=', 'wine_variants.id')
                                    ->join('wine_masters', 'wine_variants.wine_master_id', '=', 'wine_masters.id')
                                    ->whereColumn('sellable_skus.id', 'inbounds.product_reference_id')
                                    ->where('inbounds.product_reference_type', 'sellable_skus')
                                    ->where('wine_masters.name', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->wrap(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('packaging')
                    ->label('Packaging')
                    ->badge()
                    ->formatStateUsing(fn (InboundPackaging $state): string => $state->label())
                    ->color(fn (InboundPackaging $state): string => $state->color())
                    ->icon(fn (InboundPackaging $state): string => $state->icon())
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ownership_flag')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipFlag $state): string => $state->label())
                    ->color(fn (OwnershipFlag $state): string => $state->color())
                    ->icon(fn (OwnershipFlag $state): string => $state->icon())
                    ->sortable(),

                IconColumn::make('ownership_pending')
                    ->label('Attention')
                    ->boolean()
                    ->state(fn (Inbound $record): bool => $record->ownership_flag === OwnershipFlag::Pending)
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip(fn (Inbound $record): ?string => $record->ownership_flag === OwnershipFlag::Pending
                        ? 'Ownership requires clarification'
                        : null),

                TextColumn::make('received_date')
                    ->label('Received Date')
                    ->date()
                    ->sortable(),

                IconColumn::make('serialization_required')
                    ->label('Serialization')
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->tooltip(fn (Inbound $record): string => $record->serialization_required
                        ? 'Serialization required'
                        : 'Serialization not required'),

                IconColumn::make('is_unlinked')
                    ->label('Unlinked')
                    ->boolean()
                    ->state(fn (Inbound $record): bool => $record->isUnlinked())
                    ->trueIcon('heroicon-o-link-slash')
                    ->falseIcon('')
                    ->trueColor('warning')
                    ->tooltip(fn (Inbound $record): ?string => $record->isUnlinked()
                        ? 'No linked Procurement Intent'
                        : null)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (InboundStatus $state): string => $state->label())
                    ->color(fn (InboundStatus $state): string => $state->color())
                    ->icon(fn (InboundStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InboundStatus::cases())
                        ->mapWithKeys(fn (InboundStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        InboundStatus::Recorded->value,
                        InboundStatus::Routed->value,
                    ])
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('warehouse')
                    ->options([
                        'main_warehouse' => 'Main Warehouse',
                        'secondary_warehouse' => 'Secondary Warehouse',
                        'bonded_warehouse' => 'Bonded Warehouse',
                        'third_party_storage' => 'Third Party Storage',
                    ])
                    ->multiple()
                    ->label('Warehouse'),

                SelectFilter::make('ownership_flag')
                    ->options(collect(OwnershipFlag::cases())
                        ->mapWithKeys(fn (OwnershipFlag $flag) => [$flag->value => $flag->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Ownership'),

                SelectFilter::make('packaging')
                    ->options(collect(InboundPackaging::cases())
                        ->mapWithKeys(fn (InboundPackaging $packaging) => [$packaging->value => $packaging->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Packaging'),

                Filter::make('ownership_pending')
                    ->label('Ownership Pending')
                    ->query(fn (Builder $query): Builder => $query->where('ownership_flag', OwnershipFlag::Pending->value))
                    ->toggle(),

                Filter::make('unlinked')
                    ->label('Unlinked (No Intent)')
                    ->query(fn (Builder $query): Builder => $query->whereNull('procurement_intent_id'))
                    ->toggle(),

                Filter::make('serialization_required')
                    ->label('Serialization Required')
                    ->query(fn (Builder $query): Builder => $query->where('serialization_required', true))
                    ->toggle(),

                Filter::make('handed_to_module_b')
                    ->label('Handed to Module B')
                    ->query(fn (Builder $query): Builder => $query->where('handed_to_module_b', true))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('received_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['productReference', 'procurementIntent', 'purchaseOrder']));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-042 (detail tabs)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInbounds::route('/'),
            'create' => CreateInbound::route('/create'),
            'view' => ViewInbound::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
