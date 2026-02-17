<?php

namespace App\Filament\Resources\Inventory\LocationResource\RelationManagers;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\OwnershipType;
use App\Models\Inventory\SerializedBottle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SerializedBottlesRelationManager extends RelationManager
{
    protected static string $relationship = 'serializedBottles';

    protected static ?string $title = 'Serialized Bottles';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Serial number copied')
                    ->weight('bold'),

                TextColumn::make('wine_label')
                    ->label('Wine')
                    ->getStateUsing(function (SerializedBottle $record): string {
                        $variant = $record->wineVariant;
                        if ($variant === null) {
                            return 'Unknown';
                        }
                        $master = $variant->wineMaster;
                        $name = $master !== null ? $master->name : 'Unknown Wine';
                        $vintage = $variant->vintage_year ?? 'NV';

                        return "{$name} {$vintage}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('wineVariant', function (Builder $q) use ($search): void {
                            $q->whereHas('wineMaster', function (Builder $q2) use ($search): void {
                                $q2->where('name', 'like', "%{$search}%");
                            })
                                ->orWhere('vintage_year', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),

                TextColumn::make('format.name')
                    ->label('Format')
                    ->default('Standard')
                    ->sortable(),

                TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (BottleState $state): string => $state->label())
                    ->color(fn (BottleState $state): string => $state->color())
                    ->icon(fn (BottleState $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('ownership_type')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                    ->color(fn (OwnershipType $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('serialized_at')
                    ->label('Serialized')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->options(collect(BottleState::cases())
                        ->mapWithKeys(fn (BottleState $state) => [$state->value => $state->label()])
                        ->toArray())
                    ->multiple()
                    ->label('State'),

                SelectFilter::make('ownership_type')
                    ->options(collect(OwnershipType::cases())
                        ->mapWithKeys(fn (OwnershipType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Ownership'),
            ])
            ->defaultSort('serialized_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['wineVariant.wineMaster', 'format']));
    }
}
