<x-filament-panels::page>
    @php
        $allocationMetrics = $this->getAllocationMetrics();
        $allocationStatusCounts = $this->getAllocationStatusCounts();
        $allocationStatusMeta = $this->getAllocationStatusMeta();
        $voucherMetrics = $this->getVoucherMetrics();
        $voucherStateCounts = $this->getVoucherStateCounts();
        $voucherStateMeta = $this->getVoucherStateMeta();
        $reservationMetrics = $this->getReservationMetrics();
        $transferMetrics = $this->getTransferMetrics();
        $nearExhaustionAllocations = $this->getNearExhaustionAllocations();
        $expiredTransfersToday = $this->getExpiredTransfersToday();
        $totalAllocations = array_sum($allocationStatusCounts);
        $totalVouchers = array_sum($voucherStateCounts);
    @endphp

    {{-- Summary Cards Row --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-4">
        {{-- Active Allocations --}}
        <a href="{{ $this->getActiveAllocationsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-2">
                        <x-heroicon-o-cube-transparent class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Allocations</p>
                        <p class="text-xl font-semibold text-success-600 dark:text-success-400">{{ $allocationMetrics['total_active'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Near Exhaustion Allocations --}}
        <a href="{{ $this->getNearExhaustionAllocationsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-2">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Near Exhaustion</p>
                        <p class="text-xl font-semibold text-warning-600 dark:text-warning-400">{{ $allocationMetrics['near_exhaustion'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Total Issued Vouchers --}}
        <a href="{{ $this->getIssuedVouchersUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-2">
                        <x-heroicon-o-ticket class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Issued Vouchers</p>
                        <p class="text-xl font-semibold text-primary-600 dark:text-primary-400">{{ $voucherMetrics['total_issued'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Pending Transfers --}}
        <a href="{{ $this->getPendingTransfersUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-2">
                        <x-heroicon-o-arrows-right-left class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Transfers</p>
                        <p class="text-xl font-semibold text-info-600 dark:text-info-400">{{ $transferMetrics['pending_count'] }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        {{-- Allocations by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-cube-transparent class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Allocations by Status
                </h3>
            </div>
            <div class="fi-section-content p-4">
                <div class="space-y-4">
                    @foreach($allocationStatusCounts as $status => $count)
                        @php
                            $meta = $allocationStatusMeta[$status];
                            $percentage = $totalAllocations > 0 ? round(($count / $totalAllocations) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <div>
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
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Vouchers by State --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-ticket class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Vouchers by State
                </h3>
            </div>
            <div class="fi-section-content p-4">
                <div class="space-y-4">
                    @foreach($voucherStateCounts as $state => $count)
                        @php
                            $meta = $voucherStateMeta[$state];
                            $percentage = $totalVouchers > 0 ? round(($count / $totalVouchers) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <div>
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
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Metrics Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        {{-- Voucher Pending Redemption --}}
        <a href="{{ $this->getPendingRedemptionVouchersUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Pending Redemption</p>
                        <p class="text-xl font-bold text-warning-600 dark:text-warning-400 mt-1">{{ $voucherMetrics['pending_redemption'] }}</p>
                    </div>
                    <div class="rounded-full bg-warning-50 dark:bg-warning-400/10 p-1.5">
                        <x-heroicon-o-lock-closed class="h-4 w-4 text-warning-600 dark:text-warning-400" />
                    </div>
                </div>
            </div>
        </a>

        {{-- Redeemed This Month --}}
        <a href="{{ $this->getRedeemedVouchersUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Redeemed This Month</p>
                        <p class="text-xl font-bold text-info-600 dark:text-info-400 mt-1">{{ $voucherMetrics['redeemed_this_month'] }}</p>
                    </div>
                    <div class="rounded-full bg-info-50 dark:bg-info-400/10 p-1.5">
                        <x-heroicon-o-check-badge class="h-4 w-4 text-info-600 dark:text-info-400" />
                    </div>
                </div>
            </div>
        </a>

        {{-- Active Reservations --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Active Reservations</p>
                    <p class="text-xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ $reservationMetrics['active_count'] }}</p>
                </div>
                <div class="rounded-full bg-primary-50 dark:bg-primary-400/10 p-1.5">
                    <x-heroicon-o-clock class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
        </div>

        {{-- Expired Reservations Today --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Expired Today (Res.)</p>
                    <p class="text-xl font-bold text-gray-600 dark:text-gray-400 mt-1">{{ $reservationMetrics['expired_today'] }}</p>
                </div>
                <div class="rounded-full bg-gray-50 dark:bg-gray-400/10 p-1.5">
                    <x-heroicon-o-x-mark class="h-4 w-4 text-gray-600 dark:text-gray-400" />
                </div>
            </div>
        </div>
    </div>

    {{-- Transfer Metrics Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
        {{-- Closed Allocations This Month --}}
        <a href="{{ $this->getClosedAllocationsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Allocations Closed This Month</p>
                        <p class="text-xl font-bold text-danger-600 dark:text-danger-400 mt-1">{{ $allocationMetrics['closed_this_month'] }}</p>
                    </div>
                    <div class="rounded-full bg-danger-50 dark:bg-danger-400/10 p-1.5">
                        <x-heroicon-o-x-circle class="h-4 w-4 text-danger-600 dark:text-danger-400" />
                    </div>
                </div>
            </div>
        </a>

        {{-- Failed Transfers This Month --}}
        <a href="{{ $this->getExpiredTransfersUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Failed Transfers This Month</p>
                        <p class="text-xl font-bold text-danger-600 dark:text-danger-400 mt-1">{{ $transferMetrics['failed_transfers'] }}</p>
                    </div>
                    <div class="rounded-full bg-danger-50 dark:bg-danger-400/10 p-1.5">
                        <x-heroicon-o-arrows-right-left class="h-4 w-4 text-danger-600 dark:text-danger-400" />
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Problem Areas --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Near Exhaustion Allocations List --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-exclamation-triangle class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                    Allocations Near Exhaustion
                </h3>
                @if($nearExhaustionAllocations->count() > 0)
                    <a
                        href="{{ $this->getNearExhaustionAllocationsUrl() }}"
                        class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        View All &rarr;
                    </a>
                @endif
            </div>
            <div class="fi-section-content">
                @if($nearExhaustionAllocations->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($nearExhaustionAllocations as $allocation)
                            <a
                                href="{{ $this->getAllocationViewUrl($allocation) }}"
                                class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $allocation->getBottleSkuLabel() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        ID: {{ $allocation->id }}
                                    </p>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-danger-600 dark:text-danger-400">
                                            {{ $allocation->remaining_quantity }} / {{ $allocation->total_quantity }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            remaining
                                        </p>
                                    </div>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Good!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No allocations are near exhaustion.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Expired Transfers Today --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-clock class="inline-block h-5 w-5 mr-2 -mt-0.5 text-gray-500" />
                    Expired Transfers Today
                </h3>
                @if($expiredTransfersToday->count() > 0)
                    <a
                        href="{{ $this->getExpiredTransfersUrl() }}"
                        class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        View All &rarr;
                    </a>
                @endif
            </div>
            <div class="fi-section-content">
                @if($expiredTransfersToday->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($expiredTransfersToday as $transfer)
                            <a
                                href="{{ $this->getTransferViewUrl($transfer) }}"
                                class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        Voucher #{{ $transfer->voucher_id }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $transfer->fromCustomer?->name ?? 'Unknown' }} &rarr; {{ $transfer->toCustomer?->name ?? 'Unknown' }}
                                    </p>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        <x-heroicon-o-x-mark class="h-3 w-3 mr-1" />
                                        Expired
                                    </span>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Expired Transfers</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No transfers have expired today.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
