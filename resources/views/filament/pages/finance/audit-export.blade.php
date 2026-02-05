<x-filament-panels::page>
    @php
        $auditLogs = $this->getAuditLogs();
        $totalCount = $this->getTotalCount();
        $totalPages = $this->getTotalPages();
        $summary = $this->getSummary();
        $entityTypes = $this->getEntityTypes();
        $selectedUser = $this->getSelectedUser();
    @endphp

    {{-- Filters Section --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white mb-4">
            <x-heroicon-o-funnel class="inline-block h-5 w-5 mr-2 -mt-0.5" />
            Export Filters
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Entity Type Filter --}}
            <div>
                <label for="filterEntityType" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Entity Type
                </label>
                <select
                    id="filterEntityType"
                    wire:model.live="filterEntityType"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
                    @foreach($entityTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Start Date Filter --}}
            <div>
                <label for="filterStartDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Start Date
                </label>
                <input
                    type="date"
                    id="filterStartDate"
                    wire:model.live.debounce.500ms="filterStartDate"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>

            {{-- End Date Filter --}}
            <div>
                <label for="filterEndDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    End Date
                </label>
                <input
                    type="date"
                    id="filterEndDate"
                    wire:model.live.debounce.500ms="filterEndDate"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>

            {{-- User Filter --}}
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    User
                </label>
                @if($selectedUser)
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                        <span class="text-sm text-gray-900 dark:text-white flex-1">{{ $selectedUser->name }}</span>
                        <button
                            wire:click="clearUserFilter"
                            type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    </div>
                @else
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="userSearch"
                        placeholder="Search user..."
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                    >
                    @if(strlen($this->userSearch) >= 2)
                        @php $filteredUsers = $this->getFilteredUsers(); @endphp
                        @if($filteredUsers->count() > 0)
                            <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto">
                                @foreach($filteredUsers as $user)
                                    <button
                                        wire:click="selectUser('{{ $user->id }}')"
                                        type="button"
                                        class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-900 dark:text-white"
                                    >
                                        {{ $user->name }}
                                        <span class="text-gray-400 text-xs ml-2">{{ $user->email }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        </div>

        {{-- Export Options --}}
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="flex-1 max-w-xs">
                    <label for="exportFormat" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Export Format
                    </label>
                    <select
                        id="exportFormat"
                        wire:model.live="exportFormat"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                    >
                        <option value="csv">CSV (Spreadsheet)</option>
                        <option value="json">JSON (Data Exchange)</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button
                        wire:click="export"
                        type="button"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                    >
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4 mr-2" />
                        Download {{ strtoupper($this->exportFormat) }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        {{-- Total Records --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Records</p>
                <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($summary['total']) }}</p>
                <p class="text-xs text-gray-400">{{ $summary['date_range_label'] }}</p>
            </div>
        </div>

        {{-- Created Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</p>
                <p class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">{{ number_format($summary['by_event']['created'] ?? 0) }}</p>
            </div>
        </div>

        {{-- Updated Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Updated</p>
                <p class="mt-1 text-2xl font-bold text-info-600 dark:text-info-400">{{ number_format($summary['by_event']['updated'] ?? 0) }}</p>
            </div>
        </div>

        {{-- Deleted Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deleted</p>
                <p class="mt-1 text-2xl font-bold text-danger-600 dark:text-danger-400">{{ number_format($summary['by_event']['deleted'] ?? 0) }}</p>
            </div>
        </div>
    </div>

    {{-- By Entity Type Breakdown (if not filtered) --}}
    @if(count($summary['by_entity']) > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
            <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white mb-4">
                <x-heroicon-o-chart-pie class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                Records by Entity Type
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($summary['by_entity'] as $entityType => $count)
                    <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">{{ $this->getEntityTypeLabel($entityType) }}</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($count) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Preview Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-eye class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Preview ({{ number_format($totalCount) }} records)
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ ($this->currentPage - 1) * $this->perPage + 1 }} - {{ min($this->currentPage * $this->perPage, $totalCount) }} of {{ number_format($totalCount) }}
                </span>
            </div>
        </div>

        <div class="fi-section-content">
            @if($auditLogs->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Entity ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Entity Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Event
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    User
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Timestamp
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Changes
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($auditLogs as $log)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600 dark:text-gray-400">
                                        {{ \Illuminate\Support\Str::limit($log->auditable_id, 8) }}...
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                                            {{ $this->getEntityTypeLabel($log->auditable_type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @switch($log->getEventColor())
                                                @case('success') bg-success-100 dark:bg-success-900/30 text-success-800 dark:text-success-400 @break
                                                @case('info') bg-info-100 dark:bg-info-900/30 text-info-800 dark:text-info-400 @break
                                                @case('warning') bg-warning-100 dark:bg-warning-900/30 text-warning-800 dark:text-warning-400 @break
                                                @case('danger') bg-danger-100 dark:bg-danger-900/30 text-danger-800 dark:text-danger-400 @break
                                                @default bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200
                                            @endswitch
                                        ">
                                            <x-dynamic-component :component="$log->getEventIcon()" class="h-3 w-3" />
                                            {{ $log->getEventLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $log->user?->name ?? 'System' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $log->created_at?->format('Y-m-d H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-md truncate" title="{{ $this->formatChanges($log->old_values, $log->new_values) }}">
                                        {{ \Illuminate\Support\Str::limit($this->formatChanges($log->old_values, $log->new_values), 80) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($totalPages > 1)
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <button
                                wire:click="previousPage"
                                type="button"
                                @if($this->currentPage <= 1) disabled @endif
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <x-heroicon-o-chevron-left class="h-4 w-4 mr-1" />
                                Previous
                            </button>

                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    Page {{ $this->currentPage }} of {{ $totalPages }}
                                </span>
                            </div>

                            <button
                                wire:click="nextPage"
                                type="button"
                                @if($this->currentPage >= $totalPages) disabled @endif
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next
                                <x-heroicon-o-chevron-right class="h-4 w-4 ml-1" />
                            </button>
                        </div>
                    </div>
                @endif
            @else
                <div class="text-center py-12">
                    <x-heroicon-o-document-magnifying-glass class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No audit logs found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Try adjusting your filters or date range.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-start">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 text-gray-400 flex-shrink-0 mt-0.5" />
            <span>
                <strong>Audit Export</strong> allows you to download audit logs for compliance reporting.
                Logs include entity changes, the user who made the change, and timestamps.
                <br class="hidden sm:block">
                <span class="text-gray-400">
                    CSV format is recommended for spreadsheet analysis. JSON format is recommended for data integration.
                </span>
            </span>
        </p>
    </div>
</x-filament-panels::page>
