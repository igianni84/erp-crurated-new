<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InboundBatchResource extends Resource
{
    protected static ?string $model = InboundBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Inbound Batches';

    protected static ?string $modelLabel = 'Inbound Batch';

    protected static ?string $pluralModelLabel = 'Inbound Batches';

    public static function form(Form $form): Form
    {
        // Form will be implemented in US-B018 (Manual Inbound Batch creation)
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Batch ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Batch ID copied')
                    ->weight('bold')
                    ->limit(8)
                    ->tooltip(fn (InboundBatch $record): string => $record->id),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'producer' => 'success',
                        'supplier' => 'info',
                        'transfer' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_reference_type')
                    ->label('Product Reference')
                    ->formatStateUsing(function (InboundBatch $record): string {
                        $type = class_basename($record->product_reference_type);

                        return $type.' #'.substr((string) $record->product_reference_id, 0, 8);
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('product_reference_id', 'like', "%{$search}%");
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_expected')
                    ->label('Expected')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Received')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->color(fn (InboundBatch $record): string => $record->hasDiscrepancy() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('packaging_type')
                    ->label('Packaging')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('receivingLocation.name')
                    ->label('Receiving Location')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->limit(20),

                Tables\Columns\TextColumn::make('received_date')
                    ->label('Received Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('serialization_status')
                    ->label('Serialization')
                    ->badge()
                    ->formatStateUsing(fn (InboundBatchStatus $state): string => $state->label())
                    ->color(fn (InboundBatchStatus $state): string => $state->color())
                    ->icon(fn (InboundBatchStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('ownership_type')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                    ->color(fn (OwnershipType $state): string => $state->color())
                    ->icon(fn (OwnershipType $state): string => $state->icon())
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('has_discrepancy')
                    ->label('Discrepancy')
                    ->state(fn (InboundBatch $record): bool => $record->hasDiscrepancy())
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('quantity_expected != quantity_received '.$direction);
                    }),

                Tables\Columns\IconColumn::make('pending_serialization_indicator')
                    ->label('Pending')
                    ->state(fn (InboundBatch $record): bool => $record->serialization_status->canStartSerialization() && $record->remaining_unserialized > 0)
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(serialization_status IN ('pending_serialization', 'partially_serialized')) ".$direction);
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('serialization_status')
                    ->options(collect(InboundBatchStatus::cases())
                        ->mapWithKeys(fn (InboundBatchStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Serialization Status'),

                Tables\Filters\SelectFilter::make('receiving_location_id')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->label('Receiving Location'),

                Tables\Filters\SelectFilter::make('ownership_type')
                    ->options(collect(OwnershipType::cases())
                        ->mapWithKeys(fn (OwnershipType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Ownership Type'),

                Tables\Filters\Filter::make('received_date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('received_from')
                            ->label('Received From'),
                        \Filament\Forms\Components\DatePicker::make('received_until')
                            ->label('Received Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['received_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_date', '>=', $date),
                            )
                            ->when(
                                $data['received_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['received_from'] ?? null) {
                            $indicators['received_from'] = 'From '.\Carbon\Carbon::parse($data['received_from'])->toFormattedDateString();
                        }

                        if ($data['received_until'] ?? null) {
                            $indicators['received_until'] = 'Until '.\Carbon\Carbon::parse($data['received_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),

                Tables\Filters\Filter::make('has_discrepancy')
                    ->label('Has Discrepancy')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('quantity_expected', '!=', 'quantity_received'))
                    ->toggle(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('received_date', 'desc')
            ->recordClasses(function (InboundBatch $record): string {
                // Highlight rows with discrepancy in red
                if ($record->serialization_status === InboundBatchStatus::Discrepancy) {
                    return 'bg-danger-50 dark:bg-danger-950/20';
                }

                // Highlight rows pending serialization in yellow
                if ($record->serialization_status->canStartSerialization() && $record->remaining_unserialized > 0) {
                    return 'bg-warning-50 dark:bg-warning-950/20';
                }

                return '';
            });
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-B016
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInboundBatches::route('/'),
            'view' => Pages\ViewInboundBatch::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['receivingLocation'])
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
