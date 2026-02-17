<?php

namespace App\Filament\Resources\Procurement;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\SourcingModel;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages\AggregatedProcurementIntents;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages\CreateProcurementIntent;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages\ListProcurementIntents;
use App\Filament\Resources\Procurement\ProcurementIntentResource\Pages\ViewProcurementIntent;
use App\Models\Procurement\ProcurementIntent;
use App\Services\Procurement\ProcurementIntentService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;

class ProcurementIntentResource extends Resource
{
    protected static ?string $model = ProcurementIntent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Procurement Intents';

    protected static ?string $modelLabel = 'Procurement Intent';

    protected static ?string $pluralModelLabel = 'Procurement Intents';

    protected static ?string $slug = 'procurement/intents';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Form schema will be implemented in wizard stories (US-010 to US-013)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Intent ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Intent ID copied')
                    ->limit(8)
                    ->tooltip(fn (ProcurementIntent $record): string => $record->id),

                TextColumn::make('product')
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

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('trigger_type')
                    ->label('Trigger')
                    ->badge()
                    ->formatStateUsing(fn (ProcurementTriggerType $state): string => $state->label())
                    ->color(fn (ProcurementTriggerType $state): string => $state->color())
                    ->icon(fn (ProcurementTriggerType $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('sourcing_model')
                    ->label('Sourcing Model')
                    ->badge()
                    ->formatStateUsing(fn (SourcingModel $state): string => $state->label())
                    ->color(fn (SourcingModel $state): string => $state->color())
                    ->icon(fn (SourcingModel $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('preferred_inbound_location')
                    ->label('Preferred Location')
                    ->placeholder('Not specified')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProcurementIntentStatus $state): string => $state->label())
                    ->color(fn (ProcurementIntentStatus $state): string => $state->color())
                    ->icon(fn (ProcurementIntentStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('linked_objects_count')
                    ->label('Linked Objects')
                    ->state(fn (ProcurementIntent $record): int => $record->purchase_orders_count
                        + $record->bottling_instructions_count
                        + $record->inbounds_count)
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                IconColumn::make('awaiting_action')
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

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
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

                SelectFilter::make('trigger_type')
                    ->options(collect(ProcurementTriggerType::cases())
                        ->mapWithKeys(fn (ProcurementTriggerType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Trigger Type'),

                SelectFilter::make('sourcing_model')
                    ->options(collect(SourcingModel::cases())
                        ->mapWithKeys(fn (SourcingModel $model) => [$model->value => $model->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Sourcing Model'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Procurement Intents')
                        ->modalDescription(function (Collection $records): string {
                            $draftCount = 0;
                            foreach ($records as $record) {
                                /** @var ProcurementIntent $record */
                                if ($record->status === ProcurementIntentStatus::Draft) {
                                    $draftCount++;
                                }
                            }

                            return sprintf(
                                'Are you sure you want to approve %d procurement intent(s)? Only draft intents will be approved.',
                                $draftCount
                            );
                        })
                        ->modalSubmitActionLabel('Approve Selected')
                        ->action(function (Collection $records): void {
                            $service = app(ProcurementIntentService::class);
                            $approved = 0;
                            $skipped = 0;
                            $errors = [];

                            foreach ($records as $record) {
                                /** @var ProcurementIntent $record */
                                if ($record->status !== ProcurementIntentStatus::Draft) {
                                    $skipped++;

                                    continue;
                                }

                                try {
                                    $service->approve($record);
                                    $approved++;
                                } catch (InvalidArgumentException $e) {
                                    $errors[] = "Intent {$record->id}: {$e->getMessage()}";
                                }
                            }

                            if ($approved > 0) {
                                Notification::make()
                                    ->title("{$approved} intent(s) approved successfully")
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title("{$skipped} intent(s) skipped")
                                    ->body('Only draft intents can be approved.')
                                    ->warning()
                                    ->send();
                            }

                            if (count($errors) > 0) {
                                Notification::make()
                                    ->title('Some approvals failed')
                                    ->body(implode("\n", $errors))
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
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
            'index' => ListProcurementIntents::route('/'),
            'create' => CreateProcurementIntent::route('/create'),
            'view' => ViewProcurementIntent::route('/{record}'),
            'aggregated' => AggregatedProcurementIntents::route('/aggregated'),
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
