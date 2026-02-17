<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommercialAudit extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Commercial Audit Trail';

    protected string $view = 'filament.pages.commercial-audit';

    /**
     * List of Commercial model types for filtering.
     *
     * @var array<string, string>
     */
    protected const COMMERCIAL_ENTITY_TYPES = [
        'App\Models\Commercial\Bundle' => 'Bundle',
        'App\Models\Commercial\Channel' => 'Channel',
        'App\Models\Commercial\DiscountRule' => 'Discount Rule',
        'App\Models\Commercial\Offer' => 'Offer',
        'App\Models\Commercial\OfferBenefit' => 'Offer Benefit',
        'App\Models\Commercial\OfferEligibility' => 'Offer Eligibility',
        'App\Models\Commercial\PriceBook' => 'Price Book',
        'App\Models\Commercial\PriceBookEntry' => 'Price Book Entry',
        'App\Models\Commercial\PricingPolicy' => 'Pricing Policy',
    ];

    /**
     * List of event types relevant to Commercial module.
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
                    ->whereIn('auditable_type', array_keys(self::COMMERCIAL_ENTITY_TYPES))
                    ->with(['user'])
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date/Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (AuditLog $record): string => $record->getEventLabel())
                    ->color(fn (AuditLog $record): string => $record->getEventColor())
                    ->icon(fn (AuditLog $record): string => $record->getEventIcon())
                    ->sortable(),
                TextColumn::make('auditable_type')
                    ->label('Entity Type')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => self::COMMERCIAL_ENTITY_TYPES[$state] ?? class_basename($state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('auditable_id')
                    ->label('Entity ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Entity ID copied')
                    ->limit(12)
                    ->tooltip(fn (AuditLog $record): string => (string) $record->auditable_id),
                TextColumn::make('entity_name')
                    ->label('Entity Name')
                    ->getStateUsing(function (AuditLog $record): string {
                        $auditable = $record->auditable;
                        if ($auditable === null) {
                            return '—';
                        }
                        // Try common name properties
                        /** @var object $auditable */
                        if (property_exists($auditable, 'name') || method_exists($auditable, 'getAttribute')) {
                            /** @var Model $auditable */
                            $name = $auditable->getAttribute('name');
                            if ($name !== null) {
                                return (string) $name;
                            }
                        }

                        return '—';
                    })
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->default('System')
                    ->toggleable(),
                TextColumn::make('changes_summary')
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
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('auditable_type')
                    ->label('Entity Type')
                    ->options(self::COMMERCIAL_ENTITY_TYPES)
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('event')
                    ->label('Event Type')
                    ->options(self::EVENT_TYPES)
                    ->multiple(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
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
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (AuditLog $record): string => $record->getEventLabel().' - '.(self::COMMERCIAL_ENTITY_TYPES[$record->auditable_type] ?? class_basename($record->auditable_type)))
                    ->modalContent(fn (AuditLog $record) => view('filament.components.audit-detail', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (): StreamedResponse {
                        return $this->exportToCsv();
                    }),
            ])
            ->emptyStateHeading('No Audit Events Found')
            ->emptyStateDescription('Commercial audit events will appear here as changes are made to Channels, Price Books, Offers, and other commercial entities.')
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
            ->whereIn('auditable_type', array_keys(self::COMMERCIAL_ENTITY_TYPES))
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

        $filename = 'commercial-audit-'.now()->format('Y-m-d-His').'.csv';

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
                'Entity Name',
                'User',
                'Changed Fields',
                'Old Values',
                'New Values',
            ]);

            // Data rows
            foreach ($records as $record) {
                $entityName = '—';
                if ($record->auditable !== null) {
                    /** @var Model $auditable */
                    $auditable = $record->auditable;
                    $name = $auditable->getAttribute('name');
                    if ($name !== null) {
                        $entityName = (string) $name;
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
                    self::COMMERCIAL_ENTITY_TYPES[$record->auditable_type] ?? class_basename($record->auditable_type),
                    $record->auditable_id,
                    $entityName,
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
            ->whereIn('auditable_type', array_keys(self::COMMERCIAL_ENTITY_TYPES));

        return [
            'total_events' => (clone $baseQuery)->count(),
            'today_events' => (clone $baseQuery)->whereDate('created_at', today())->count(),
            'entities_tracked' => count(self::COMMERCIAL_ENTITY_TYPES),
            'users_active' => (clone $baseQuery)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }
}
