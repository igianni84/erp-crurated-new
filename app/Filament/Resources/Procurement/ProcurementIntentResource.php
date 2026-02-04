<?php

namespace App\Filament\Resources\Procurement;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\SourcingModel;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;
use App\Models\Procurement\ProcurementIntent;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcurementIntentResource extends Resource
{
    protected static ?string $model = ProcurementIntent::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Procurement Intents';

    protected static ?string $modelLabel = 'Procurement Intent';

    protected static ?string $pluralModelLabel = 'Procurement Intents';

    protected static ?string $slug = 'procurement/intents';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form schema will be implemented in wizard stories (US-010 to US-013)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Intent ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Intent ID copied')
                    ->limit(8)
                    ->tooltip(fn (ProcurementIntent $record): string => $record->id),

                Tables\Columns\TextColumn::make('product')
                    ->label('Product')
                    ->state(fn (ProcurementIntent $record): string => $record->getProductLabel())
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
                                                ->whereColumn('sellable_skus.id', 'procurement_intents.product_reference_id')
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
                                                ->whereColumn('liquid_products.id', 'procurement_intents.product_reference_id')
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

                Tables\Columns\TextColumn::make('trigger_type')
                    ->label('Trigger')
                    ->badge()
                    ->formatStateUsing(fn (ProcurementTriggerType $state): string => $state->label())
                    ->color(fn (ProcurementTriggerType $state): string => $state->color())
                    ->icon(fn (ProcurementTriggerType $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('sourcing_model')
                    ->label('Sourcing Model')
                    ->badge()
                    ->formatStateUsing(fn (SourcingModel $state): string => $state->label())
                    ->color(fn (SourcingModel $state): string => $state->color())
                    ->icon(fn (SourcingModel $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('preferred_inbound_location')
                    ->label('Preferred Location')
                    ->placeholder('Not specified')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProcurementIntentStatus $state): string => $state->label())
                    ->color(fn (ProcurementIntentStatus $state): string => $state->color())
                    ->icon(fn (ProcurementIntentStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('linked_objects_count')
                    ->label('Linked Objects')
                    ->state(fn (ProcurementIntent $record): int => $record->purchase_orders_count
                        + $record->bottling_instructions_count
                        + $record->inbounds_count)
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('awaiting_action')
                    ->label('Awaiting Action')
                    ->boolean()
                    ->state(fn (ProcurementIntent $record): bool => $record->status !== ProcurementIntentStatus::Closed
                        && $record->status !== ProcurementIntentStatus::Draft
                        && ($record->purchase_orders_count + $record->bottling_instructions_count + $record->inbounds_count) === 0)
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('warning')
                    ->tooltip(fn (ProcurementIntent $record): ?string => $record->status !== ProcurementIntentStatus::Closed
                        && $record->status !== ProcurementIntentStatus::Draft
                        && ($record->purchase_orders_count + $record->bottling_instructions_count + $record->inbounds_count) === 0
                        ? 'No linked objects - awaiting action'
                        : null),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ProcurementIntentStatus::cases())
                        ->mapWithKeys(fn (ProcurementIntentStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        ProcurementIntentStatus::Draft->value,
                        ProcurementIntentStatus::Approved->value,
                        ProcurementIntentStatus::Executed->value,
                    ])
                    ->multiple()
                    ->label('Status'),

                Tables\Filters\SelectFilter::make('trigger_type')
                    ->options(collect(ProcurementTriggerType::cases())
                        ->mapWithKeys(fn (ProcurementTriggerType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Trigger Type'),

                Tables\Filters\SelectFilter::make('sourcing_model')
                    ->options(collect(SourcingModel::cases())
                        ->mapWithKeys(fn (SourcingModel $model) => [$model->value => $model->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Sourcing Model'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Bulk approve action will be implemented in US-017
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['productReference'])
                ->withCount(['purchaseOrders', 'bottlingInstructions', 'inbounds']));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-014 (detail tabs)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcurementIntents::route('/'),
            'view' => Pages\ViewProcurementIntent::route('/{record}'),
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
