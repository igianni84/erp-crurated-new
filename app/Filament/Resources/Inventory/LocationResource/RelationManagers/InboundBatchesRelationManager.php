<?php

namespace App\Filament\Resources\Inventory\LocationResource\RelationManagers;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InboundBatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'inboundBatches';

    protected static ?string $title = 'Inbound Batches';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Batch ID')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->limit(8),

                TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('quantity_received')
                    ->label('Qty Received')
                    ->numeric()
                    ->suffix(' bottles')
                    ->sortable(),

                TextColumn::make('serialization_status')
                    ->label('Serialization')
                    ->badge()
                    ->formatStateUsing(fn (InboundBatchStatus $state): string => $state->label())
                    ->color(fn (InboundBatchStatus $state): string => $state->color())
                    ->icon(fn (InboundBatchStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('ownership_type')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                    ->color(fn (OwnershipType $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('received_date')
                    ->label('Received')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('serialization_status')
                    ->options(collect(InboundBatchStatus::cases())
                        ->mapWithKeys(fn (InboundBatchStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Serialization Status'),

                SelectFilter::make('ownership_type')
                    ->options(collect(OwnershipType::cases())
                        ->mapWithKeys(fn (OwnershipType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->label('Ownership'),
            ])
            ->defaultSort('received_date', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
