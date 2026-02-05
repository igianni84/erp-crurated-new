<?php

namespace App\Filament\Pages\Finance;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Audit Export page for Finance module.
 *
 * This page allows compliance officers to export audit logs with:
 * - Filters: entity type, date range, user
 * - Export formats: CSV, JSON
 * - Include: entity_id, event_type, user, timestamp, changes
 */
class AuditExport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'Audit Export';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 65;

    protected static ?string $title = 'Audit Export';

    protected static string $view = 'filament.pages.finance.audit-export';

    /**
     * Filter by entity type (auditable_type).
     */
    public ?string $filterEntityType = null;

    /**
     * Start date filter.
     */
    public string $filterStartDate = '';

    /**
     * End date filter.
     */
    public string $filterEndDate = '';

    /**
     * Filter by user ID.
     */
    public ?string $filterUserId = null;

    /**
     * User search query.
     */
    public string $userSearch = '';

    /**
     * Export format (csv or json).
     */
    public string $exportFormat = 'csv';

    /**
     * Pagination: items per page.
     */
    public int $perPage = 50;

    /**
     * Current page.
     */
    public int $currentPage = 1;

    /**
     * Mount the page with default filters.
     */
    public function mount(): void
    {
        // Default to last 30 days
        $this->filterEndDate = now()->format('Y-m-d');
        $this->filterStartDate = now()->subDays(30)->format('Y-m-d');
    }

    /**
     * Reset pagination when filters change.
     */
    public function updatedFilterEntityType(): void
    {
        $this->currentPage = 1;
    }

    /**
     * Reset pagination when date changes.
     */
    public function updatedFilterStartDate(): void
    {
        $this->currentPage = 1;
    }

    /**
     * Reset pagination when date changes.
     */
    public function updatedFilterEndDate(): void
    {
        $this->currentPage = 1;
    }

    /**
     * Reset pagination when user filter changes.
     */
    public function updatedFilterUserId(): void
    {
        $this->currentPage = 1;
    }

    /**
     * Get available entity types for filter dropdown.
     *
     * @return array<string, string>
     */
    public function getEntityTypes(): array
    {
        return [
            '' => 'All Entity Types',
            'App\\Models\\Finance\\Invoice' => 'Invoice',
            'App\\Models\\Finance\\Payment' => 'Payment',
            'App\\Models\\Finance\\CreditNote' => 'Credit Note',
            'App\\Models\\Finance\\Refund' => 'Refund',
            'App\\Models\\Finance\\Subscription' => 'Subscription',
            'App\\Models\\Finance\\StorageBillingPeriod' => 'Storage Billing Period',
            'App\\Models\\Finance\\InvoicePayment' => 'Invoice Payment',
            'App\\Models\\Allocation\\Voucher' => 'Voucher',
            'App\\Models\\Allocation\\VoucherTransfer' => 'Voucher Transfer',
            'App\\Models\\Allocation\\CaseEntitlement' => 'Case Entitlement',
            'App\\Models\\Allocation\\Allocation' => 'Allocation',
            'App\\Models\\Inventory\\SerializedBottle' => 'Serialized Bottle',
            'App\\Models\\Inventory\\InventoryCase' => 'Inventory Case',
            'App\\Models\\Inventory\\InboundBatch' => 'Inbound Batch',
        ];
    }

    /**
     * Get friendly entity type label.
     */
    public function getEntityTypeLabel(string $type): string
    {
        $types = $this->getEntityTypes();

        return $types[$type] ?? class_basename($type);
    }

    /**
     * Get available event types for reference.
     *
     * @return array<string, string>
     */
    public function getEventTypes(): array
    {
        return [
            AuditLog::EVENT_CREATED => 'Created',
            AuditLog::EVENT_UPDATED => 'Updated',
            AuditLog::EVENT_DELETED => 'Deleted',
            AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
            AuditLog::EVENT_LIFECYCLE_CHANGE => 'Lifecycle Changed',
            AuditLog::EVENT_FLAG_CHANGE => 'Flag Changed',
            AuditLog::EVENT_PAYMENT_FAILED => 'Payment Failed',
            AuditLog::EVENT_PAYMENT_CONFIRMED => 'Payment Confirmed',
            AuditLog::EVENT_PAYMENT_RECONCILED => 'Payment Reconciled',
        ];
    }

    /**
     * Get filtered users for autocomplete.
     *
     * @return Collection<int, User>
     */
    public function getFilteredUsers(): Collection
    {
        if (strlen($this->userSearch) < 2) {
            return collect();
        }

        return User::query()
            ->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->userSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->userSearch.'%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * Select a user for filtering.
     */
    public function selectUser(string $userId): void
    {
        $this->filterUserId = $userId;
        $this->userSearch = '';
        $this->currentPage = 1;
    }

    /**
     * Clear user filter.
     */
    public function clearUserFilter(): void
    {
        $this->filterUserId = null;
        $this->userSearch = '';
        $this->currentPage = 1;
    }

    /**
     * Get the selected user for display.
     */
    public function getSelectedUser(): ?User
    {
        if ($this->filterUserId === null) {
            return null;
        }

        return User::find($this->filterUserId);
    }

    /**
     * Build the base query with filters applied.
     *
     * @return \Illuminate\Database\Eloquent\Builder<AuditLog>
     */
    protected function buildQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = AuditLog::query()->with('user');

        // Filter by entity type
        if ($this->filterEntityType !== null && $this->filterEntityType !== '') {
            $query->where('auditable_type', $this->filterEntityType);
        }

        // Filter by date range
        if ($this->filterStartDate !== '') {
            $query->where('created_at', '>=', Carbon::parse($this->filterStartDate)->startOfDay());
        }

        if ($this->filterEndDate !== '') {
            $query->where('created_at', '<=', Carbon::parse($this->filterEndDate)->endOfDay());
        }

        // Filter by user
        if ($this->filterUserId !== null) {
            $query->where('user_id', $this->filterUserId);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get audit logs for preview with pagination.
     *
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogs(): Collection
    {
        return $this->buildQuery()
            ->skip(($this->currentPage - 1) * $this->perPage)
            ->take($this->perPage)
            ->get();
    }

    /**
     * Get total count of filtered records.
     */
    public function getTotalCount(): int
    {
        return $this->buildQuery()->count();
    }

    /**
     * Get total number of pages.
     */
    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->getTotalCount() / $this->perPage));
    }

    /**
     * Go to previous page.
     */
    public function previousPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    /**
     * Go to next page.
     */
    public function nextPage(): void
    {
        if ($this->currentPage < $this->getTotalPages()) {
            $this->currentPage++;
        }
    }

    /**
     * Go to a specific page.
     */
    public function goToPage(int $page): void
    {
        $this->currentPage = max(1, min($page, $this->getTotalPages()));
    }

    /**
     * Format changes for display (combines old and new values).
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function formatChanges(?array $oldValues, ?array $newValues): string
    {
        $changes = [];

        if ($newValues !== null) {
            foreach ($newValues as $key => $newValue) {
                $oldValue = $oldValues[$key] ?? null;
                if ($oldValue !== null && $oldValue !== $newValue) {
                    $changes[] = $key.': '.$this->formatValue($oldValue).' → '.$this->formatValue($newValue);
                } elseif ($oldValue === null) {
                    $changes[] = $key.': '.$this->formatValue($newValue);
                }
            }
        }

        if (empty($changes) && $oldValues !== null) {
            // For deletions, show what was deleted
            foreach ($oldValues as $key => $value) {
                $changes[] = $key.': '.$this->formatValue($value);
            }
        }

        return implode(', ', $changes);
    }

    /**
     * Format a single value for display.
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }

        return (string) $value;
    }

    /**
     * Export audit logs to CSV.
     */
    public function exportToCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = $this->buildQuery();
        $filterStartDate = $this->filterStartDate;
        $filterEndDate = $this->filterEndDate;
        $filterEntityType = $this->filterEntityType;

        return response()->streamDownload(function () use ($query, $filterStartDate, $filterEndDate, $filterEntityType): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Report header
            fputcsv($handle, ['Audit Log Export']);
            fputcsv($handle, ['Generated', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, ['Date Range', $filterStartDate.' to '.$filterEndDate]);
            if ($filterEntityType !== null && $filterEntityType !== '') {
                fputcsv($handle, ['Entity Type', $this->getEntityTypeLabel($filterEntityType)]);
            }
            fputcsv($handle, []);

            // Column headers
            fputcsv($handle, [
                'Entity ID',
                'Entity Type',
                'Event Type',
                'User',
                'Timestamp',
                'Old Values',
                'New Values',
                'Changes Summary',
            ]);

            // Stream data in chunks
            $query->chunk(500, function ($logs) use ($handle): void {
                /** @var AuditLog $log */
                foreach ($logs as $log) {
                    /** @var array<string, mixed>|null $oldValues */
                    $oldValues = $log->old_values;
                    /** @var array<string, mixed>|null $newValues */
                    $newValues = $log->new_values;

                    fputcsv($handle, [
                        $log->auditable_id,
                        $this->getEntityTypeLabel($log->auditable_type),
                        $log->getEventLabel(),
                        $log->user !== null ? $log->user->name : 'System',
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $oldValues !== null ? json_encode($oldValues) : '',
                        $newValues !== null ? json_encode($newValues) : '',
                        $this->formatChanges($oldValues, $newValues),
                    ]);
                }
            });

            fclose($handle);
        }, 'audit-export-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export audit logs to JSON.
     */
    public function exportToJson(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = $this->buildQuery();
        $filterStartDate = $this->filterStartDate;
        $filterEndDate = $this->filterEndDate;
        $filterEntityType = $this->filterEntityType;

        return response()->streamDownload(function () use ($query, $filterStartDate, $filterEndDate, $filterEntityType): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Write JSON header
            fwrite($handle, "{\n");
            fwrite($handle, '  "export_info": {'."\n");
            fwrite($handle, '    "generated_at": "'.now()->toIso8601String().'",'."\n");
            fwrite($handle, '    "date_range": {'."\n");
            fwrite($handle, '      "start": "'.$filterStartDate.'",'."\n");
            fwrite($handle, '      "end": "'.$filterEndDate.'"'."\n");
            fwrite($handle, '    }'.($filterEntityType !== null && $filterEntityType !== '' ? ',' : '')."\n");
            if ($filterEntityType !== null && $filterEntityType !== '') {
                fwrite($handle, '    "entity_type": "'.$this->getEntityTypeLabel($filterEntityType).'"'."\n");
            }
            fwrite($handle, '  },'."\n");
            fwrite($handle, '  "audit_logs": ['."\n");

            $first = true;
            $query->chunk(500, function ($logs) use ($handle, &$first): void {
                foreach ($logs as $log) {
                    if (! $first) {
                        fwrite($handle, ",\n");
                    }
                    $first = false;

                    $entry = [
                        'entity_id' => $log->auditable_id,
                        'entity_type' => $this->getEntityTypeLabel($log->auditable_type),
                        'entity_type_class' => $log->auditable_type,
                        'event_type' => $log->event,
                        'event_label' => $log->getEventLabel(),
                        'user' => $log->user !== null ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                            'email' => $log->user->email,
                        ] : null,
                        'timestamp' => $log->created_at?->toIso8601String(),
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                    ];

                    $json = json_encode($entry, JSON_PRETTY_PRINT);
                    if ($json !== false) {
                        // Indent each line for proper nesting
                        $indented = implode("\n", array_map(fn ($line) => '    '.$line, explode("\n", $json)));
                        fwrite($handle, $indented);
                    }
                }
            });

            fwrite($handle, "\n  ]\n");
            fwrite($handle, "}\n");

            fclose($handle);
        }, 'audit-export-'.now()->format('Y-m-d-His').'.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Export based on selected format.
     */
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($this->exportFormat === 'json') {
            return $this->exportToJson();
        }

        return $this->exportToCsv();
    }

    /**
     * Get summary statistics for the current filter.
     *
     * @return array{
     *     total: int,
     *     by_event: array<string, int>,
     *     by_entity: array<string, int>,
     *     date_range_label: string
     * }
     */
    public function getSummary(): array
    {
        $query = $this->buildQuery();

        $total = $query->count();

        // Count by event type
        $byEvent = AuditLog::query()
            ->selectRaw('event, COUNT(*) as count')
            ->when($this->filterEntityType !== null && $this->filterEntityType !== '', function ($q): void {
                $q->where('auditable_type', $this->filterEntityType);
            })
            ->when($this->filterStartDate !== '', function ($q): void {
                $q->where('created_at', '>=', Carbon::parse($this->filterStartDate)->startOfDay());
            })
            ->when($this->filterEndDate !== '', function ($q): void {
                $q->where('created_at', '<=', Carbon::parse($this->filterEndDate)->endOfDay());
            })
            ->when($this->filterUserId !== null, function ($q): void {
                $q->where('user_id', $this->filterUserId);
            })
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();

        // Count by entity type (only if not filtered)
        $byEntity = [];
        if ($this->filterEntityType === null || $this->filterEntityType === '') {
            $byEntity = AuditLog::query()
                ->selectRaw('auditable_type, COUNT(*) as count')
                ->when($this->filterStartDate !== '', function ($q): void {
                    $q->where('created_at', '>=', Carbon::parse($this->filterStartDate)->startOfDay());
                })
                ->when($this->filterEndDate !== '', function ($q): void {
                    $q->where('created_at', '<=', Carbon::parse($this->filterEndDate)->endOfDay());
                })
                ->when($this->filterUserId !== null, function ($q): void {
                    $q->where('user_id', $this->filterUserId);
                })
                ->groupBy('auditable_type')
                ->pluck('count', 'auditable_type')
                ->toArray();
        }

        // Date range label
        $dateRangeLabel = $this->filterStartDate.' to '.$this->filterEndDate;

        return [
            'total' => $total,
            'by_event' => $byEvent,
            'by_entity' => $byEntity,
            'date_range_label' => $dateRangeLabel,
        ];
    }
}
