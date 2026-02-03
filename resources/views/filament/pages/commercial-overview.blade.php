<x-filament-panels::page>
    @php
        $empStats = $this->getEmpAlertStatistics();
        $threshold = $empStats['threshold'];
        $isPriceBookAvailable = $this->isPriceBookAvailable();
        $isOfferAvailable = $this->isOfferAvailable();
    @endphp

    {{-- Page Header --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <x-heroicon-o-building-storefront class="h-6 w-6 text-primary-500" />
            </div>
            <div class="ml-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Commercial Module</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Central control panel for Channels, Price Books, Pricing Policies, Offers, Discounts, and Bundles.
                    Monitor pricing intelligence and commercial activities.
                </p>
            </div>
        </div>
    </div>

    {{-- EMP Alerts Widget --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                    <x-heroicon-o-bell-alert class="h-5 w-5 mr-2 text-warning-500" />
                    EMP Alerts
                </h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    Threshold: {{ $threshold }}%
                </span>
            </div>
        </div>
        <div class="fi-section-content p-6">
            @if(!$isPriceBookAvailable)
                {{-- Price Book not yet available notice --}}
                <div class="rounded-lg bg-info-50 dark:bg-info-400/10 border border-info-200 dark:border-info-400/20 p-4 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-information-circle class="h-5 w-5 text-info-600 dark:text-info-400" />
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-info-800 dark:text-info-200">Price Deviation Alerts Coming Soon</h4>
                            <p class="text-sm text-info-700 dark:text-info-300 mt-1">
                                Full price deviation alerts will be available once Price Books (US-009+) and Offers (US-033+) are implemented.
                                Currently showing EMP data quality alerts.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Alert Cards Grid --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                {{-- Total EMP Records --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">EMP Records</p>
                            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ number_format($empStats['total_emp_records']) }}</p>
                        </div>
                        <div class="rounded-full bg-primary-100 dark:bg-primary-400/10 p-2">
                            <x-heroicon-o-chart-bar class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        </div>
                    </div>
                </div>

                {{-- Price Deviations (placeholder until Price Books implemented) --}}
                @php
                    $deviationSeverity = $isPriceBookAvailable
                        ? $this->getAlertSeverity($empStats['deviations_count'], $empStats['total_emp_records'])
                        : 'success';
                    $deviationColorClass = match($deviationSeverity) {
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                    $deviationBgClass = match($deviationSeverity) {
                        'danger' => 'bg-danger-100 dark:bg-danger-400/10',
                        'warning' => 'bg-warning-100 dark:bg-warning-400/10',
                        default => 'bg-gray-100 dark:bg-gray-400/10',
                    };
                @endphp
                <a
                    href="{{ $this->getPricingIntelligenceUrl('deviation_high') }}"
                    class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Price Deviations (&gt;{{ $threshold }}%)
                            </p>
                            @if($isPriceBookAvailable)
                                <p class="text-2xl font-bold {{ $deviationColorClass }} mt-1">{{ number_format($empStats['deviations_count']) }}</p>
                            @else
                                <p class="text-2xl font-bold text-gray-400 dark:text-gray-500 mt-1">—</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Pending Price Books</p>
                            @endif
                        </div>
                        <div class="rounded-full {{ $deviationBgClass }} p-2">
                            <x-heroicon-o-arrow-trending-up class="h-5 w-5 {{ $deviationColorClass }}" />
                        </div>
                    </div>
                </a>

                {{-- Stale Data --}}
                @php
                    $staleSeverity = $this->getAlertSeverity($empStats['stale_count'], $empStats['total_emp_records']);
                    $staleColorClass = match($staleSeverity) {
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        default => 'text-success-600 dark:text-success-400',
                    };
                    $staleBgClass = match($staleSeverity) {
                        'danger' => 'bg-danger-100 dark:bg-danger-400/10',
                        'warning' => 'bg-warning-100 dark:bg-warning-400/10',
                        default => 'bg-success-100 dark:bg-success-400/10',
                    };
                @endphp
                <a
                    href="{{ $this->getPricingIntelligenceUrl('stale_data') }}"
                    class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Stale EMP Data</p>
                            <p class="text-2xl font-bold {{ $staleColorClass }} mt-1">{{ number_format($empStats['stale_count']) }}</p>
                        </div>
                        <div class="rounded-full {{ $staleBgClass }} p-2">
                            <x-heroicon-o-clock class="h-5 w-5 {{ $staleColorClass }}" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Data older than {{ config('commercial.emp.stale_threshold_days', 7) }} days</p>
                </a>

                {{-- Low Confidence --}}
                @php
                    $confidenceSeverity = $this->getAlertSeverity($empStats['low_confidence_count'], $empStats['total_emp_records']);
                    $confidenceColorClass = match($confidenceSeverity) {
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        default => 'text-success-600 dark:text-success-400',
                    };
                    $confidenceBgClass = match($confidenceSeverity) {
                        'danger' => 'bg-danger-100 dark:bg-danger-400/10',
                        'warning' => 'bg-warning-100 dark:bg-warning-400/10',
                        default => 'bg-success-100 dark:bg-success-400/10',
                    };
                @endphp
                <a
                    href="{{ $this->getPricingIntelligenceUrl('confidence_level') }}"
                    class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Low Confidence</p>
                            <p class="text-2xl font-bold {{ $confidenceColorClass }} mt-1">{{ number_format($empStats['low_confidence_count']) }}</p>
                        </div>
                        <div class="rounded-full {{ $confidenceBgClass }} p-2">
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 {{ $confidenceColorClass }}" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Records with low reliability</p>
                </a>
            </div>

            {{-- Markets with Issues --}}
            @if(count($empStats['markets_with_issues']) > 0)
                <div class="rounded-lg border border-warning-200 dark:border-warning-400/20 bg-warning-50 dark:bg-warning-400/10 p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-globe-alt class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                        </div>
                        <div class="ml-3 flex-1">
                            <h4 class="text-sm font-medium text-warning-800 dark:text-warning-200">Markets with Data Quality Issues</h4>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($empStats['markets_with_issues'] as $market => $count)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-200 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400">
                                        {{ $market }}: {{ $count }} issue{{ $count !== 1 ? 's' : '' }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-success-200 dark:border-success-400/20 bg-success-50 dark:bg-success-400/10 p-4">
                    <div class="flex items-center">
                        <x-heroicon-o-check-circle class="h-5 w-5 text-success-600 dark:text-success-400" />
                        <span class="ml-2 text-sm font-medium text-success-800 dark:text-success-200">
                            All markets have healthy EMP data quality
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Placeholder Widgets for Future Features --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
        {{-- Active Price Books Widget (Placeholder) --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                    <x-heroicon-o-book-open class="h-5 w-5 mr-2 text-primary-500" />
                    Active Price Books
                </h3>
            </div>
            <div class="fi-section-content p-6">
                <div class="text-center py-8">
                    <x-heroicon-o-book-open class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Price Books coming in US-009+</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Will show active price books by status</p>
                </div>
            </div>
        </div>

        {{-- Active Offers Widget (Placeholder) --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                    <x-heroicon-o-tag class="h-5 w-5 mr-2 text-success-500" />
                    Active Offers
                </h3>
            </div>
            <div class="fi-section-content p-6">
                <div class="text-center py-8">
                    <x-heroicon-o-tag class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Offers coming in US-033+</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Will show active offers by status</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Pricing Policies Widget (Placeholder) --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                <x-heroicon-o-cog-6-tooth class="h-5 w-5 mr-2 text-info-500" />
                Pricing Policies
            </h3>
        </div>
        <div class="fi-section-content p-6">
            <div class="text-center py-8">
                <x-heroicon-o-cog-6-tooth class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Pricing Policies coming in US-020+</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Will show policies with last execution status</p>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                <x-heroicon-o-bolt class="h-5 w-5 mr-2 text-warning-500" />
                Quick Actions
            </h3>
        </div>
        <div class="fi-section-content p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($this->getQuickActions() as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-2">
                            <x-dynamic-component :component="$action['icon']" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $action['label'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $action['description'] }}</p>
                        </div>
                    </a>
                @endforeach

                {{-- Placeholder actions for future features --}}
                <div class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 opacity-60 cursor-not-allowed">
                    <div class="flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-700 p-2">
                        <x-heroicon-o-plus class="h-5 w-5 text-gray-400" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Create Price Book</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Coming in US-012+</p>
                    </div>
                </div>

                <div class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 opacity-60 cursor-not-allowed">
                    <div class="flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-700 p-2">
                        <x-heroicon-o-plus class="h-5 w-5 text-gray-400" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Create Offer</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Coming in US-037+</p>
                    </div>
                </div>

                <div class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 opacity-60 cursor-not-allowed">
                    <div class="flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-700 p-2">
                        <x-heroicon-o-plus class="h-5 w-5 text-gray-400" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Create Policy</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Coming in US-024+</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
