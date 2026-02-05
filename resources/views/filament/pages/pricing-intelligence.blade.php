<x-filament-panels::page>
    @php
        $statistics = $this->getStatistics();
    @endphp

    {{-- Page Description --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <x-heroicon-o-information-circle class="h-5 w-5 text-info-500" />
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>Pricing Intelligence</strong> provides market reference data for all Sellable SKUs.
                    EMP (Estimated Market Prices) are imported from external sources and serve as benchmarks for pricing decisions.
                    This view is read-only.
                </p>
            </div>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Records --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total EMP Records</p>
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ number_format($statistics['total_records']) }}</p>
                </div>
                <div class="rounded-full bg-primary-50 dark:bg-primary-400/10 p-2">
                    <x-heroicon-o-chart-bar class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
        </div>

        {{-- Markets Covered --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Markets Covered</p>
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400 mt-1">{{ $statistics['markets_covered'] }}</p>
                </div>
                <div class="rounded-full bg-success-50 dark:bg-success-400/10 p-2">
                    <x-heroicon-o-globe-alt class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
            </div>
        </div>

        {{-- Stale Data --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Stale Data</p>
                    <p class="text-2xl font-bold {{ $statistics['stale_count'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-600 dark:text-gray-400' }} mt-1">
                        {{ number_format($statistics['stale_count']) }}
                    </p>
                </div>
                <div class="rounded-full {{ $statistics['stale_count'] > 0 ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-gray-50 dark:bg-gray-400/10' }} p-2">
                    <x-heroicon-o-clock class="h-6 w-6 {{ $statistics['stale_count'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-600 dark:text-gray-400' }}" />
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Records older than 7 days</p>
        </div>

        {{-- Low Confidence --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Low Confidence</p>
                    <p class="text-2xl font-bold {{ $statistics['low_confidence_count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-600 dark:text-gray-400' }} mt-1">
                        {{ number_format($statistics['low_confidence_count']) }}
                    </p>
                </div>
                <div class="rounded-full {{ $statistics['low_confidence_count'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-gray-50 dark:bg-gray-400/10' }} p-2">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 {{ $statistics['low_confidence_count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-600 dark:text-gray-400' }}" />
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Records with low reliability</p>
        </div>
    </div>

    {{-- Placeholder Notice for Future Features --}}
    <div class="fi-section rounded-xl bg-info-50 dark:bg-info-400/10 border border-info-200 dark:border-info-400/20 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <x-heroicon-o-light-bulb class="h-5 w-5 text-info-600 dark:text-info-400" />
            </div>
            <div class="ml-3">
                <h4 class="text-sm font-medium text-info-800 dark:text-info-200">Coming Soon</h4>
                <p class="text-sm text-info-700 dark:text-info-300 mt-1">
                    Price Book Price, Offer Price, and Delta vs EMP columns will be populated once
                    Price Books (US-009+) and Offers (US-033+) are implemented. The deviation highlighting
                    for SKUs with >15% difference from EMP will also become active.
                </p>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
