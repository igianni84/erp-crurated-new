<?php

namespace App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;

use App\Filament\Resources\Procurement\ProcurementIntentResource;
use App\Models\Procurement\ProcurementIntent;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated view of Procurement Intents grouped by product.
 *
 * This page provides a view of demand aggregated by product reference,
 * useful for identifying volumes for bulk procurement decisions.
 */
class AggregatedProcurementIntents extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ProcurementIntentResource::class;

    protected string $view = 'filament.resources.procurement.procurement-intent-resource.pages.aggregated-procurement-intents';

    protected static ?string $title = 'Procurement Intents - Aggregated by Product';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    /**
     * Get the header actions for this page.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_list')
                ->label('Back to Standard List')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('index')),
            Action::make('create')
                ->label('Create Intent')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => static::getResource()::getUrl('create')),
        ];
    }

    /**
     * Configure the table for aggregated view.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAggregatedQuery())
            ->columns([
                TextColumn::make('product_label')
                    ->label('Product')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // Search is handled at the aggregate level
                        return $query->having('product_label', 'like', "%{$search}%");
                    })
                    ->wrap()
                    ->weight('bold'),

                TextColumn::make('total_quantity')
                    ->label('Total Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('intents_count')
                    ->label('Intents')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('draft_count')
                    ->label('Draft')
                    ->numeric()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('approved_count')
                    ->label('Approved')
                    ->numeric()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('executed_count')
                    ->label('Executed')
                    ->numeric()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                TextColumn::make('closed_count')
                    ->label('Closed')
                    ->numeric()
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('product_reference_type')
                    ->label('Product Type')
                    ->options([
                        'sellable_skus' => 'Bottle SKU',
                        'liquid_products' => 'Liquid Product',
                    ]),

                Filter::make('has_draft')
                    ->label('Has Draft Intents')
                    ->query(fn (Builder $query): Builder => $query->having('draft_count', '>', 0))
                    ->toggle(),

                Filter::make('high_volume')
                    ->label('High Volume (>100)')
                    ->query(fn (Builder $query): Builder => $query->having('total_quantity', '>', 100))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('view_intents')
                    ->label('View Intents')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Model $record): string => 'Intents for: '.$this->getRecordAttribute($record, 'product_label'))
                    ->modalContent(fn (Model $record): View => view(
                        'filament.resources.procurement.procurement-intent-resource.pages.partials.intent-list-modal',
                        ['intents' => $this->getIntentsForProduct($record)]
                    ))
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('total_quantity', 'desc')
            ->striped()
            ->emptyStateHeading('No aggregated data available')
            ->emptyStateDescription('Create procurement intents to see aggregated demand by product.')
            ->emptyStateIcon('heroicon-o-chart-bar-square');
    }

    /**
     * Get the aggregated query for intents grouped by product reference.
     */
    protected function getAggregatedQuery(): Builder
    {
        return ProcurementIntent::query()
            ->select([
                'product_reference_type',
                'product_reference_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(*) as intents_count'),
                DB::raw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count"),
                DB::raw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count"),
                DB::raw("SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed_count"),
                DB::raw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count"),
            ])
            ->selectRaw($this->getProductLabelSelect())
            ->leftJoin('sellable_skus', function ($join) {
                $join->on('procurement_intents.product_reference_id', '=', 'sellable_skus.id')
                    ->where('procurement_intents.product_reference_type', '=', 'sellable_skus');
            })
            ->leftJoin('liquid_products', function ($join) {
                $join->on('procurement_intents.product_reference_id', '=', 'liquid_products.id')
                    ->where('procurement_intents.product_reference_type', '=', 'liquid_products');
            })
            ->leftJoin('wine_variants as wv_sku', 'sellable_skus.wine_variant_id', '=', 'wv_sku.id')
            ->leftJoin('wine_masters as wm_sku', 'wv_sku.wine_master_id', '=', 'wm_sku.id')
            ->leftJoin('formats', 'sellable_skus.format_id', '=', 'formats.id')
            ->leftJoin('wine_variants as wv_lp', 'liquid_products.wine_variant_id', '=', 'wv_lp.id')
            ->leftJoin('wine_masters as wm_lp', 'wv_lp.wine_master_id', '=', 'wm_lp.id')
            ->whereNull('procurement_intents.deleted_at')
            ->groupBy([
                'procurement_intents.product_reference_type',
                'procurement_intents.product_reference_id',
                'wm_sku.name',
                'wv_sku.vintage_year',
                'formats.label',
                'wm_lp.name',
                'wv_lp.vintage_year',
            ]);
    }

    /**
     * Get the SQL expression for product label.
     */
    protected function getProductLabelSelect(): string
    {
        return "CASE
            WHEN procurement_intents.product_reference_type = 'sellable_skus'
            THEN CONCAT(COALESCE(wm_sku.name, 'Unknown'), ' ', COALESCE(wv_sku.vintage_year, ''), ' - ', COALESCE(formats.label, 'Unknown Format'))
            WHEN procurement_intents.product_reference_type = 'liquid_products'
            THEN CONCAT(COALESCE(wm_lp.name, 'Unknown'), ' ', COALESCE(wv_lp.vintage_year, ''), ' (Liquid)')
            ELSE 'Unknown Product'
        END as product_label";
    }

    /**
     * Get intents for a specific product (for the modal).
     *
     * @return Collection<int, ProcurementIntent>
     */
    protected function getIntentsForProduct(Model $record): Collection
    {
        $productReferenceType = $this->getRecordAttribute($record, 'product_reference_type');
        $productReferenceId = $this->getRecordAttribute($record, 'product_reference_id');

        if (! is_string($productReferenceType) || ! is_string($productReferenceId)) {
            return collect();
        }

        return ProcurementIntent::query()
            ->where('product_reference_type', $productReferenceType)
            ->where('product_reference_id', $productReferenceId)
            ->with(['productReference', 'approver'])
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Safely get attribute from record (handles both models and stdClass).
     */
    protected function getRecordAttribute(Model $record, string $attribute): mixed
    {
        return $record->getAttribute($attribute);
    }
}
