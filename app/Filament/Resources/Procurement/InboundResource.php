<?php

namespace App\Filament\Resources\Procurement;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Filament\Resources\Procurement\InboundResource\Pages;
use App\Models\Procurement\Inbound;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InboundResource extends Resource
{
    protected static ?string $model = Inbound::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Inbounds';

    protected static ?string $modelLabel = 'Inbound';

    protected static ?string $pluralModelLabel = 'Inbounds';

    protected static ?string $slug = 'procurement/inbounds';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form schema will be implemented in wizard stories (US-038 to US-041)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Inbound ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Inbound ID copied')
                    ->limit(8)
                    ->tooltip(fn (Inbound $record): string => $record->id),

                Tables\Columns\TextColumn::make('warehouse')
                    ->label('Warehouse')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('product')
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

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('packaging')
                    ->label('Packaging')
                    ->badge()
                    ->formatStateUsing(fn (InboundPackaging $state): string => $state->label())
                    ->color(fn (InboundPackaging $state): string => $state->color())
                    ->icon(fn (InboundPackaging $state): string => $state->icon())
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ownership_flag')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipFlag $state): string => $state->label())
                    ->color(fn (OwnershipFlag $state): string => $state->color())
                    ->icon(fn (OwnershipFlag $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\IconColumn::make('ownership_pending')
                    ->label('Attention')
                    ->boolean()
                    ->state(fn (Inbound $record): bool => $record->ownership_flag === OwnershipFlag::Pending)
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip(fn (Inbound $record): ?string => $record->ownership_flag === OwnershipFlag::Pending
                        ? 'Ownership requires clarification'
                        : null),

                Tables\Columns\TextColumn::make('received_date')
                    ->label('Received Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\IconColumn::make('serialization_required')
                    ->label('Serialization')
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->tooltip(fn (Inbound $record): string => $record->serialization_required
                        ? 'Serialization required'
                        : 'Serialization not required'),

                Tables\Columns\IconColumn::make('is_unlinked')
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

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (InboundStatus $state): string => $state->label())
                    ->color(fn (InboundStatus $state): string => $state->color())
                    ->icon(fn (InboundStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(InboundStatus::cases())
                        ->mapWithKeys(fn (InboundStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        InboundStatus::Recorded->value,
                        InboundStatus::Routed->value,
                    ])
                    ->multiple()
                    ->label('Status'),

                Tables\Filters\SelectFilter::make('warehouse')
                    ->options([
                        'main_warehouse' => 'Main Warehouse',
                        'secondary_warehouse' => 'Secondary Warehouse',
                        'bonded_warehouse' => 'Bonded Warehouse',
                        'third_party_storage' => 'Third Party Storage',
                    ])
                    ->multiple()
                    ->label('Warehouse'),

                Tables\Filters\SelectFilter::make('ownership_flag')
                    ->options(collect(OwnershipFlag::cases())
                        ->mapWithKeys(fn (OwnershipFlag $flag) => [$flag->value => $flag->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Ownership'),

                Tables\Filters\SelectFilter::make('packaging')
                    ->options(collect(InboundPackaging::cases())
                        ->mapWithKeys(fn (InboundPackaging $packaging) => [$packaging->value => $packaging->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Packaging'),

                Tables\Filters\Filter::make('ownership_pending')
                    ->label('Ownership Pending')
                    ->query(fn (Builder $query): Builder => $query->where('ownership_flag', OwnershipFlag::Pending->value))
                    ->toggle(),

                Tables\Filters\Filter::make('unlinked')
                    ->label('Unlinked (No Intent)')
                    ->query(fn (Builder $query): Builder => $query->whereNull('procurement_intent_id'))
                    ->toggle(),

                Tables\Filters\Filter::make('serialization_required')
                    ->label('Serialization Required')
                    ->query(fn (Builder $query): Builder => $query->where('serialization_required', true))
                    ->toggle(),

                Tables\Filters\Filter::make('handed_to_module_b')
                    ->label('Handed to Module B')
                    ->query(fn (Builder $query): Builder => $query->where('handed_to_module_b', true))
                    ->toggle(),

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
            'index' => Pages\ListInbounds::route('/'),
            'create' => Pages\CreateInbound::route('/create'),
            'view' => Pages\ViewInbound::route('/{record}'),
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
