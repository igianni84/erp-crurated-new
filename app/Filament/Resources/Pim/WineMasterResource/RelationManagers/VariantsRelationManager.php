<?php

namespace App\Filament\Resources\Pim\WineMasterResource\RelationManagers;

use App\Enums\ProductLifecycleStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'wineVariants';

    protected static ?string $title = 'Wine Variants';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vintage_year')
                    ->label('Vintage')
                    ->sortable()
                    ->placeholder('NV'),

                TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProductLifecycleStatus $state): string => $state->label())
                    ->color(fn (ProductLifecycleStatus $state): string => $state->color())
                    ->icon(fn (ProductLifecycleStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('sellable_skus_count')
                    ->label('Sellable SKUs')
                    ->counts('sellableSkus')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('lwin_code')
                    ->label('LWIN')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('lifecycle_status')
                    ->options(collect(ProductLifecycleStatus::cases())
                        ->mapWithKeys(fn (ProductLifecycleStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),
            ])
            ->defaultSort('vintage_year', 'desc')
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('sellableSkus'));
    }
}
