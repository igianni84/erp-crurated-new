<x-filament-panels::page>
    @php
        $totalCount = $this->getTotalAuditCount();
        $countsByEntity = $this->getCountByEntityType();
    @endphp

    {{-- Summary Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Audit Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-3">
                    <x-heroicon-o-clipboard-document-list class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Events</p>
                    <p class="text-2xl font-semibold text-primary-600 dark:text-primary-400">{{ number_format($totalCount) }}</p>
                </div>
            </div>
        </div>

        {{-- Bottle Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-3">
                    <x-heroicon-o-beaker class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bottle Events</p>
                    <p class="text-2xl font-semibold text-success-600 dark:text-success-400">{{ number_format($countsByEntity['bottles']) }}</p>
                </div>
            </div>
        </div>

        {{-- Case Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                    <x-heroicon-o-archive-box class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Case Events</p>
                    <p class="text-2xl font-semibold text-info-600 dark:text-info-400">{{ number_format($countsByEntity['cases']) }}</p>
                </div>
            </div>
        </div>

        {{-- Batch Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-3">
                    <x-heroicon-o-inbox-arrow-down class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Batch Events</p>
                    <p class="text-2xl font-semibold text-warning-600 dark:text-warning-400">{{ number_format($countsByEntity['batches']) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Info Banner with Export --}}
    <div class="mb-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-heroicon-o-shield-check class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <strong>Compliance Audit Trail</strong> - This log provides an immutable record of all inventory-related events.
                        All entries are automatically generated and cannot be modified or deleted.
                        Use the export feature for compliance reporting.
                    </p>
                </div>
            </div>
            <div class="flex-shrink-0 ml-4">
                <x-filament::button
                    wire:click="exportToCsv"
                    icon="heroicon-o-arrow-down-tray"
                    color="gray"
                    size="sm"
                >
                    Export CSV
                </x-filament::button>
            </div>
        </div>
    </div>

    {{-- Audit Log Table --}}
    {{ $this->table }}
</x-filament-panels::page>
