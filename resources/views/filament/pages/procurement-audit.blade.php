<x-filament-panels::page>
    @php
        $statistics = $this->getStatistics();
    @endphp

    {{-- Page Description --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <x-heroicon-o-shield-check class="h-5 w-5 text-primary-500" />
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>Procurement Audit Trail</strong> provides a complete, immutable record of all changes to procurement entities.
                    Track Intents, Purchase Orders, Bottling Instructions, and Inbounds. Use filters to investigate specific events, entities, or time periods.
                    Export data for compliance reporting.
                </p>
            </div>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Events</p>
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ number_format($statistics['total_events']) }}</p>
                </div>
                <div class="rounded-full bg-primary-50 dark:bg-primary-400/10 p-2">
                    <x-heroicon-o-document-text class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">All procurement audit records</p>
        </div>

        {{-- Today's Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Today's Events</p>
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400 mt-1">{{ number_format($statistics['today_events']) }}</p>
                </div>
                <div class="rounded-full bg-success-50 dark:bg-success-400/10 p-2">
                    <x-heroicon-o-calendar class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Changes logged today</p>
        </div>

        {{-- Entities Tracked --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Entities Tracked</p>
                    <p class="text-2xl font-bold text-info-600 dark:text-info-400 mt-1">{{ $statistics['entities_tracked'] }}</p>
                </div>
                <div class="rounded-full bg-info-50 dark:bg-info-400/10 p-2">
                    <x-heroicon-o-cube class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Procurement entity types</p>
        </div>

        {{-- Active Users --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Users Active</p>
                    <p class="text-2xl font-bold text-warning-600 dark:text-warning-400 mt-1">{{ $statistics['users_active'] }}</p>
                </div>
                <div class="rounded-full bg-warning-50 dark:bg-warning-400/10 p-2">
                    <x-heroicon-o-users class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Users with recorded actions</p>
        </div>
    </div>

    {{-- Compliance Notice --}}
    <div class="fi-section rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <x-heroicon-o-shield-check class="h-5 w-5 text-gray-500 dark:text-gray-400" />
            </div>
            <div class="ml-3">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Compliance Information</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Audit logs are immutable and cannot be modified or deleted. All events are timestamped and attributed to users when applicable.
                    Use the <strong>Export CSV</strong> button to download filtered audit data for compliance reporting.
                </p>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
