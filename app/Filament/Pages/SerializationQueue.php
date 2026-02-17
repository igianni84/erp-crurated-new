<?php

namespace App\Filament\Pages;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InboundBatchResource;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Services\Inventory\SerializationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class SerializationQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Serialization Queue';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Serialization Queue';

    protected string $view = 'filament.pages.serialization-queue';

    /**
     * Get the page heading.
     */
    public function getHeading(): string|Htmlable
    {
        return 'Serialization Queue';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return 'Batches eligible for serialization at authorized locations';
    }

    /**
     * Configure the table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('id')
                    ->label('Batch ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Batch ID copied')
                    ->weight('bold')
                    ->limit(8)
                    ->tooltip(fn (InboundBatch $record): string => $record->id),

                TextColumn::make('product_reference_display')
                    ->label('Product')
                    ->getStateUsing(function (InboundBatch $record): string {
                        $type = class_basename($record->product_reference_type);

                        return "{$type} #{$record->product_reference_id}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('product_reference_id', 'like', "%{$search}%");
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('product_reference_id', $direction);
                    })
                    ->icon('heroicon-o-cube'),

                TextColumn::make('remaining_unserialized')
                    ->label('Qty Remaining')
                    ->getStateUsing(fn (InboundBatch $record): int => $record->remaining_unserialized)
                    ->numeric()
                    ->suffix(' bottles')
                    ->color('warning')
                    ->weight('bold')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Calculate remaining as quantity_received - serialized_count
                        // This is approximate for sorting (actual count is computed)
                        return $query->orderByRaw('quantity_received '.$direction);
                    }),

                TextColumn::make('receivingLocation.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin'),

                TextColumn::make('allocation_lineage')
                    ->label('Allocation Lineage')
                    ->getStateUsing(function (InboundBatch $record): string {
                        if ($record->allocation_id) {
                            return '#'.substr($record->allocation_id, 0, 8).'...';
                        }

                        return 'No allocation';
                    })
                    ->badge()
                    ->color(fn (InboundBatch $record): string => $record->hasAllocationLineage() ? 'success' : 'danger')
                    ->copyable()
                    ->copyableState(fn (InboundBatch $record): ?string => $record->allocation_id)
                    ->copyMessage('Allocation ID copied')
                    ->tooltip(fn (InboundBatch $record): ?string => $record->allocation_id),

                TextColumn::make('serialization_status')
                    ->label('Status')
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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('received_date')
                    ->label('Received')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('receiving_location_id')
                    ->label('Location')
                    ->options(function (): array {
                        // Only show locations that are authorized for serialization
                        return Location::query()
                            ->where('serialization_authorized', true)
                            ->where('status', 'active')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable(),

                Filter::make('received_date_range')
                    ->schema([
                        DatePicker::make('received_from')
                            ->label('Received From'),
                        DatePicker::make('received_until')
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
                            $indicators['received_from'] = 'From '.Carbon::parse($data['received_from'])->toFormattedDateString();
                        }

                        if ($data['received_until'] ?? null) {
                            $indicators['received_until'] = 'Until '.Carbon::parse($data['received_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),

                SelectFilter::make('serialization_status')
                    ->label('Serialization Status')
                    ->options([
                        InboundBatchStatus::PendingSerialization->value => InboundBatchStatus::PendingSerialization->label(),
                        InboundBatchStatus::PartiallySerialized->value => InboundBatchStatus::PartiallySerialized->label(),
                    ])
                    ->default(null),
            ])
            ->recordActions([
                Action::make('startSerialization')
                    ->label('Serialize')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Start Serialization')
                    ->modalDescription(function (InboundBatch $record): string {
                        $remaining = $record->remaining_unserialized;

                        return "You are about to serialize bottles from this inbound batch. {$remaining} bottles are available for serialization.";
                    })
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Quantity to Serialize')
                            ->helperText(fn (InboundBatch $record): string => "Maximum: {$record->remaining_unserialized} bottles")
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (InboundBatch $record): int => $record->remaining_unserialized)
                            ->default(fn (InboundBatch $record): int => $record->remaining_unserialized),
                    ])
                    ->action(function (InboundBatch $record, array $data): void {
                        $quantity = (int) $data['quantity'];
                        $user = auth()->user();

                        if (! $user) {
                            Notification::make()
                                ->title('Authentication required')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $serializationService = app(SerializationService::class);
                            $bottles = $serializationService->serializeBatch($record, $quantity, $user);

                            Notification::make()
                                ->title('Serialization Complete')
                                ->body("{$bottles->count()} bottles have been serialized successfully.")
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Serialization Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('viewDetails')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (InboundBatch $record): string => InboundBatchResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->defaultSort('received_date', 'desc')
            ->emptyStateHeading('No batches in queue')
            ->emptyStateDescription('All batches have been fully serialized or no batches exist at authorized locations.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s');
    }

    /**
     * Get the base query for the serialization queue.
     *
     * Only includes batches that:
     * - Have pending_serialization or partially_serialized status
     * - Are at locations with serialization_authorized = true
     * - Have remaining unserialized bottles
     */
    protected function getTableQuery(): Builder
    {
        return InboundBatch::query()
            ->with(['receivingLocation', 'allocation'])
            ->whereIn('serialization_status', [
                InboundBatchStatus::PendingSerialization->value,
                InboundBatchStatus::PartiallySerialized->value,
            ])
            // Only batches at locations where serialization is authorized
            ->whereHas('receivingLocation', function (Builder $query): void {
                $query->where('serialization_authorized', true)
                    ->where('status', 'active');
            });
    }

    /**
     * Get the queue statistics.
     *
     * @return array{total_batches: int, pending_count: int, partial_count: int, total_bottles_remaining: int}
     */
    public function getQueueStats(): array
    {
        $baseQuery = $this->getTableQuery();

        $totalBatches = $baseQuery->count();

        $pendingCount = (clone $baseQuery)
            ->where('serialization_status', InboundBatchStatus::PendingSerialization->value)
            ->count();

        $partialCount = (clone $baseQuery)
            ->where('serialization_status', InboundBatchStatus::PartiallySerialized->value)
            ->count();

        // Calculate total bottles remaining
        // We need to compute remaining_unserialized for each batch
        /** @var Collection<int, InboundBatch> $batches */
        $batches = (clone $baseQuery)->get();
        $totalBottlesRemaining = 0;
        foreach ($batches as $batch) {
            $totalBottlesRemaining += $batch->remaining_unserialized;
        }

        return [
            'total_batches' => $totalBatches,
            'pending_count' => $pendingCount,
            'partial_count' => $partialCount,
            'total_bottles_remaining' => $totalBottlesRemaining,
        ];
    }

    /**
     * Get the URL to view an inbound batch.
     */
    public function getBatchViewUrl(InboundBatch $batch): string
    {
        return InboundBatchResource::getUrl('view', ['record' => $batch]);
    }
}
