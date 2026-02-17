<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Inventory\CaseResource;
use App\Filament\Resources\Inventory\InboundBatchResource;
use App\Filament\Resources\Inventory\SerializedBottleResource;
use App\Models\AuditLog;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Global Module B Audit Page.
 *
 * Provides a unified view of all audit events for Module B entities:
 * - SerializedBottle
 * - InventoryCase
 * - InboundBatch
 *
 * Features:
 * - Unified list of all Module B audit events
 * - Filters: entity_type, event_type, date_range, user, location
 * - Search: entity_id, serial_number, user_name
 * - CSV export for compliance
 */
class InventoryAudit extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Module B Audit Log';

    protected string $view = 'filament.pages.inventory-audit';

    /**
     * Get the page heading.
     */
    public function getHeading(): string|Htmlable
    {
        return 'Module B Audit Log';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return 'Unified audit trail for all inventory entities (Bottles, Cases, Batches)';
    }

    /**
     * Get the entity types for Module B.
     *
     * @return array<string, string>
     */
    protected function getEntityTypes(): array
    {
        return [
            SerializedBottle::class => 'Bottle',
            InventoryCase::class => 'Case',
            InboundBatch::class => 'Inbound Batch',
        ];
    }

    /**
     * Get event types for filtering.
     *
     * @return array<string, string>
     */
    protected function getEventTypes(): array
    {
        $events = [];

        // Bottle events
        $events[AuditLog::EVENT_BOTTLE_SERIALIZED] = 'Bottle Serialized';
        $events[AuditLog::EVENT_BOTTLE_STATE_CHANGE] = 'State Changed';
        $events[AuditLog::EVENT_BOTTLE_LOCATION_CHANGE] = 'Location Changed';
        $events[AuditLog::EVENT_BOTTLE_CUSTODY_CHANGE] = 'Custody Changed';
        $events[AuditLog::EVENT_BOTTLE_DESTROYED] = 'Bottle Destroyed';
        $events[AuditLog::EVENT_BOTTLE_MISSING] = 'Marked Missing';
        $events[AuditLog::EVENT_BOTTLE_MIS_SERIALIZED] = 'Flagged Mis-serialized';

        // Case events
        $events[AuditLog::EVENT_CASE_CREATED] = 'Case Created';
        $events[AuditLog::EVENT_CASE_LOCATION_CHANGE] = 'Case Location Changed';
        $events[AuditLog::EVENT_CASE_BROKEN] = 'Case Broken';
        $events[AuditLog::EVENT_CASE_BOTTLE_ADDED] = 'Bottle Added to Case';
        $events[AuditLog::EVENT_CASE_BOTTLE_REMOVED] = 'Bottle Removed from Case';

        // Batch events
        $events[AuditLog::EVENT_BATCH_CREATED] = 'Batch Created';
        $events[AuditLog::EVENT_BATCH_QUANTITY_UPDATE] = 'Quantity Updated';
        $events[AuditLog::EVENT_BATCH_DISCREPANCY_FLAGGED] = 'Discrepancy Flagged';
        $events[AuditLog::EVENT_BATCH_DISCREPANCY_RESOLVED] = 'Discrepancy Resolved';
        $events[AuditLog::EVENT_BATCH_SERIALIZATION_STARTED] = 'Serialization Started';
        $events[AuditLog::EVENT_BATCH_SERIALIZATION_COMPLETED] = 'Serialization Completed';

        // Generic events that may apply
        $events[AuditLog::EVENT_CREATED] = 'Created';
        $events[AuditLog::EVENT_UPDATED] = 'Updated';
        $events[AuditLog::EVENT_DELETED] = 'Deleted';

        return $events;
    }

    /**
     * Define the table for displaying audit logs.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable()
                    ->icon('heroicon-o-clock'),

                TextColumn::make('auditable_type')
                    ->label('Entity Type')
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            SerializedBottle::class => 'Bottle',
                            InventoryCase::class => 'Case',
                            InboundBatch::class => 'Batch',
                            default => class_basename($state),
                        };
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            SerializedBottle::class => 'success',
                            InventoryCase::class => 'info',
                            InboundBatch::class => 'warning',
                            default => 'gray',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            SerializedBottle::class => 'heroicon-o-beaker',
                            InventoryCase::class => 'heroicon-o-archive-box',
                            InboundBatch::class => 'heroicon-o-inbox-arrow-down',
                            default => 'heroicon-o-document',
                        };
                    })
                    ->sortable(),

                TextColumn::make('entity_identifier')
                    ->label('Entity ID')
                    ->state(function (AuditLog $record): string {
                        $auditable = $record->auditable;
                        if ($auditable === null) {
                            // Fallback to the UUID from the audit log
                            return (string) $record->auditable_id;
                        }

                        if ($auditable instanceof SerializedBottle) {
                            return $auditable->serial_number ?? (string) $auditable->id;
                        }

                        /** @var InventoryCase|InboundBatch $auditable */
                        return (string) $auditable->id;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            // Search by auditable_id (UUID)
                            $q->where('auditable_id', 'like', "%{$search}%");

                            // Search by serial number in serialized_bottles
                            $q->orWhereHas('auditable', function (Builder $morphQuery) use ($search): void {
                                // This will be polymorphic, so check if it's a bottle with serial_number
                                $morphQuery->where(function (Builder $innerQuery) use ($search): void {
                                    // For bottles, search serial_number
                                    $innerQuery->when(
                                        $innerQuery->getModel() instanceof SerializedBottle,
                                        fn (Builder $bq) => $bq->where('serial_number', 'like', "%{$search}%")
                                    );
                                });
                            });
                        });
                    })
                    ->copyable()
                    ->copyMessage('Entity ID copied')
                    ->limit(20)
                    ->tooltip(fn (AuditLog $record): string => (string) $record->auditable_id),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (AuditLog $record): string => $record->getEventLabel())
                    ->color(fn (AuditLog $record): string => $record->getEventColor())
                    ->icon(fn (AuditLog $record): string => $record->getEventIcon())
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->placeholder('System')
                    ->limit(20),

                TextColumn::make('changes_summary')
                    ->label('Changes')
                    ->state(function (AuditLog $record): string {
                        $newValues = $record->new_values ?? [];
                        $oldValues = $record->old_values ?? [];

                        $changedFields = array_unique(array_merge(array_keys($newValues), array_keys($oldValues)));
                        $count = count($changedFields);

                        if ($count === 0) {
                            return '—';
                        }

                        // Show first 2-3 field names
                        $preview = array_slice($changedFields, 0, 3);
                        $text = implode(', ', $preview);
                        if ($count > 3) {
                            $text .= ' +'.($count - 3).' more';
                        }

                        return $text;
                    })
                    ->limit(30)
                    ->tooltip(function (AuditLog $record): string {
                        $newValues = $record->new_values ?? [];
                        $oldValues = $record->old_values ?? [];

                        $changedFields = array_unique(array_merge(array_keys($newValues), array_keys($oldValues)));

                        if (empty($changedFields)) {
                            return 'No changes recorded';
                        }

                        return 'Changed fields: '.implode(', ', $changedFields);
                    }),

                TextColumn::make('location_name')
                    ->label('Location')
                    ->state(function (AuditLog $record): ?string {
                        $auditable = $record->auditable;
                        if ($auditable === null) {
                            return null;
                        }

                        // Get current location based on entity type
                        if ($auditable instanceof SerializedBottle) {
                            return $auditable->currentLocation?->name;
                        }
                        if ($auditable instanceof InventoryCase) {
                            return $auditable->currentLocation?->name;
                        }
                        if ($auditable instanceof InboundBatch) {
                            return $auditable->receivingLocation?->name;
                        }

                        return null;
                    })
                    ->icon('heroicon-o-map-pin')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('auditable_type')
                    ->label('Entity Type')
                    ->options([
                        SerializedBottle::class => 'Bottle',
                        InventoryCase::class => 'Case',
                        InboundBatch::class => 'Inbound Batch',
                    ])
                    ->multiple(),

                SelectFilter::make('event')
                    ->label('Event Type')
                    ->options($this->getEventTypes())
                    ->multiple()
                    ->searchable(),

                Filter::make('date_range')
                    ->label('Date Range')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('From'),
                        DatePicker::make('date_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['date_until'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'From: '.$data['date_from'];
                        }
                        if ($data['date_until'] ?? null) {
                            $indicators[] = 'Until: '.$data['date_until'];
                        }

                        return $indicators;
                    }),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location')
                    ->label('Location')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $locationId = $data['value'] ?? null;
                        if (! $locationId) {
                            return $query;
                        }

                        // Filter audit logs where the auditable entity is at the specified location
                        return $query->where(function (Builder $q) use ($locationId): void {
                            // For bottles at this location
                            $q->where(function (Builder $bottleQuery) use ($locationId): void {
                                $bottleQuery->where('auditable_type', SerializedBottle::class)
                                    ->whereIn('auditable_id', function ($subquery) use ($locationId): void {
                                        $subquery->select('id')
                                            ->from('serialized_bottles')
                                            ->where('current_location_id', $locationId);
                                    });
                            });

                            // Or for cases at this location
                            $q->orWhere(function (Builder $caseQuery) use ($locationId): void {
                                $caseQuery->where('auditable_type', InventoryCase::class)
                                    ->whereIn('auditable_id', function ($subquery) use ($locationId): void {
                                        $subquery->select('id')
                                            ->from('cases')
                                            ->where('current_location_id', $locationId);
                                    });
                            });

                            // Or for batches at this location
                            $q->orWhere(function (Builder $batchQuery) use ($locationId): void {
                                $batchQuery->where('auditable_type', InboundBatch::class)
                                    ->whereIn('auditable_id', function ($subquery) use ($locationId): void {
                                        $subquery->select('id')
                                            ->from('inbound_batches')
                                            ->where('receiving_location_id', $locationId);
                                    });
                            });
                        });
                    }),
            ])
            ->recordActions([
                Action::make('view_entity')
                    ->label('View Entity')
                    ->icon('heroicon-o-eye')
                    ->url(function (AuditLog $record): ?string {
                        $auditable = $record->auditable;
                        if ($auditable === null) {
                            return null;
                        }

                        if ($auditable instanceof SerializedBottle) {
                            return SerializedBottleResource::getUrl('view', ['record' => $auditable]);
                        }
                        if ($auditable instanceof InventoryCase) {
                            return CaseResource::getUrl('view', ['record' => $auditable]);
                        }
                        if ($auditable instanceof InboundBatch) {
                            return InboundBatchResource::getUrl('view', ['record' => $auditable]);
                        }

                        return null;
                    })
                    ->visible(fn (AuditLog $record): bool => $record->auditable !== null)
                    ->openUrlInNewTab(),

                Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->modalHeading(fn (AuditLog $record): string => $record->getEventLabel().' Details')
                    ->modalContent(function (AuditLog $record): View {
                        return view('filament.pages.partials.audit-detail-modal', [
                            'record' => $record,
                        ]);
                    })
                    ->modalWidth('lg'),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Get the base query for audit logs.
     */
    protected function getTableQuery(): Builder
    {
        return AuditLog::query()
            ->whereIn('auditable_type', [
                SerializedBottle::class,
                InventoryCase::class,
                InboundBatch::class,
            ])
            ->with(['user', 'auditable']);
    }

    /**
     * Get total count for display.
     */
    public function getTotalAuditCount(): int
    {
        return AuditLog::query()
            ->whereIn('auditable_type', [
                SerializedBottle::class,
                InventoryCase::class,
                InboundBatch::class,
            ])
            ->count();
    }

    /**
     * Get count by entity type.
     *
     * @return array<string, int>
     */
    public function getCountByEntityType(): array
    {
        $counts = AuditLog::query()
            ->whereIn('auditable_type', [
                SerializedBottle::class,
                InventoryCase::class,
                InboundBatch::class,
            ])
            ->select('auditable_type', DB::raw('count(*) as count'))
            ->groupBy('auditable_type')
            ->pluck('count', 'auditable_type')
            ->toArray();

        return [
            'bottles' => $counts[SerializedBottle::class] ?? 0,
            'cases' => $counts[InventoryCase::class] ?? 0,
            'batches' => $counts[InboundBatch::class] ?? 0,
        ];
    }

    /**
     * Export audit logs to CSV.
     */
    public function exportToCsv(): StreamedResponse
    {
        // Get all audit logs with current filters applied
        $query = $this->getTableQuery();

        // Apply active filters from the table
        $filters = $this->filters ?? [];

        if (! empty($filters['auditable_type']['values'] ?? [])) {
            $query->whereIn('auditable_type', $filters['auditable_type']['values']);
        }

        if (! empty($filters['event']['values'] ?? [])) {
            $query->whereIn('event', $filters['event']['values']);
        }

        if (! empty($filters['date_range']['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['date_range']['date_from']);
        }

        if (! empty($filters['date_range']['date_until'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['date_range']['date_until']);
        }

        if (! empty($filters['user_id']['value'] ?? null)) {
            $query->where('user_id', $filters['user_id']['value']);
        }

        $auditLogs = $query->orderBy('created_at', 'desc')->get();

        return response()->streamDownload(function () use ($auditLogs): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // CSV Header
            fputcsv($handle, [
                'Timestamp',
                'Entity Type',
                'Entity ID',
                'Serial Number',
                'Event',
                'User',
                'Changed Fields',
                'Old Values',
                'New Values',
                'Location',
            ]);

            /** @var AuditLog $log */
            foreach ($auditLogs as $log) {
                $auditable = $log->auditable;

                // Determine entity type label
                $entityType = match ($log->auditable_type) {
                    SerializedBottle::class => 'Bottle',
                    InventoryCase::class => 'Case',
                    InboundBatch::class => 'Batch',
                    default => class_basename((string) $log->auditable_type),
                };

                // Get serial number if it's a bottle
                $serialNumber = '';
                if ($auditable instanceof SerializedBottle) {
                    $serialNumber = $auditable->serial_number ?? '';
                }

                // Get location
                $location = '';
                if ($auditable instanceof SerializedBottle) {
                    $locationModel = $auditable->currentLocation;
                    $location = $locationModel !== null ? $locationModel->name : '';
                } elseif ($auditable instanceof InventoryCase) {
                    $locationModel = $auditable->currentLocation;
                    $location = $locationModel !== null ? $locationModel->name : '';
                } elseif ($auditable instanceof InboundBatch) {
                    $locationModel = $auditable->receivingLocation;
                    $location = $locationModel !== null ? $locationModel->name : '';
                }

                // Format changes
                $oldValues = $log->old_values ?? [];
                $newValues = $log->new_values ?? [];
                $changedFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

                fputcsv($handle, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $entityType,
                    $log->auditable_id,
                    $serialNumber,
                    $log->getEventLabel(),
                    $log->user !== null ? $log->user->name : 'System',
                    implode(', ', $changedFields),
                    json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                    json_encode($newValues, JSON_UNESCAPED_UNICODE),
                    $location,
                ]);
            }

            fclose($handle);
        }, 'module-b-audit-log-'.date('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
