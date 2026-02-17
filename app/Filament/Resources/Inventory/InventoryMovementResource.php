<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Filament\Resources\Inventory\InventoryMovementResource\Pages\ListInventoryMovements;
use App\Filament\Resources\Inventory\InventoryMovementResource\Pages\ViewInventoryMovement;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Movements';

    protected static ?string $modelLabel = 'Movement';

    protected static ?string $pluralModelLabel = 'Movements';

    public static function form(Schema $schema): Schema
    {
        // Movements are immutable - no create/edit form
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Movement ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Movement ID copied')
                    ->weight('bold')
                    ->limit(12)
                    ->tooltip(fn (InventoryMovement $record): string => $record->id),

                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (MovementType $state): string => $state->label())
                    ->color(fn (MovementType $state): string => $state->color())
                    ->icon(fn (MovementType $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('sourceLocation.name')
                    ->label('Source')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->limit(20)
                    ->placeholder('—'),

                TextColumn::make('destinationLocation.name')
                    ->label('Destination')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->limit(20)
                    ->placeholder('—'),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->state(fn (InventoryMovement $record): int => $record->items_count)
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('movementItems')
                            ->orderBy('movement_items_count', $direction);
                    }),

                TextColumn::make('trigger')
                    ->label('Trigger')
                    ->badge()
                    ->formatStateUsing(fn (MovementTrigger $state): string => $state->label())
                    ->color(fn (MovementTrigger $state): string => $state->color())
                    ->icon(fn (MovementTrigger $state): string => $state->icon())
                    ->sortable(),

                IconColumn::make('custody_changed')
                    ->label('Custody')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path-rounded-square')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (InventoryMovement $record): string => $record->custody_changed ? 'Custody Changed' : 'No Custody Change')
                    ->toggleable(),

                TextColumn::make('wms_event_id')
                    ->label('WMS Event')
                    ->searchable()
                    ->sortable()
                    ->limit(15)
                    ->placeholder('—')
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('WMS Event ID copied'),

                TextColumn::make('executed_at')
                    ->label('Executed At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('executor.name')
                    ->label('Executed By')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->limit(20)
                    ->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->options(collect(MovementType::cases())
                        ->mapWithKeys(fn (MovementType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->label('Movement Type')
                    ->multiple(),

                SelectFilter::make('trigger')
                    ->options(collect(MovementTrigger::cases())
                        ->mapWithKeys(fn (MovementTrigger $trigger) => [$trigger->value => $trigger->label()])
                        ->toArray())
                    ->label('Trigger')
                    ->multiple(),

                SelectFilter::make('source_location_id')
                    ->label('Source Location')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('destination_location_id')
                    ->label('Destination Location')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->preload(),

                Filter::make('executed_at')
                    ->label('Execution Date')
                    ->schema([
                        DatePicker::make('executed_from')
                            ->label('From'),
                        DatePicker::make('executed_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['executed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('executed_at', '>=', $date),
                            )
                            ->when(
                                $data['executed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('executed_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['executed_from'] ?? null) {
                            $indicators[] = 'From: '.$data['executed_from'];
                        }
                        if ($data['executed_until'] ?? null) {
                            $indicators[] = 'Until: '.$data['executed_until'];
                        }

                        return $indicators;
                    }),

                TernaryFilter::make('custody_changed')
                    ->label('Custody Change')
                    ->placeholder('All')
                    ->trueLabel('With Custody Change')
                    ->falseLabel('Without Custody Change'),

                TernaryFilter::make('has_wms_event')
                    ->label('WMS Event')
                    ->placeholder('All')
                    ->trueLabel('WMS Triggered')
                    ->falseLabel('Non-WMS')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('wms_event_id'),
                        false: fn (Builder $query) => $query->whereNull('wms_event_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Movements are immutable - no bulk actions
            ])
            ->defaultSort('executed_at', 'desc')
            ->poll('30s'); // Auto-refresh to show new movements
    }

    public static function getRelations(): array
    {
        return [
            // Movement items will be shown in detail view
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'view' => ViewInventoryMovement::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sourceLocation', 'destinationLocation', 'executor']);
    }

    /**
     * Movements are immutable - prevent creating directly.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Movements are immutable - prevent editing.
     */
    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * Movements are immutable - prevent deleting.
     */
    public static function canDelete($record): bool
    {
        return false;
    }
}
