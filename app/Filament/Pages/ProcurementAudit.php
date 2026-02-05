<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementAudit extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static ?string $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Procurement Audit Trail';

    protected static string $view = 'filament.pages.procurement-audit';

    /**
     * List of Procurement model types for filtering.
     *
     * @var array<string, string>
     */
    protected const PROCUREMENT_ENTITY_TYPES = [
        'App\Models\Procurement\ProcurementIntent' => 'Procurement Intent',
        'App\Models\Procurement\PurchaseOrder' => 'Purchase Order',
        'App\Models\Procurement\BottlingInstruction' => 'Bottling Instruction',
        'App\Models\Procurement\Inbound' => 'Inbound',
    ];

    /**
     * List of event types relevant to Procurement module.
     *
     * @var array<string, string>
     */
    protected const EVENT_TYPES = [
        AuditLog::EVENT_CREATED => 'Created',
        AuditLog::EVENT_UPDATED => 'Updated',
        AuditLog::EVENT_DELETED => 'Deleted',
        AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
        AuditLog::EVENT_LIFECYCLE_CHANGE => 'Lifecycle Changed',
        AuditLog::EVENT_FLAG_CHANGE => 'Flag Changed',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuditLog::query()
                    ->whereIn('auditable_type', array_keys(self::PROCUREMENT_ENTITY_TYPES))
                    ->with(['user'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date/Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (AuditLog $record): string => $record->getEventLabel())
                    ->color(fn (AuditLog $record): string => $record->getEventColor())
                    ->icon(fn (AuditLog $record): string => $record->getEventIcon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Entity Type')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => self::PROCUREMENT_ENTITY_TYPES[$state] ?? class_basename($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'App\Models\Procurement\ProcurementIntent' => 'success',
                        'App\Models\Procurement\PurchaseOrder' => 'info',
                        'App\Models\Procurement\BottlingInstruction' => 'warning',
                        'App\Models\Procurement\Inbound' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('Entity ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Entity ID copied')
                    ->limit(12)
                    ->tooltip(fn (AuditLog $record): string => (string) $record->auditable_id),
                Tables\Columns\TextColumn::make('entity_label')
                    ->label('Entity Label')
                    ->getStateUsing(function (AuditLog $record): string {
                        $auditable = $record->auditable;
                        if ($auditable === null) {
                            return '—';
                        }

                        // Use entity-specific labels
                        if ($auditable instanceof \App\Models\Procurement\ProcurementIntent) {
                            return $auditable->getProductLabel();
                        }

                        if ($auditable instanceof \App\Models\Procurement\PurchaseOrder) {
                            return $auditable->getProductLabel();
                        }

                        if ($auditable instanceof \App\Models\Procurement\BottlingInstruction) {
                            $liquidProduct = $auditable->liquidProduct;
                            if ($liquidProduct !== null) {
                                $wineVariant = $liquidProduct->wineVariant;
                                if ($wineVariant !== null && $wineVariant->wineMaster !== null) {
                                    return $wineVariant->wineMaster->name;
                                }
                            }

                            return 'Unknown Product';
                        }

                        if ($auditable instanceof \App\Models\Procurement\Inbound) {
                            return $auditable->warehouse.' ('.$auditable->quantity.' units)';
                        }

                        return '—';
                    })
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->default('System')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('changes_summary')
                    ->label('Changes')
                    ->getStateUsing(function (AuditLog $record): string {
                        if ($record->event === AuditLog::EVENT_CREATED) {
                            return 'New record created';
                        }
                        if ($record->event === AuditLog::EVENT_DELETED) {
                            return 'Record deleted';
                        }
                        $newValues = $record->new_values ?? [];
                        $oldValues = $record->old_values ?? [];

                        // Get status changes specifically
                        if (isset($newValues['status']) && isset($oldValues['status'])) {
                            $oldStatus = $oldValues['status_label'] ?? $oldValues['status'];
                            $newStatus = $newValues['status_label'] ?? $newValues['status'];

                            return "{$oldStatus} → {$newStatus}";
                        }

                        // Get ownership changes specifically
                        if (isset($newValues['ownership_flag']) && isset($oldValues['ownership_flag'])) {
                            $oldFlag = $oldValues['ownership_flag_label'] ?? $oldValues['ownership_flag'];
                            $newFlag = $newValues['ownership_flag_label'] ?? $newValues['ownership_flag'];

                            return "Ownership: {$oldFlag} → {$newFlag}";
                        }

                        $changedFields = array_keys(array_diff_assoc($newValues, $oldValues));
                        if (count($changedFields) === 0) {
                            return '—';
                        }
                        $count = count($changedFields);

                        return $count === 1
                            ? 'Changed: '.str_replace('_', ' ', $changedFields[0])
                            : "Changed {$count} fields";
                    })
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('auditable_type')
                    ->label('Entity Type')
                    ->options(self::PROCUREMENT_ENTITY_TYPES)
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Event Type')
                    ->options(self::EVENT_TYPES)
                    ->multiple(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From '.date('M j, Y', strtotime($data['from']));
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until '.date('M j, Y', strtotime($data['until']));
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (AuditLog $record): string => $record->getEventLabel().' - '.(self::PROCUREMENT_ENTITY_TYPES[$record->auditable_type] ?? class_basename($record->auditable_type)))
                    ->modalContent(fn (AuditLog $record) => view('filament.components.audit-detail', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('view_entity')
                    ->label('View Entity')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (AuditLog $record): ?string {
                        $auditable = $record->auditable;
                        if ($auditable === null) {
                            return null;
                        }

                        /** @var string $auditableId */
                        $auditableId = $auditable->getKey();

                        return match ($record->auditable_type) {
                            'App\Models\Procurement\ProcurementIntent' => route('filament.admin.resources.procurement/procurement-intents.view', ['record' => $auditableId]),
                            'App\Models\Procurement\PurchaseOrder' => route('filament.admin.resources.procurement/purchase-orders.view', ['record' => $auditableId]),
                            'App\Models\Procurement\BottlingInstruction' => route('filament.admin.resources.procurement/bottling-instructions.view', ['record' => $auditableId]),
                            'App\Models\Procurement\Inbound' => route('filament.admin.resources.procurement/inbounds.view', ['record' => $auditableId]),
                            default => null,
                        };
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (AuditLog $record): bool => $record->auditable !== null),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (): StreamedResponse {
                        return $this->exportToCsv();
                    }),
            ])
            ->emptyStateHeading('No Audit Events Found')
            ->emptyStateDescription('Procurement audit events will appear here as changes are made to Intents, Purchase Orders, Bottling Instructions, and Inbounds.')
            ->emptyStateIcon('heroicon-o-document-magnifying-glass')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Export audit logs to CSV.
     */
    protected function exportToCsv(): StreamedResponse
    {
        $query = AuditLog::query()
            ->whereIn('auditable_type', array_keys(self::PROCUREMENT_ENTITY_TYPES))
            ->with(['user', 'auditable'])
            ->orderByDesc('created_at');

        // Apply current filters
        $filterState = $this->getTableFiltersForm()->getState();

        if (! empty($filterState['auditable_type']['values'])) {
            $query->whereIn('auditable_type', $filterState['auditable_type']['values']);
        }

        if (! empty($filterState['event']['values'])) {
            $query->whereIn('event', $filterState['event']['values']);
        }

        if (! empty($filterState['created_at']['from'])) {
            $query->whereDate('created_at', '>=', $filterState['created_at']['from']);
        }

        if (! empty($filterState['created_at']['until'])) {
            $query->whereDate('created_at', '<=', $filterState['created_at']['until']);
        }

        if (! empty($filterState['user_id']['value'])) {
            $query->where('user_id', $filterState['user_id']['value']);
        }

        $records = $query->get();

        $filename = 'procurement-audit-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Header row
            fputcsv($handle, [
                'Date/Time',
                'Event',
                'Entity Type',
                'Entity ID',
                'Entity Label',
                'User',
                'Changed Fields',
                'Old Values',
                'New Values',
            ]);

            // Data rows
            foreach ($records as $record) {
                $entityLabel = '—';
                if ($record->auditable !== null) {
                    $auditable = $record->auditable;

                    if ($auditable instanceof \App\Models\Procurement\ProcurementIntent) {
                        $entityLabel = $auditable->getProductLabel();
                    } elseif ($auditable instanceof \App\Models\Procurement\PurchaseOrder) {
                        $entityLabel = $auditable->getProductLabel();
                    } elseif ($auditable instanceof \App\Models\Procurement\BottlingInstruction) {
                        $liquidProduct = $auditable->liquidProduct;
                        if ($liquidProduct !== null) {
                            $wineVariant = $liquidProduct->wineVariant;
                            if ($wineVariant !== null && $wineVariant->wineMaster !== null) {
                                $entityLabel = $wineVariant->wineMaster->name;
                            } else {
                                $entityLabel = 'Unknown Product';
                            }
                        } else {
                            $entityLabel = 'Unknown Product';
                        }
                    } elseif ($auditable instanceof \App\Models\Procurement\Inbound) {
                        $entityLabel = $auditable->warehouse.' ('.$auditable->quantity.' units)';
                    }
                }

                $changedFields = [];
                /** @var array<string, mixed>|null $newValues */
                $newValues = $record->new_values;
                /** @var array<string, mixed>|null $oldValues */
                $oldValues = $record->old_values;
                if ($newValues !== null && $oldValues !== null) {
                    $changedFields = array_keys(array_diff_key($newValues, $oldValues) + array_diff_key($oldValues, $newValues));
                    foreach (array_intersect_key($newValues, $oldValues) as $key => $value) {
                        if ($value !== $oldValues[$key]) {
                            $changedFields[] = $key;
                        }
                    }
                    $changedFields = array_unique($changedFields);
                }

                fputcsv($handle, [
                    $record->created_at->format('Y-m-d H:i:s'),
                    $record->getEventLabel(),
                    self::PROCUREMENT_ENTITY_TYPES[$record->auditable_type] ?? class_basename($record->auditable_type),
                    $record->auditable_id,
                    $entityLabel,
                    $record->user !== null ? $record->user->name : 'System',
                    implode(', ', $changedFields),
                    json_encode($record->old_values ?? []),
                    json_encode($record->new_values ?? []),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Get statistics for the page header.
     *
     * @return array{total_events: int, today_events: int, entities_tracked: int, users_active: int}
     */
    public function getStatistics(): array
    {
        $baseQuery = AuditLog::query()
            ->whereIn('auditable_type', array_keys(self::PROCUREMENT_ENTITY_TYPES));

        return [
            'total_events' => (clone $baseQuery)->count(),
            'today_events' => (clone $baseQuery)->whereDate('created_at', today())->count(),
            'entities_tracked' => count(self::PROCUREMENT_ENTITY_TYPES),
            'users_active' => (clone $baseQuery)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }
}
