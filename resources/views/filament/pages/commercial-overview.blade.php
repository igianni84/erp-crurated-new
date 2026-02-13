<x-filament-panels::page>
    @php
        $empStats = $this->getEmpAlertStatistics();
        $priceBookStats = $this->getPriceBookStatistics();
        $offerStats = $this->getOfferStatistics();
        $policyStats = $this->getPricingPolicyStatistics();
        $empCoverage = $this->getEmpCoverageStatistics();
        $alerts = $this->getAlertsSummary();
        $resourceUrls = $this->getResourceUrls();
        $threshold = $empStats['threshold'];
    @endphp

    {{-- Page Header --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-4">
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

    {{-- Alerts Summary Bar --}}
    @if($alerts['total'] > 0)
        <div class="fi-section rounded-xl bg-warning-50 dark:bg-warning-400/10 border border-warning-200 dark:border-warning-400/20 p-4 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <x-heroicon-o-bell-alert class="h-5 w-5 text-warning-600 dark:text-warning-400 mr-2" />
                    <span class="text-sm font-medium text-warning-800 dark:text-warning-200">
                        {{ $alerts['total'] }} alert{{ $alerts['total'] !== 1 ? 's' : '' }} require attention
                    </span>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    @if($alerts['expiring_offers'] > 0)
                        <span class="inline-flex items-center px-2 py-1 rounded bg-warning-200 dark:bg-warning-400/20 text-warning-800 dark:text-warning-200">
                            <x-heroicon-o-tag class="h-3 w-3 mr-1" />
                            {{ $alerts['expiring_offers'] }} offers expiring
                        </span>
                    @endif
                    @if($alerts['expiring_price_books'] > 0)
                        <span class="inline-flex items-center px-2 py-1 rounded bg-warning-200 dark:bg-warning-400/20 text-warning-800 dark:text-warning-200">
                            <x-heroicon-o-book-open class="h-3 w-3 mr-1" />
                            {{ $alerts['expiring_price_books'] }} price books expiring
                        </span>
                    @endif
                    @if($alerts['policy_failures'] > 0)
                        <span class="inline-flex items-center px-2 py-1 rounded bg-danger-200 dark:bg-danger-400/20 text-danger-800 dark:text-danger-200">
                            <x-heroicon-o-exclamation-triangle class="h-3 w-3 mr-1" />
                            {{ $alerts['policy_failures'] }} policy failures
                        </span>
                    @endif
                    @if($alerts['stale_emp'] > 0)
                        <span class="inline-flex items-center px-2 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                            <x-heroicon-o-clock class="h-3 w-3 mr-1" />
                            {{ $alerts['stale_emp'] }} stale EMP
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Main Stats Grid --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-4 mb-4">
        {{-- Active Price Books Widget --}}
        <a href="{{ $resourceUrls['price_books'] }}" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:shadow-md transition-shadow">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="rounded-full bg-primary-100 dark:bg-primary-400/10 p-2">
                        <x-heroicon-o-book-open class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    @if($priceBookStats['expiring_soon'] > 0)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                            {{ $priceBookStats['expiring_soon'] }} expiring
                        </span>
                    @endif
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $priceBookStats['total'] }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Price Books</p>
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach($priceBookStats['by_status'] as $status => $count)
                        @if($count > 0)
                            @php
                                $statusEnum = \App\Enums\Commercial\PriceBookStatus::from($status);
                            @endphp
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium" style="background-color: {{ match($statusEnum) {
                                \App\Enums\Commercial\PriceBookStatus::Draft => 'rgb(229 231 235)',
                                \App\Enums\Commercial\PriceBookStatus::Active => 'rgb(187 247 208)',
                                \App\Enums\Commercial\PriceBookStatus::Expired => 'rgb(254 215 170)',
                                \App\Enums\Commercial\PriceBookStatus::Archived => 'rgb(203 213 225)',
                            } }}; color: {{ match($statusEnum) {
                                \App\Enums\Commercial\PriceBookStatus::Draft => 'rgb(55 65 81)',
                                \App\Enums\Commercial\PriceBookStatus::Active => 'rgb(21 128 61)',
                                \App\Enums\Commercial\PriceBookStatus::Expired => 'rgb(194 65 12)',
                                \App\Enums\Commercial\PriceBookStatus::Archived => 'rgb(71 85 105)',
                            } }};">
                                {{ $count }} {{ $statusEnum->label() }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        </a>

        {{-- Active Offers Widget --}}
        <a href="{{ $resourceUrls['offers'] }}" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:shadow-md transition-shadow">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="rounded-full bg-success-100 dark:bg-success-400/10 p-2">
                        <x-heroicon-o-tag class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                    @if($offerStats['expiring_soon'] > 0)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                            {{ $offerStats['expiring_soon'] }} expiring
                        </span>
                    @endif
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $offerStats['total'] }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Offers</p>
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach($offerStats['by_status'] as $status => $count)
                        @if($count > 0)
                            @php
                                $statusEnum = \App\Enums\Commercial\OfferStatus::from($status);
                            @endphp
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium" style="background-color: {{ match($statusEnum) {
                                \App\Enums\Commercial\OfferStatus::Draft => 'rgb(229 231 235)',
                                \App\Enums\Commercial\OfferStatus::Active => 'rgb(187 247 208)',
                                \App\Enums\Commercial\OfferStatus::Paused => 'rgb(254 249 195)',
                                \App\Enums\Commercial\OfferStatus::Expired => 'rgb(254 215 170)',
                                \App\Enums\Commercial\OfferStatus::Cancelled => 'rgb(254 202 202)',
                            } }}; color: {{ match($statusEnum) {
                                \App\Enums\Commercial\OfferStatus::Draft => 'rgb(55 65 81)',
                                \App\Enums\Commercial\OfferStatus::Active => 'rgb(21 128 61)',
                                \App\Enums\Commercial\OfferStatus::Paused => 'rgb(161 98 7)',
                                \App\Enums\Commercial\OfferStatus::Expired => 'rgb(194 65 12)',
                                \App\Enums\Commercial\OfferStatus::Cancelled => 'rgb(185 28 28)',
                            } }};">
                                {{ $count }} {{ $statusEnum->label() }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        </a>

        {{-- Pricing Policies Widget --}}
        <a href="{{ $resourceUrls['pricing_policies'] }}" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:shadow-md transition-shadow">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="rounded-full bg-info-100 dark:bg-info-400/10 p-2">
                        <x-heroicon-o-cog-6-tooth class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                    @if($policyStats['failed_executions'] > 0)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-danger-100 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
                            {{ $policyStats['failed_executions'] }} failed
                        </span>
                    @endif
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $policyStats['total'] }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pricing Policies</p>
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach($policyStats['by_status'] as $status => $count)
                        @if($count > 0)
                            @php
                                $statusEnum = \App\Enums\Commercial\PricingPolicyStatus::from($status);
                            @endphp
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium" style="background-color: {{ match($statusEnum) {
                                \App\Enums\Commercial\PricingPolicyStatus::Draft => 'rgb(229 231 235)',
                                \App\Enums\Commercial\PricingPolicyStatus::Active => 'rgb(187 247 208)',
                                \App\Enums\Commercial\PricingPolicyStatus::Paused => 'rgb(254 249 195)',
                                \App\Enums\Commercial\PricingPolicyStatus::Archived => 'rgb(203 213 225)',
                            } }}; color: {{ match($statusEnum) {
                                \App\Enums\Commercial\PricingPolicyStatus::Draft => 'rgb(55 65 81)',
                                \App\Enums\Commercial\PricingPolicyStatus::Active => 'rgb(21 128 61)',
                                \App\Enums\Commercial\PricingPolicyStatus::Paused => 'rgb(161 98 7)',
                                \App\Enums\Commercial\PricingPolicyStatus::Archived => 'rgb(71 85 105)',
                            } }};">
                                {{ $count }} {{ $statusEnum->label() }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        </a>

        {{-- EMP Coverage Widget --}}
        <a href="{{ $resourceUrls['pricing_intelligence'] }}" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:shadow-md transition-shadow">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="rounded-full bg-purple-100 dark:bg-purple-400/10 p-2">
                        <x-heroicon-o-chart-bar class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $empCoverage['coverage_percentage'] >= 80 ? 'bg-success-100 text-success-700 dark:bg-success-400/10 dark:text-success-400' : ($empCoverage['coverage_percentage'] >= 50 ? 'bg-warning-100 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' : 'bg-danger-100 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400') }}">
                        {{ $empCoverage['coverage_percentage'] }}%
                    </span>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $empCoverage['skus_with_emp'] }}/{{ $empCoverage['total_skus'] }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">EMP Coverage</p>
                <div class="mt-3">
                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                        <div class="h-2 rounded-full {{ $empCoverage['coverage_percentage'] >= 80 ? 'bg-success-500' : ($empCoverage['coverage_percentage'] >= 50 ? 'bg-warning-500' : 'bg-danger-500') }}" style="width: {{ min($empCoverage['coverage_percentage'], 100) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $empCoverage['markets_covered'] }} markets covered</p>
                </div>
            </div>
        </a>
    </div>

    {{-- Recent Policy Executions & EMP Alerts Row --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 mb-4">
        {{-- Recent Policy Executions --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                        <x-heroicon-o-clock class="h-5 w-5 mr-2 text-info-500" />
                        Recent Policy Executions
                    </h3>
                    <a href="{{ $resourceUrls['pricing_policies'] }}" class="text-xs text-primary-600 hover:text-primary-500 dark:text-primary-400">
                        View all →
                    </a>
                </div>
            </div>
            <div class="fi-section-content p-4">
                @if(count($policyStats['recent_executions']) > 0)
                    <div class="space-y-3">
                        @foreach($policyStats['recent_executions'] as $execution)
                            @php
                                $statusEnum = \App\Enums\Commercial\ExecutionStatus::tryFrom($execution['status']);
                            @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                <div class="flex items-center">
                                    @if($statusEnum)
                                        <span class="flex-shrink-0 w-2 h-2 rounded-full mr-3 {{ match($statusEnum) {
                                            \App\Enums\Commercial\ExecutionStatus::Success => 'bg-success-500',
                                            \App\Enums\Commercial\ExecutionStatus::Partial => 'bg-warning-500',
                                            \App\Enums\Commercial\ExecutionStatus::Failed => 'bg-danger-500',
                                        } }}"></span>
                                    @else
                                        <span class="flex-shrink-0 w-2 h-2 rounded-full mr-3 bg-gray-400"></span>
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $execution['policy_name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $execution['executed_at'] }}</p>
                                    </div>
                                </div>
                                @if($statusEnum)
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium" style="background-color: {{ match($statusEnum) {
                                        \App\Enums\Commercial\ExecutionStatus::Success => 'rgb(187 247 208)',
                                        \App\Enums\Commercial\ExecutionStatus::Partial => 'rgb(254 249 195)',
                                        \App\Enums\Commercial\ExecutionStatus::Failed => 'rgb(254 202 202)',
                                    } }}; color: {{ match($statusEnum) {
                                        \App\Enums\Commercial\ExecutionStatus::Success => 'rgb(21 128 61)',
                                        \App\Enums\Commercial\ExecutionStatus::Partial => 'rgb(161 98 7)',
                                        \App\Enums\Commercial\ExecutionStatus::Failed => 'rgb(185 28 28)',
                                    } }};">
                                        {{ $statusEnum->label() }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-heroicon-o-clock class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No recent policy executions</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Execute a pricing policy to see results here</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- EMP Alerts --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
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
            <div class="fi-section-content p-4">
                {{-- Alert Cards Grid --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    {{-- Stale Data --}}
                    @php
                        $staleSeverity = $this->getAlertSeverity($empStats['stale_count'], $empStats['total_emp_records']);
                        $staleColorClass = match($staleSeverity) {
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            'warning' => 'text-warning-600 dark:text-warning-400',
                            default => 'text-success-600 dark:text-success-400',
                        };
                    @endphp
                    <a href="{{ $this->getPricingIntelligenceUrl('stale_data') }}" class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stale Data</p>
                                <p class="text-xl font-bold {{ $staleColorClass }} mt-1">{{ number_format($empStats['stale_count']) }}</p>
                            </div>
                            <x-heroicon-o-clock class="h-4 w-4 {{ $staleColorClass }}" />
                        </div>
                    </a>

                    {{-- Low Confidence --}}
                    @php
                        $confidenceSeverity = $this->getAlertSeverity($empStats['low_confidence_count'], $empStats['total_emp_records']);
                        $confidenceColorClass = match($confidenceSeverity) {
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            'warning' => 'text-warning-600 dark:text-warning-400',
                            default => 'text-success-600 dark:text-success-400',
                        };
                    @endphp
                    <a href="{{ $this->getPricingIntelligenceUrl('confidence_level') }}" class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Low Confidence</p>
                                <p class="text-xl font-bold {{ $confidenceColorClass }} mt-1">{{ number_format($empStats['low_confidence_count']) }}</p>
                            </div>
                            <x-heroicon-o-exclamation-triangle class="h-4 w-4 {{ $confidenceColorClass }}" />
                        </div>
                    </a>
                </div>

                {{-- Markets with Issues --}}
                @if(count($empStats['markets_with_issues']) > 0)
                    <div class="rounded-lg border border-warning-200 dark:border-warning-400/20 bg-warning-50 dark:bg-warning-400/10 p-3">
                        <div class="flex items-start">
                            <x-heroicon-o-globe-alt class="h-4 w-4 text-warning-600 dark:text-warning-400 mt-0.5" />
                            <div class="ml-2">
                                <p class="text-xs font-medium text-warning-800 dark:text-warning-200">Markets with issues:</p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach($empStats['markets_with_issues'] as $market => $count)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-warning-200 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400">
                                            {{ $market }}: {{ $count }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-success-200 dark:border-success-400/20 bg-success-50 dark:bg-success-400/10 p-3">
                        <div class="flex items-center">
                            <x-heroicon-o-check-circle class="h-4 w-4 text-success-600 dark:text-success-400" />
                            <span class="ml-2 text-xs font-medium text-success-800 dark:text-success-200">
                                All markets have healthy EMP data
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Upcoming Expirations Widget --}}
    @php
        $expirations = $this->getUpcomingExpirations();
    @endphp
    @if($expirations['total'] > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                        <x-heroicon-o-calendar class="h-5 w-5 mr-2 text-warning-500" />
                        Upcoming Expirations
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                            {{ $expirations['total'] }}
                        </span>
                    </h3>
                </div>
            </div>
            <div class="fi-section-content p-4">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {{-- Expiring Offers --}}
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <x-heroicon-o-tag class="h-4 w-4 mr-1.5 text-success-500" />
                            Offers (7 days)
                        </h4>
                        @if(count($expirations['offers']) > 0)
                            <div class="space-y-2">
                                @foreach($expirations['offers'] as $offer)
                                    <a href="{{ $offer['url'] }}" class="block rounded-lg border p-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ match($offer['urgency']) {
                                        'critical' => 'border-danger-300 bg-danger-50 dark:border-danger-400/30 dark:bg-danger-400/10',
                                        'warning' => 'border-warning-300 bg-warning-50 dark:border-warning-400/30 dark:bg-warning-400/10',
                                        default => 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800',
                                    } }}">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $offer['name'] }}</p>
                                                @if($offer['extra_info'])
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $offer['extra_info'] }}</p>
                                                @endif
                                            </div>
                                            <div class="flex-shrink-0 ml-3 text-right">
                                                @if($offer['urgency'] === 'critical')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400">
                                                        <x-heroicon-o-exclamation-triangle class="h-3 w-3 mr-1" />
                                                        {{ $offer['days_until_expiry'] === 0 ? 'Today' : ($offer['days_until_expiry'] === 1 ? '1 day' : $offer['days_until_expiry'] . ' days') }}
                                                    </span>
                                                @elseif($offer['urgency'] === 'warning')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400">
                                                        {{ $offer['days_until_expiry'] }} days
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                        {{ $offer['days_until_expiry'] }} days
                                                    </span>
                                                @endif
                                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $offer['valid_to'] }}</p>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                                <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-success-400" />
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">No offers expiring soon</p>
                            </div>
                        @endif
                    </div>

                    {{-- Expiring Price Books --}}
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <x-heroicon-o-book-open class="h-4 w-4 mr-1.5 text-primary-500" />
                            Price Books (30 days)
                        </h4>
                        @if(count($expirations['price_books']) > 0)
                            <div class="space-y-2">
                                @foreach($expirations['price_books'] as $priceBook)
                                    <a href="{{ $priceBook['url'] }}" class="block rounded-lg border p-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ match($priceBook['urgency']) {
                                        'critical' => 'border-danger-300 bg-danger-50 dark:border-danger-400/30 dark:bg-danger-400/10',
                                        'warning' => 'border-warning-300 bg-warning-50 dark:border-warning-400/30 dark:bg-warning-400/10',
                                        default => 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800',
                                    } }}">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $priceBook['name'] }}</p>
                                                @if($priceBook['extra_info'])
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $priceBook['extra_info'] }}</p>
                                                @endif
                                            </div>
                                            <div class="flex-shrink-0 ml-3 text-right">
                                                @if($priceBook['urgency'] === 'critical')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400">
                                                        <x-heroicon-o-exclamation-triangle class="h-3 w-3 mr-1" />
                                                        {{ $priceBook['days_until_expiry'] === 0 ? 'Today' : ($priceBook['days_until_expiry'] === 1 ? '1 day' : $priceBook['days_until_expiry'] . ' days') }}
                                                    </span>
                                                @elseif($priceBook['urgency'] === 'warning')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400">
                                                        {{ $priceBook['days_until_expiry'] }} days
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                        {{ $priceBook['days_until_expiry'] }} days
                                                    </span>
                                                @endif
                                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $priceBook['valid_to'] }}</p>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                                <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-success-400" />
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">No price books expiring soon</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Quick Actions --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                <x-heroicon-o-bolt class="h-5 w-5 mr-2 text-warning-500" />
                Quick Actions
            </h3>
        </div>
        <div class="fi-section-content p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach($this->getQuickActions() as $action)
                    @if($action['enabled'])
                        <a
                            href="{{ $action['url'] }}"
                            class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                        >
                            <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-1.5">
                                <x-dynamic-component :component="$action['icon']" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $action['label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $action['description'] }}</p>
                            </div>
                        </a>
                    @else
                        <div class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 opacity-60 cursor-not-allowed">
                            <div class="flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-700 p-1.5">
                                <x-dynamic-component :component="$action['icon']" class="h-4 w-4 text-gray-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $action['label'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $action['description'] }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
