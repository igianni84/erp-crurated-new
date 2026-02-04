<x-filament-panels::page>
    @php
        $stats = $this->getQueueStats();
    @endphp

    {{-- Summary Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Batches in Queue --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-3">
                    <x-heroicon-o-queue-list class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Batches in Queue</p>
                    <p class="text-2xl font-semibold text-primary-600 dark:text-primary-400">{{ $stats['total_batches'] }}</p>
                </div>
            </div>
        </div>

        {{-- Pending Serialization --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-3">
                    <x-heroicon-o-clock class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-semibold text-warning-600 dark:text-warning-400">{{ $stats['pending_count'] }}</p>
                </div>
            </div>
        </div>

        {{-- Partially Serialized --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                    <x-heroicon-o-arrow-path class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Partially Done</p>
                    <p class="text-2xl font-semibold text-info-600 dark:text-info-400">{{ $stats['partial_count'] }}</p>
                </div>
            </div>
        </div>

        {{-- Total Bottles Remaining --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-3">
                    <x-heroicon-o-beaker class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bottles to Serialize</p>
                    <p class="text-2xl font-semibold text-success-600 dark:text-success-400">{{ number_format($stats['total_bottles_remaining']) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Info Banner --}}
    @if($stats['total_batches'] > 0)
        <div class="mb-6 p-4 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="h-5 w-5 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm text-info-700 dark:text-info-300">
                        <strong>Serialization Queue</strong> - This list shows inbound batches that are eligible for serialization.
                        Only batches at locations with <strong>serialization authorized</strong> are displayed.
                        Click the <strong>Serialize</strong> button to start serializing bottles from a batch.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Table --}}
    {{ $this->table }}
</x-filament-panels::page>
