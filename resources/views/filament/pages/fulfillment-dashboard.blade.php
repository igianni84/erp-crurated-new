<x-filament-panels::page>
    @php
        $statusCounts = $this->getShippingOrderStatusCounts();
        $statusMeta = $this->getShippingOrderStatusMeta();
        $attentionCounts = $this->getAttentionCounts();
        $shipmentMetrics = $this->getShipmentMetrics();
        $exceptionTypeCounts = $this->getExceptionTypeCounts();
        $exceptionTypeMeta = $this->getExceptionTypeMeta();
        $totalActiveExceptions = $this->getTotalActiveExceptions();
        $sosWithExceptions = $this->getShippingOrdersWithExceptions();
        $sosNearShipDate = $this->getShippingOrdersNearShipDate();
        $totalSOs = array_sum($statusCounts);
        $totalActiveExceptionsByType = array_sum($exceptionTypeCounts);
    @endphp

    {{-- Summary Cards Row --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Draft SOs --}}
        <a href="{{ $this->getShippingOrdersUrl('draft') }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-gray-50 dark:bg-gray-400/10 p-3">
                        <x-heroicon-o-document-text class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Draft</p>
                        <p class="text-2xl font-semibold text-gray-600 dark:text-gray-400">{{ $statusCounts['draft'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Planned SOs --}}
        <a href="{{ $this->getShippingOrdersUrl('planned') }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                        <x-heroicon-o-clipboard-document-list class="h-6 w-6 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Planned</p>
                        <p class="text-2xl font-semibold text-info-600 dark:text-info-400">{{ $statusCounts['planned'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Picking SOs --}}
        <a href="{{ $this->getShippingOrdersUrl('picking') }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-3">
                        <x-heroicon-o-cube class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Picking</p>
                        <p class="text-2xl font-semibold text-warning-600 dark:text-warning-400">{{ $statusCounts['picking'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- On Hold SOs --}}
        <a href="{{ $this->getOnHoldShippingOrdersUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-danger-50 dark:bg-danger-400/10 p-3">
                        <x-heroicon-o-pause-circle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">On Hold</p>
                        <p class="text-2xl font-semibold text-danger-600 dark:text-danger-400">{{ $statusCounts['on_hold'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Widget A: SO by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-document-text class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Shipping Orders by Status
                </h3>
            </div>
            <div class="fi-section-content p-6">
                <div class="space-y-4">
                    @foreach($statusCounts as $status => $count)
                        @php
                            $meta = $statusMeta[$status] ?? ['label' => $status, 'color' => 'gray', 'icon' => 'heroicon-o-document'];
                            $percentage = $totalSOs > 0 ? round(($count / $totalSOs) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <a href="{{ $this->getShippingOrdersUrl($status) }}" class="block hover:opacity-80 transition-opacity">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                                    <x-dynamic-component :component="$meta['icon']" class="h-4 w-4 mr-2" />
                                    {{ $meta['label'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="{{ $colorClass }} h-2 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Widget D: Exception Summary --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-exclamation-triangle class="inline-block h-5 w-5 mr-2 -mt-0.5 text-danger-500" />
                    Active Exceptions ({{ $totalActiveExceptions }})
                </h3>
                @if($totalActiveExceptions > 0)
                    <a
                        href="{{ $this->getExceptionsUrl() }}"
                        class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        View All &rarr;
                    </a>
                @endif
            </div>
            <div class="fi-section-content p-6">
                @if($totalActiveExceptionsByType > 0)
                    <div class="space-y-3">
                        @foreach($exceptionTypeCounts as $type => $count)
                            @if($count > 0)
                                @php
                                    $meta = $exceptionTypeMeta[$type] ?? ['label' => $type, 'color' => 'gray', 'icon' => 'heroicon-o-exclamation-circle'];
                                    $colorClass = match($meta['color']) {
                                        'gray' => 'text-gray-600 bg-gray-50 dark:text-gray-400 dark:bg-gray-800',
                                        'warning' => 'text-warning-600 bg-warning-50 dark:text-warning-400 dark:bg-warning-900/20',
                                        'info' => 'text-info-600 bg-info-50 dark:text-info-400 dark:bg-info-900/20',
                                        'success' => 'text-success-600 bg-success-50 dark:text-success-400 dark:bg-success-900/20',
                                        'danger' => 'text-danger-600 bg-danger-50 dark:text-danger-400 dark:bg-danger-900/20',
                                        default => 'text-gray-600 bg-gray-50 dark:text-gray-400 dark:bg-gray-800',
                                    };
                                @endphp
                                <a
                                    href="{{ $this->getExceptionsByTypeUrl($type) }}"
                                    class="flex items-center justify-between p-3 rounded-lg {{ $colorClass }} hover:opacity-80 transition-opacity"
                                >
                                    <span class="flex items-center">
                                        <x-dynamic-component :component="$meta['icon']" class="h-5 w-5 mr-2" />
                                        <span class="font-medium">{{ $meta['label'] }}</span>
                                    </span>
                                    <span class="text-lg font-bold">{{ $count }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No active exceptions.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Widget C: Shipment Metrics Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        {{-- Shipments Today --}}
        <a href="{{ $this->getShipmentsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Shipped Today</p>
                        <p class="text-xl font-bold text-success-600 dark:text-success-400 mt-1">{{ $shipmentMetrics['today'] }}</p>
                    </div>
                    <div class="rounded-full bg-success-50 dark:bg-success-400/10 p-2">
                        <x-heroicon-o-truck class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                </div>
            </div>
        </a>

        {{-- Shipments This Week --}}
        <a href="{{ $this->getShipmentsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Shipped This Week</p>
                        <p class="text-xl font-bold text-info-600 dark:text-info-400 mt-1">{{ $shipmentMetrics['this_week'] }}</p>
                    </div>
                    <div class="rounded-full bg-info-50 dark:bg-info-400/10 p-2">
                        <x-heroicon-o-calendar class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                </div>
            </div>
        </a>

        {{-- Pending Confirmation --}}
        <a href="{{ $this->getShipmentsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Pending Confirmation</p>
                        <p class="text-xl font-bold text-warning-600 dark:text-warning-400 mt-1">{{ $shipmentMetrics['pending_confirmation'] }}</p>
                    </div>
                    <div class="rounded-full bg-warning-50 dark:bg-warning-400/10 p-2">
                        <x-heroicon-o-clock class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Widget B: SOs Requiring Attention --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- SOs with Exceptions --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-exclamation-circle class="inline-block h-5 w-5 mr-2 -mt-0.5 text-danger-500" />
                    SOs with Active Exceptions ({{ $attentionCounts['with_exceptions'] }})
                </h3>
            </div>
            <div class="fi-section-content">
                @if($sosWithExceptions->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($sosWithExceptions as $so)
                            <a
                                href="{{ $this->getShippingOrderViewUrl($so) }}"
                                class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        SO #{{ Str::limit($so->id, 8, '...') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $so->customer?->name ?? 'No Customer' }}
                                    </p>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900/30 dark:text-danger-400">
                                        {{ $so->exceptions->count() }} {{ Str::plural('exception', $so->exceptions->count()) }}
                                    </span>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No SOs have active exceptions.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- SOs Near Ship Date --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-clock class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                    SOs Near Ship Date ({{ $attentionCounts['near_ship_date'] }})
                </h3>
            </div>
            <div class="fi-section-content">
                @if($sosNearShipDate->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($sosNearShipDate as $so)
                            @php
                                $daysUntil = \Carbon\Carbon::now()->diffInDays($so->requested_ship_date, false);
                                $urgencyClass = $daysUntil <= 0 ? 'text-danger-600 dark:text-danger-400' : ($daysUntil <= 1 ? 'text-warning-600 dark:text-warning-400' : 'text-info-600 dark:text-info-400');
                                $urgencyBgClass = $daysUntil <= 0 ? 'bg-danger-100 dark:bg-danger-900/30' : ($daysUntil <= 1 ? 'bg-warning-100 dark:bg-warning-900/30' : 'bg-info-100 dark:bg-info-900/30');
                            @endphp
                            <a
                                href="{{ $this->getShippingOrderViewUrl($so) }}"
                                class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        SO #{{ Str::limit($so->id, 8, '...') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $so->customer?->name ?? 'No Customer' }}
                                    </p>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $urgencyBgClass }} {{ $urgencyClass }}">
                                        @if($daysUntil <= 0)
                                            Overdue
                                        @elseif($daysUntil == 1)
                                            Tomorrow
                                        @else
                                            {{ $daysUntil }} days
                                        @endif
                                    </span>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">On Track!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No SOs are approaching their ship date.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
