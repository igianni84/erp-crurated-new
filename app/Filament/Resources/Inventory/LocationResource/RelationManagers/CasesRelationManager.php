<?php

namespace App\Filament\Resources\Inventory\LocationResource\RelationManagers;

use App\Enums\Inventory\CaseIntegrityStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CasesRelationManager extends RelationManager
{
    protected static string $relationship = 'cases';

    protected static ?string $title = 'Cases';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Case ID')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->limit(8),

                TextColumn::make('caseConfiguration.name')
                    ->label('Configuration')
                    ->default('Standard')
                    ->sortable(),

                TextColumn::make('integrity_status')
                    ->label('Integrity')
                    ->badge()
                    ->formatStateUsing(fn (CaseIntegrityStatus $state): string => $state->label())
                    ->color(fn (CaseIntegrityStatus $state): string => $state->color())
                    ->icon(fn (CaseIntegrityStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('serialized_bottles_count')
                    ->label('Bottles')
                    ->counts('serializedBottles')
                    ->numeric()
                    ->suffix(' bottles')
                    ->sortable(),

                IconColumn::make('is_original')
                    ->label('Original')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('integrity_status')
                    ->options(collect(CaseIntegrityStatus::cases())
                        ->mapWithKeys(fn (CaseIntegrityStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->label('Integrity'),
            ])
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('caseConfiguration')->withCount('serializedBottles'));
    }
}
