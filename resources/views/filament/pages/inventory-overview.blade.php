<x-filament-panels::page>
    @php
        $globalKpis = $this->getGlobalKpis();
        $bottleStateMeta = $this->getBottleStateMeta();
        $topLocations = $this->getTopLocationsByBottleCount(8);
        $locationTypeBreakdown = $this->getLocationTypeBreakdown();
        $alerts = $this->getAlerts();
        $hasAlerts = $this->hasAlerts();
        $recentExceptions = $this->getRecentExceptions();
        $recentMovements = $this->getRecentMovementsSummary();
        $ownershipBreakdown = $this->getOwnershipBreakdown();
        $ownershipMeta = $this->getOwnershipTypeMeta();
        $atRiskAllocations = $this->getAtRiskAllocationDetails();
        $wmsSyncErrors = $this->getWmsSyncErrors();
    @endphp

    {{-- Top Summary Cards (Key KPIs) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Serialized Bottles --}}
        <a href="{{ $this->getBottlesUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-3">
                        <x-heroicon-o-beaker class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Serialized</p>
                        <p class="text-2xl font-semibold text-primary-600 dark:text-primary-400">{{ number_format($globalKpis['total_serialized_bottles']) }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Unserialized Inbound --}}
        <a href="{{ $this->getSerializationQueueUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-3">
                        <x-heroicon-o-inbox-arrow-down class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Serialization</p>
                        <p class="text-2xl font-semibold text-warning-600 dark:text-warning-400">{{ number_format($globalKpis['unserialized_inbound']) }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Committed Inventory --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                    <x-heroicon-o-lock-closed class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Committed</p>
                    <p class="text-2xl font-semibold text-info-600 dark:text-info-400">{{ number_format($globalKpis['committed_quantity']) }}</p>
                </div>
            </div>
        </div>

        {{-- Free Inventory --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-3">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Free</p>
                    <p class="text-2xl font-semibold text-success-600 dark:text-success-400">{{ number_format($globalKpis['free_quantity']) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid: Widget A (Bottles by State) + Widget B (Locations) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Widget A: Bottles by State Breakdown --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-beaker class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Bottles by State
                </h3>
            </div>
            <div class="fi-section-content p-6">
                @php
                    $bottleStates = [
                        ['key' => 'bottles_stored', 'state' => \App\Enums\Inventory\BottleState::Stored],
                        ['key' => 'bottles_reserved', 'state' => \App\Enums\Inventory\BottleState::ReservedForPicking],
                        ['key' => 'bottles_shipped', 'state' => \App\Enums\Inventory\BottleState::Shipped],
                        ['key' => 'bottles_consumed', 'state' => \App\Enums\Inventory\BottleState::Consumed],
                        ['key' => 'bottles_destroyed', 'state' => \App\Enums\Inventory\BottleState::Destroyed],
                        ['key' => 'bottles_missing', 'state' => \App\Enums\Inventory\BottleState::Missing],
                    ];
                    $totalBottles = $globalKpis['total_serialized_bottles'];
                @endphp
                <div class="space-y-4">
                    @foreach($bottleStates as $item)
                        @php
                            $count = $globalKpis[$item['key']];
                            $state = $item['state'];
                            $meta = $bottleStateMeta[$state->value];
                            $percentage = $totalBottles > 0 ? round(($count / $totalBottles) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <a href="{{ $this->getBottlesByStateUrl($state) }}" class="block group">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                    <x-dynamic-component :component="$meta['icon']" class="h-4 w-4 mr-2" />
                                    {{ $meta['label'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($count) }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="{{ $colorClass }} h-2 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Widget B: Inventory by Location --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-map-pin class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Top Locations by Stock
                </h3>
                <a href="{{ $this->getLocationsUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content">
                @if($topLocations->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($topLocations as $item)
                            <a
                                href="{{ $this->getBottlesByLocationUrl($item['location']) }}"
                                class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $item['location']->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item['location']->location_type->label() }}
                                    </p>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-primary-600 dark:text-primary-400">
                                            {{ number_format($item['bottle_count']) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">bottles</p>
                                    </div>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-map-pin class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Locations</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No locations with stored bottles.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Location Type Breakdown Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Warehouse Bottles --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Warehouse Stock</p>
                    <p class="text-xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ number_format($locationTypeBreakdown['warehouse_bottles']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $locationTypeBreakdown['warehouse_locations'] }} locations</p>
                </div>
                <div class="rounded-full bg-primary-50 dark:bg-primary-400/10 p-2">
                    <x-heroicon-o-building-office class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
        </div>

        {{-- Consignee Bottles --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Consignee Stock</p>
                    <p class="text-xl font-bold text-info-600 dark:text-info-400 mt-1">{{ number_format($locationTypeBreakdown['consignee_bottles']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $locationTypeBreakdown['consignee_locations'] }} consignees</p>
                </div>
                <div class="rounded-full bg-info-50 dark:bg-info-400/10 p-2">
                    <x-heroicon-o-building-storefront class="h-5 w-5 text-info-600 dark:text-info-400" />
                </div>
            </div>
        </div>

        {{-- Total Cases --}}
        <a href="{{ $this->getCasesUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Cases</p>
                        <p class="text-xl font-bold text-gray-600 dark:text-gray-400 mt-1">{{ number_format($globalKpis['total_cases']) }}</p>
                    </div>
                    <div class="rounded-full bg-gray-50 dark:bg-gray-400/10 p-2">
                        <x-heroicon-o-archive-box class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    </div>
                </div>
            </div>
        </a>

        {{-- Intact Cases --}}
        <a href="{{ $this->getIntactCasesUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Intact Cases</p>
                        <p class="text-xl font-bold text-success-600 dark:text-success-400 mt-1">{{ number_format($globalKpis['intact_cases']) }}</p>
                    </div>
                    <div class="rounded-full bg-success-50 dark:bg-success-400/10 p-2">
                        <x-heroicon-o-check-badge class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Widget C: Alerts & Exceptions + Ownership Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Widget C: Alerts & Exceptions --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 {{ $hasAlerts ? 'ring-2 ring-danger-500' : '' }}">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 {{ $hasAlerts ? 'bg-danger-50 dark:bg-danger-900/20' : '' }}">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 {{ $hasAlerts ? 'text-danger-800 dark:text-danger-200' : 'text-gray-950 dark:text-white' }}">
                    <x-heroicon-o-exclamation-triangle class="inline-block h-5 w-5 mr-2 -mt-0.5 {{ $hasAlerts ? 'text-danger-500' : 'text-warning-500' }}" />
                    Alerts & Exceptions
                </h3>
            </div>
            <div class="fi-section-content p-6">
                <div class="space-y-4">
                    {{-- Serialization Pending --}}
                    <a href="{{ $this->getSerializationQueueUrl() }}" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-center">
                            <div class="rounded-full p-2 {{ $alerts['serialization_pending'] > 0 ? 'bg-warning-100 dark:bg-warning-400/10' : 'bg-gray-100 dark:bg-gray-700' }}">
                                <x-heroicon-o-clock class="h-4 w-4 {{ $alerts['serialization_pending'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}" />
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Pending Serialization</span>
                        </div>
                        <span class="text-lg font-bold {{ $alerts['serialization_pending'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-500' }}">
                            {{ number_format($alerts['serialization_pending']) }}
                        </span>
                    </a>

                    {{-- Batches with Discrepancy --}}
                    <a href="{{ $this->getDiscrepancyBatchesUrl() }}" class="flex items-center justify-between p-3 rounded-lg {{ $alerts['batches_with_discrepancy'] > 0 ? 'bg-danger-50 dark:bg-danger-900/20' : '' }} hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-center">
                            <div class="rounded-full p-2 {{ $alerts['batches_with_discrepancy'] > 0 ? 'bg-danger-100 dark:bg-danger-400/10' : 'bg-gray-100 dark:bg-gray-700' }}">
                                <x-heroicon-o-exclamation-circle class="h-4 w-4 {{ $alerts['batches_with_discrepancy'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}" />
                            </div>
                            <span class="ml-3 text-sm font-medium {{ $alerts['batches_with_discrepancy'] > 0 ? 'text-danger-800 dark:text-danger-200' : 'text-gray-700 dark:text-gray-300' }}">Batches with Discrepancy</span>
                        </div>
                        <span class="text-lg font-bold {{ $alerts['batches_with_discrepancy'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500' }}">
                            {{ number_format($alerts['batches_with_discrepancy']) }}
                        </span>
                    </a>

                    {{-- Committed at Risk --}}
                    <div x-data="{ expanded: false }" class="rounded-lg {{ $alerts['committed_at_risk'] > 0 ? 'bg-danger-50 dark:bg-danger-900/20' : '' }}">
                        <button
                            type="button"
                            @click="expanded = !expanded"
                            class="flex items-center justify-between w-full p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $alerts['committed_at_risk'] > 0 ? 'hover:bg-danger-100 dark:hover:bg-danger-900/30' : '' }}"
                        >
                            <div class="flex items-center">
                                <div class="rounded-full p-2 {{ $alerts['committed_at_risk'] > 0 ? 'bg-danger-100 dark:bg-danger-400/10' : 'bg-gray-100 dark:bg-gray-700' }}">
                                    <x-heroicon-o-shield-exclamation class="h-4 w-4 {{ $alerts['committed_at_risk'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}" />
                                </div>
                                <div class="ml-3 text-left">
                                    <span class="text-sm font-medium {{ $alerts['committed_at_risk'] > 0 ? 'text-danger-800 dark:text-danger-200' : 'text-gray-700 dark:text-gray-300' }}">Committed at Risk</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Free &lt; 10% of committed</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <span class="text-lg font-bold {{ $alerts['committed_at_risk'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500' }} mr-2">
                                    {{ number_format($alerts['committed_at_risk']) }}
                                </span>
                                @if($alerts['committed_at_risk'] > 0)
                                    <x-heroicon-o-chevron-down class="h-4 w-4 text-danger-500 transition-transform" x-bind:class="{ 'rotate-180': expanded }" />
                                @endif
                            </div>
                        </button>
                        @if($alerts['committed_at_risk'] > 0 && $atRiskAllocations->count() > 0)
                            <div x-show="expanded" x-collapse class="px-3 pb-3">
                                <div class="mt-2 space-y-2 border-t border-danger-200 dark:border-danger-700 pt-3">
                                    <p class="text-xs font-medium text-danger-700 dark:text-danger-300 mb-2">At-Risk Allocations:</p>
                                    @foreach($atRiskAllocations->take(5) as $item)
                                        <div class="flex items-center justify-between text-xs bg-white dark:bg-gray-800 rounded p-2">
                                            <span class="text-gray-700 dark:text-gray-300 truncate flex-1 mr-2">
                                                {{ $item['allocation']->name ?? 'Allocation #' . $item['allocation']->id }}
                                            </span>
                                            <span class="text-danger-600 dark:text-danger-400 font-semibold whitespace-nowrap">
                                                {{ $item['free'] }} free / {{ $item['committed'] }} committed
                                            </span>
                                        </div>
                                    @endforeach
                                    @if($atRiskAllocations->count() > 5)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">
                                            + {{ $atRiskAllocations->count() - 5 }} more allocations...
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- WMS Sync Errors --}}
                    <div x-data="{ expanded: false }" class="rounded-lg {{ $alerts['wms_sync_errors'] > 0 ? 'bg-danger-50 dark:bg-danger-900/20' : '' }}">
                        <button
                            type="button"
                            @click="expanded = !expanded"
                            class="flex items-center justify-between w-full p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $alerts['wms_sync_errors'] > 0 ? 'hover:bg-danger-100 dark:hover:bg-danger-900/30' : '' }}"
                        >
                            <div class="flex items-center">
                                <div class="rounded-full p-2 {{ $alerts['wms_sync_errors'] > 0 ? 'bg-danger-100 dark:bg-danger-400/10' : 'bg-gray-100 dark:bg-gray-700' }}">
                                    <x-heroicon-o-arrow-path class="h-4 w-4 {{ $alerts['wms_sync_errors'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}" />
                                </div>
                                <div class="ml-3 text-left">
                                    <span class="text-sm font-medium {{ $alerts['wms_sync_errors'] > 0 ? 'text-danger-800 dark:text-danger-200' : 'text-gray-700 dark:text-gray-300' }}">WMS Sync Errors</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Last 7 days</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <span class="text-lg font-bold {{ $alerts['wms_sync_errors'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500' }} mr-2">
                                    {{ number_format($alerts['wms_sync_errors']) }}
                                </span>
                                @if($alerts['wms_sync_errors'] > 0)
                                    <x-heroicon-o-chevron-down class="h-4 w-4 text-danger-500 transition-transform" x-bind:class="{ 'rotate-180': expanded }" />
                                @endif
                            </div>
                        </button>
                        @if($alerts['wms_sync_errors'] > 0 && $wmsSyncErrors->count() > 0)
                            <div x-show="expanded" x-collapse class="px-3 pb-3">
                                <div class="mt-2 space-y-2 border-t border-danger-200 dark:border-danger-700 pt-3">
                                    <p class="text-xs font-medium text-danger-700 dark:text-danger-300 mb-2">Recent WMS Errors:</p>
                                    @foreach($wmsSyncErrors->take(5) as $error)
                                        <div class="text-xs bg-white dark:bg-gray-800 rounded p-2">
                                            <div class="flex items-center justify-between">
                                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                                    {{ ucwords(str_replace('_', ' ', $error->exception_type)) }}
                                                </span>
                                                <span class="text-gray-500 dark:text-gray-400">
                                                    {{ $error->created_at->diffForHumans() }}
                                                </span>
                                            </div>
                                            <p class="text-gray-600 dark:text-gray-400 truncate mt-1">
                                                {{ Str::limit($error->reason, 80) }}
                                            </p>
                                        </div>
                                    @endforeach
                                    @if($wmsSyncErrors->count() > 5)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">
                                            + {{ $alerts['wms_sync_errors'] - 5 }} more errors...
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Ownership Breakdown --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-user-group class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Ownership Breakdown
                </h3>
            </div>
            <div class="fi-section-content p-6">
                @php
                    $totalOwnership = array_sum($ownershipBreakdown);
                @endphp
                <div class="space-y-4">
                    @foreach($ownershipBreakdown as $type => $count)
                        @php
                            $meta = $ownershipMeta[$type];
                            $percentage = $totalOwnership > 0 ? round(($count / $totalOwnership) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                'primary' => 'bg-primary-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $meta['label'] }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($count) }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="{{ $colorClass }} h-2 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Movement Summary --}}
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($recentMovements['today_count']) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Movements Today</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-info-600 dark:text-info-400">{{ number_format($recentMovements['this_week_count']) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">This Week</p>
                        </div>
                    </div>
                    @if($recentMovements['last_movement_at'])
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center mt-4">
                            Last movement: {{ $recentMovements['last_movement_at'] }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Exceptions (if any) --}}
    @if($recentExceptions->count() > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-document-magnifying-glass class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                    Unresolved Exceptions ({{ $alerts['unresolved_exceptions'] }})
                </h3>
            </div>
            <div class="fi-section-content">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($recentExceptions as $exception)
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ ucwords(str_replace('_', ' ', $exception->exception_type)) }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 truncate">
                                        {{ Str::limit($exception->reason, 100) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $exception->created_at->diffForHumans() }}
                                        @if($exception->creator)
                                            by {{ $exception->creator->name }}
                                        @endif
                                    </p>
                                </div>
                                <span class="ml-4 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-400/10 dark:text-warning-400">
                                    Pending
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
