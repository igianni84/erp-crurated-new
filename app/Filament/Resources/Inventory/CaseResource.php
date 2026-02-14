<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\CaseIntegrityStatus;
use App\Filament\Resources\Inventory\CaseResource\Pages;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CaseResource extends Resource
{
    protected static ?string $model = InventoryCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Cases';

    protected static ?string $modelLabel = 'Case';

    protected static ?string $pluralModelLabel = 'Cases';

    public static function form(Form $form): Form
    {
        // Cases are managed through detail page - no create/edit form
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Case ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Case ID copied')
                    ->weight('bold')
                    ->limit(12)
                    ->tooltip(fn (InventoryCase $record): string => $record->id),

                Tables\Columns\TextColumn::make('configuration')
                    ->label('Configuration')
                    ->state(function (InventoryCase $record): string {
                        $config = $record->caseConfiguration;
                        if ($config === null) {
                            return 'Unknown';
                        }

                        $name = $config->name ?? 'Unnamed';
                        $bottles = $config->bottles_per_case ?? 0;

                        return "{$name} ({$bottles} bottles)";
                    })
                    ->wrap()
                    ->limit(30),

                Tables\Columns\IconColumn::make('is_original')
                    ->label('Original')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (InventoryCase $record): string => $record->is_original ? 'Original Producer Case' : 'Repacked Case')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_breakable')
                    ->label('Breakable')
                    ->boolean()
                    ->trueIcon('heroicon-o-scissors')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (InventoryCase $record): string => $record->is_breakable ? 'Can be broken' : 'Cannot be broken')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('integrity_status')
                    ->label('Integrity')
                    ->badge()
                    ->formatStateUsing(fn (CaseIntegrityStatus $state): string => $state->label())
                    ->color(fn (CaseIntegrityStatus $state): string => $state->color())
                    ->icon(fn (CaseIntegrityStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('currentLocation.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->limit(20),

                Tables\Columns\TextColumn::make('bottle_count')
                    ->label('Bottles')
                    ->state(fn (InventoryCase $record): int => $record->bottle_count)
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('serializedBottles')
                            ->orderBy('serialized_bottles_count', $direction);
                    }),

                Tables\Columns\TextColumn::make('allocation_lineage')
                    ->label('Allocation')
                    ->state(function (InventoryCase $record): string {
                        $allocation = $record->allocation;
                        if ($allocation === null) {
                            return 'N/A';
                        }

                        return $allocation->getBottleSkuLabel();
                    })
                    ->description(fn (InventoryCase $record): ?string => $record->allocation !== null ? 'ID: '.substr($record->allocation_id, 0, 8).'...' : null)
                    ->icon('heroicon-o-link')
                    ->limit(25)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('broken_at')
                    ->label('Broken At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->visible(fn (): bool => true)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('integrity_status')
                    ->options(collect(CaseIntegrityStatus::cases())
                        ->mapWithKeys(fn (CaseIntegrityStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->label('Integrity Status'),

                Tables\Filters\SelectFilter::make('current_location_id')
                    ->label('Location')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_original')
                    ->label('Original Producer Case')
                    ->placeholder('All')
                    ->trueLabel('Original Only')
                    ->falseLabel('Repacked Only'),

                Tables\Filters\TernaryFilter::make('is_breakable')
                    ->label('Breakable')
                    ->placeholder('All')
                    ->trueLabel('Breakable Only')
                    ->falseLabel('Non-breakable Only'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Cases should not be bulk deleted - managed through detail page actions
            ])
            ->defaultSort('created_at', 'desc')
            ->recordClasses(function (InventoryCase $record): string {
                // Visual indicator for broken cases - highlight in danger color
                if ($record->isBroken()) {
                    return 'bg-danger-50 dark:bg-danger-950/20';
                }

                return '';
            });
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['id'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['caseConfiguration', 'currentLocation']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var InventoryCase $record */
        return 'Case #'.substr($record->id, 0, 8);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var InventoryCase $record */
        $configName = $record->caseConfiguration !== null ? $record->caseConfiguration->name : 'Unknown';

        return [
            'Configuration' => $configName,
            'Location' => $record->currentLocation !== null ? $record->currentLocation->name : '-',
            'Integrity' => $record->integrity_status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-B031
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCases::route('/'),
            'view' => Pages\ViewCase::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['caseConfiguration', 'allocation', 'currentLocation'])
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    /**
     * Prevent creating cases directly - they are created through serialization process.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
