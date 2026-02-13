<x-filament-panels::page>
    @php
        $summaryMetrics = $this->getSummaryMetrics();
        $demandExecutionMetrics = $this->getDemandExecutionMetrics();
        $intentStatusCounts = $this->getIntentStatusCounts();
        $intentStatusMeta = $this->getIntentStatusMeta();
        $poStatusCounts = $this->getPOStatusCounts();
        $poStatusMeta = $this->getPOStatusMeta();
        $inboundStatusCounts = $this->getInboundStatusCounts();
        $inboundStatusMeta = $this->getInboundStatusMeta();
        $bottlingDeadlineCounts = $this->getBottlingDeadlineCounts();
        $bottlingPreferenceCounts = $this->getBottlingPreferenceCounts();
        $exceptionCounts = $this->getExceptionCounts();
        $intentsAwaitingApproval = $this->getIntentsAwaitingApproval();
        $inboundsWithPendingOwnership = $this->getInboundsWithPendingOwnership();
        $urgentBottlingInstructions = $this->getUrgentBottlingInstructions();
        $totalIntents = array_sum($intentStatusCounts);
        $totalPOs = array_sum($poStatusCounts);
        $totalInbounds = array_sum($inboundStatusCounts);
        $hasExceptions = ($exceptionCounts['pending_ownership'] + $exceptionCounts['unlinked_inbounds'] + $exceptionCounts['overdue_pos'] + $exceptionCounts['variance_pos']) > 0;
        $pollInterval = $this->getPollInterval();
        $dateRangeOptions = $this->getDateRangeOptions();
        $autoRefreshOptions = $this->getAutoRefreshOptions();
    @endphp

    {{-- Auto-refresh polling (if enabled) --}}
    @if($pollInterval)
        <div wire:poll.{{ $pollInterval }}="refreshDashboard"></div>
    @endif

    {{-- Dashboard Controls Bar --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4">
        <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-4">
            {{-- Left side: Description --}}
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Control tower for Module D - Procurement & Inbound. This dashboard shows where priorities are identified.
                </p>
            </div>

            {{-- Right side: Controls --}}
            <div class="flex flex-wrap items-center gap-3">
                {{-- Date Range Selector --}}
                <div class="flex items-center gap-2">
                    <label for="date-range" class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        <x-heroicon-o-calendar class="inline-block h-4 w-4 mr-1" />
                        Date Range:
                    </label>
                    <select
                        id="date-range"
                        wire:model.live="dateRangeDays"
                        wire:change="setDateRange($event.target.value)"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                    >
                        @foreach($dateRangeOptions as $value => $label)
                            <option value="{{ $value }}" {{ $this->dateRangeDays == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Auto-Refresh Selector --}}
                <div class="flex items-center gap-2">
                    <label for="auto-refresh" class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        <x-heroicon-o-arrow-path class="inline-block h-4 w-4 mr-1" />
                        Auto-refresh:
                    </label>
                    <select
                        id="auto-refresh"
                        wire:model.live="autoRefreshMinutes"
                        wire:change="setAutoRefresh($event.target.value)"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                    >
                        @foreach($autoRefreshOptions as $value => $label)
                            <option value="{{ $value }}" {{ $this->autoRefreshMinutes == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Refresh Button --}}
                <button
                    type="button"
                    wire:click="refreshDashboard"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-wait"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:ring-primary-300 rounded-lg transition-colors dark:bg-primary-500 dark:hover:bg-primary-600 dark:focus:ring-primary-800"
                >
                    <x-heroicon-o-arrow-path class="h-4 w-4 mr-2" wire:loading.class="animate-spin" wire:target="refreshDashboard" />
                    <span wire:loading.remove wire:target="refreshDashboard">Refresh</span>
                    <span wire:loading wire:target="refreshDashboard">Refreshing...</span>
                </button>

                {{-- Last Updated Timestamp --}}
                <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                    <x-heroicon-o-clock class="h-3 w-3" />
                    <span>Updated: {{ $this->getLastRefreshedFormatted() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards Row (4 main widgets) --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-4">
        {{-- Active Intents --}}
        <a href="{{ $this->getIntentsListUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-2">
                        <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Intents</p>
                        <p class="text-xl font-semibold text-primary-600 dark:text-primary-400">{{ $summaryMetrics['total_intents'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Pending Approvals --}}
        <a href="{{ $this->getPendingApprovalsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ $summaryMetrics['pending_approvals'] > 0 ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-2">
                        <x-heroicon-o-clock class="h-5 w-5 {{ $summaryMetrics['pending_approvals'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Approvals</p>
                        <p class="text-xl font-semibold {{ $summaryMetrics['pending_approvals'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}">{{ $summaryMetrics['pending_approvals'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Pending Inbounds --}}
        <a href="{{ $this->getInboundsListUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-2">
                        <x-heroicon-o-inbox-arrow-down class="h-5 w-5 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Inbounds</p>
                        <p class="text-xl font-semibold text-info-600 dark:text-info-400">{{ $summaryMetrics['pending_inbounds'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Bottling Deadlines (based on date range) --}}
        <a href="{{ $this->getBottlingDeadlinesUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ $summaryMetrics['bottling_deadlines'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-2">
                        <x-heroicon-o-calendar-days class="h-5 w-5 {{ $summaryMetrics['bottling_deadlines'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Deadlines ({{ $summaryMetrics['date_range_days'] }}d)</p>
                        <p class="text-xl font-semibold {{ $summaryMetrics['bottling_deadlines'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">{{ $summaryMetrics['bottling_deadlines'] }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Widget A: Demand → Execution Knowability --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-arrow-trending-up class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                Demand → Execution
            </h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">Sourcing pipeline visibility</span>
        </div>
        <div class="fi-section-content p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Vouchers Awaiting Sourcing --}}
                @php
                    $voucherStatus = $this->getDemandExecutionHealthStatus('vouchers_awaiting_sourcing', $demandExecutionMetrics['vouchers_awaiting_sourcing']);
                    $voucherColorBg = match($voucherStatus) {
                        'success' => 'bg-success-50 dark:bg-success-400/10',
                        'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                        'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                        default => 'bg-gray-50 dark:bg-gray-800',
                    };
                    $voucherColorText = match($voucherStatus) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <a href="{{ $this->getVouchersAwaitingSourcingUrl() }}" class="block group">
                    <div class="p-4 rounded-lg {{ $voucherColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Vouchers Awaiting</p>
                                <p class="text-2xl font-bold {{ $voucherColorText }} mt-1">{{ $demandExecutionMetrics['vouchers_awaiting_sourcing'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">sourcing needed</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-ticket class="h-8 w-8 {{ $voucherColorText }} opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>

                {{-- Allocation-Driven Pending --}}
                @php
                    $allocStatus = $this->getDemandExecutionHealthStatus('allocation_driven_pending', $demandExecutionMetrics['allocation_driven_pending']);
                    $allocColorBg = match($allocStatus) {
                        'success' => 'bg-success-50 dark:bg-success-400/10',
                        'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                        'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                        default => 'bg-gray-50 dark:bg-gray-800',
                    };
                    $allocColorText = match($allocStatus) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <a href="{{ $this->getAllocationDrivenPendingUrl() }}" class="block group">
                    <div class="p-4 rounded-lg {{ $allocColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Allocation-Driven</p>
                                <p class="text-2xl font-bold {{ $allocColorText }} mt-1">{{ $demandExecutionMetrics['allocation_driven_pending'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">pending procurement</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-cube class="h-8 w-8 {{ $allocColorText }} opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>

                {{-- Bottling Required Demand --}}
                @php
                    $bottlingStatus = $this->getDemandExecutionHealthStatus('bottling_required_demand', $demandExecutionMetrics['bottling_required_demand']);
                    $bottlingColorBg = match($bottlingStatus) {
                        'success' => 'bg-success-50 dark:bg-success-400/10',
                        'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                        'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                        default => 'bg-gray-50 dark:bg-gray-800',
                    };
                    $bottlingColorText = match($bottlingStatus) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <a href="{{ $this->getBottlingRequiredDemandUrl() }}" class="block group">
                    <div class="p-4 rounded-lg {{ $bottlingColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Bottling Required</p>
                                <p class="text-2xl font-bold {{ $bottlingColorText }} mt-1">{{ $demandExecutionMetrics['bottling_required_demand'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">liquid demand</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-beaker class="h-8 w-8 {{ $bottlingColorText }} opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>

                {{-- Inbound Overdue vs Expected (Ratio) --}}
                @php
                    $overdueStatus = $this->getDemandExecutionHealthStatus('inbound_overdue_ratio', $demandExecutionMetrics['inbound_overdue_ratio']);
                    $overdueColorBg = match($overdueStatus) {
                        'success' => 'bg-success-50 dark:bg-success-400/10',
                        'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                        'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                        default => 'bg-gray-50 dark:bg-gray-800',
                    };
                    $overdueColorText = match($overdueStatus) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <a href="{{ $this->getOverduePOsUrl() }}" class="block group">
                    <div class="p-4 rounded-lg {{ $overdueColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Inbound Overdue</p>
                                <p class="text-2xl font-bold {{ $overdueColorText }} mt-1">
                                    {{ $demandExecutionMetrics['inbound_overdue'] }}<span class="text-base font-normal text-gray-400"> / {{ $demandExecutionMetrics['inbound_expected_in_range'] + $demandExecutionMetrics['inbound_overdue'] }}</span>
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ number_format($demandExecutionMetrics['inbound_overdue_ratio'], 1) }}% overdue ({{ $demandExecutionMetrics['date_range_days'] }}d)</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-clock class="h-8 w-8 {{ $overdueColorText }} opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Legend / Help text --}}
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-success-500 mr-2"></span>
                        <span>Healthy</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-warning-500 mr-2"></span>
                        <span>Attention</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-danger-500 mr-2"></span>
                        <span>Critical</span>
                    </div>
                    <span class="text-gray-400 dark:text-gray-500">|</span>
                    <span>Click each metric to view filtered list</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Widget B: Bottling Risk --}}
    @php
        $bottlingRiskMetrics = $this->getBottlingRiskMetrics();
    @endphp
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-beaker class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                Bottling Risk
            </h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">Deadline & preference tracking</span>
        </div>
        <div class="fi-section-content p-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Left side: Deadline Horizons --}}
                <div>
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 flex items-center">
                        <x-heroicon-o-calendar class="h-4 w-4 mr-2 text-gray-400" />
                        Upcoming Deadlines
                    </h4>
                    <div class="grid grid-cols-3 gap-3">
                        {{-- 30 days --}}
                        @php
                            $d30Status = $this->getBottlingRiskHealthStatus('deadlines_30d', $bottlingRiskMetrics['deadlines_30d']);
                            $d30ColorBg = match($d30Status) {
                                'success' => 'bg-success-50 dark:bg-success-400/10',
                                'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                                'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                                default => 'bg-gray-50 dark:bg-gray-800',
                            };
                            $d30ColorText = match($d30Status) {
                                'success' => 'text-success-600 dark:text-success-400',
                                'warning' => 'text-warning-600 dark:text-warning-400',
                                'danger' => 'text-danger-600 dark:text-danger-400',
                                default => 'text-gray-600 dark:text-gray-400',
                            };
                        @endphp
                        <a href="{{ $this->getBottling30dDeadlinesUrl() }}" class="block group">
                            <div class="text-center p-3 rounded-lg {{ $d30ColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                                <p class="text-2xl font-bold {{ $d30ColorText }}">{{ $bottlingRiskMetrics['deadlines_30d'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">30 days</p>
                            </div>
                        </a>

                        {{-- 60 days --}}
                        @php
                            $d60Status = $this->getBottlingRiskHealthStatus('deadlines_60d', $bottlingRiskMetrics['deadlines_60d']);
                            $d60ColorBg = match($d60Status) {
                                'success' => 'bg-success-50 dark:bg-success-400/10',
                                'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                                'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                                default => 'bg-gray-50 dark:bg-gray-800',
                            };
                            $d60ColorText = match($d60Status) {
                                'success' => 'text-success-600 dark:text-success-400',
                                'warning' => 'text-warning-600 dark:text-warning-400',
                                'danger' => 'text-danger-600 dark:text-danger-400',
                                default => 'text-gray-600 dark:text-gray-400',
                            };
                        @endphp
                        <a href="{{ $this->getBottlingInstructionsListUrl() }}" class="block group">
                            <div class="text-center p-3 rounded-lg {{ $d60ColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                                <p class="text-2xl font-bold {{ $d60ColorText }}">{{ $bottlingRiskMetrics['deadlines_60d'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">60 days</p>
                            </div>
                        </a>

                        {{-- 90 days --}}
                        @php
                            $d90Status = $this->getBottlingRiskHealthStatus('deadlines_90d', $bottlingRiskMetrics['deadlines_90d']);
                            $d90ColorBg = match($d90Status) {
                                'success' => 'bg-success-50 dark:bg-success-400/10',
                                'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                                'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                                default => 'bg-gray-50 dark:bg-gray-800',
                            };
                            $d90ColorText = match($d90Status) {
                                'success' => 'text-success-600 dark:text-success-400',
                                'warning' => 'text-warning-600 dark:text-warning-400',
                                'danger' => 'text-danger-600 dark:text-danger-400',
                                default => 'text-gray-600 dark:text-gray-400',
                            };
                        @endphp
                        <a href="{{ $this->getBottlingInstructionsListUrl() }}" class="block group">
                            <div class="text-center p-3 rounded-lg {{ $d90ColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                                <p class="text-2xl font-bold {{ $d90ColorText }}">{{ $bottlingRiskMetrics['deadlines_90d'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">90 days</p>
                            </div>
                        </a>
                    </div>

                    {{-- Urgent highlight (< 14 days) --}}
                    @if($bottlingRiskMetrics['deadlines_14d'] > 0)
                        <a href="{{ $this->getBottlingDeadlinesUrl() }}" class="mt-4 block">
                            <div class="flex items-center justify-between p-3 rounded-lg bg-danger-50 dark:bg-danger-400/10 border-2 border-danger-300 dark:border-danger-600 hover:border-danger-500 transition-colors">
                                <div class="flex items-center">
                                    <x-heroicon-o-fire class="h-5 w-5 text-danger-600 dark:text-danger-400 mr-2" />
                                    <span class="text-sm font-medium text-danger-700 dark:text-danger-300">
                                        <span class="font-bold">{{ $bottlingRiskMetrics['deadlines_14d'] }}</span> deadline{{ $bottlingRiskMetrics['deadlines_14d'] !== 1 ? 's' : '' }} in next 14 days!
                                    </span>
                                </div>
                                <x-heroicon-o-chevron-right class="h-5 w-5 text-danger-500" />
                            </div>
                        </a>
                    @else
                        <div class="mt-4 flex items-center p-3 rounded-lg bg-success-50 dark:bg-success-400/10 border border-success-200 dark:border-success-800">
                            <x-heroicon-o-check-circle class="h-5 w-5 text-success-600 dark:text-success-400 mr-2" />
                            <span class="text-sm text-success-700 dark:text-success-300">No urgent deadlines in next 14 days</span>
                        </div>
                    @endif
                </div>

                {{-- Right side: Preference Collection & Defaults --}}
                <div>
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 flex items-center">
                        <x-heroicon-o-clipboard-document-check class="h-4 w-4 mr-2 text-gray-400" />
                        Preference Collection
                    </h4>

                    {{-- Progress Bar --}}
                    @php
                        $prefStatus = $this->getBottlingRiskHealthStatus('preferences_collected_pct', $bottlingRiskMetrics['preferences_collected_pct']);
                        $prefBarColor = match($prefStatus) {
                            'success' => 'bg-success-500',
                            'warning' => 'bg-warning-500',
                            'danger' => 'bg-danger-500',
                            default => 'bg-gray-500',
                        };
                        $prefTextColor = match($prefStatus) {
                            'success' => 'text-success-600 dark:text-success-400',
                            'warning' => 'text-warning-600 dark:text-warning-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                    @endphp
                    <a href="{{ $this->getBottlingPendingPreferencesUrl() }}" class="block group">
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">% Preferences Collected</span>
                                <span class="text-lg font-bold {{ $prefTextColor }}">{{ number_format($bottlingRiskMetrics['preferences_collected_pct'], 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div class="{{ $prefBarColor }} h-3 rounded-full transition-all duration-300" style="width: {{ min($bottlingRiskMetrics['preferences_collected_pct'], 100) }}%"></div>
                            </div>
                            <div class="flex justify-between items-center mt-2 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ $bottlingRiskMetrics['preferences_collected'] }} of {{ $bottlingRiskMetrics['preferences_total'] }} collected</span>
                                @if($bottlingRiskMetrics['preferences_total'] - $bottlingRiskMetrics['preferences_collected'] > 0)
                                    <span class="text-warning-600 dark:text-warning-400">{{ $bottlingRiskMetrics['preferences_total'] - $bottlingRiskMetrics['preferences_collected'] }} pending</span>
                                @endif
                            </div>
                        </div>
                    </a>

                    {{-- Default Fallback Count --}}
                    @php
                        $defaultStatus = $this->getBottlingRiskHealthStatus('default_fallback_count', $bottlingRiskMetrics['default_fallback_count']);
                        $defaultColorBg = match($defaultStatus) {
                            'success' => 'bg-success-50 dark:bg-success-400/10',
                            'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                            'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                            default => 'bg-gray-50 dark:bg-gray-800',
                        };
                        $defaultColorText = match($defaultStatus) {
                            'success' => 'text-success-600 dark:text-success-400',
                            'warning' => 'text-warning-600 dark:text-warning-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                    @endphp
                    <a href="{{ $this->getBottlingDefaultedUrl() }}" class="mt-4 block group">
                        <div class="p-4 rounded-lg {{ $defaultColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <x-heroicon-o-arrow-path class="h-5 w-5 {{ $defaultColorText }} mr-3" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Default Fallbacks Applied</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Instructions where defaults were used</p>
                                    </div>
                                </div>
                                <span class="text-2xl font-bold {{ $defaultColorText }}">{{ $bottlingRiskMetrics['default_fallback_count'] }}</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- Legend / Help text --}}
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-success-500 mr-2"></span>
                        <span>Healthy</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-warning-500 mr-2"></span>
                        <span>Attention</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-danger-500 mr-2"></span>
                        <span>Critical</span>
                    </div>
                    <span class="text-gray-400 dark:text-gray-500">|</span>
                    <span>Click each metric to view filtered list</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Widget C: Inbound Status --}}
    @php
        $inboundStatusMetrics = $this->getInboundStatusMetrics();
    @endphp
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-inbox-arrow-down class="inline-block h-5 w-5 mr-2 -mt-0.5 text-info-500" />
                Inbound Status
            </h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">Physical arrival tracking</span>
        </div>
        <div class="fi-section-content p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Expected in Date Range --}}
                <a href="{{ $this->getExpected30dPOsUrl() }}" class="block group">
                    <div class="p-4 rounded-lg bg-info-50 dark:bg-info-400/10 border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Expected ({{ $inboundStatusMetrics['date_range_days'] }}d)</p>
                                <p class="text-2xl font-bold text-info-600 dark:text-info-400 mt-1">{{ $inboundStatusMetrics['expected_in_range'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">deliveries incoming</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-calendar-days class="h-8 w-8 text-info-600 dark:text-info-400 opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>

                {{-- Delayed --}}
                @php
                    $delayedStatus = $this->getInboundStatusHealthStatus('delayed', $inboundStatusMetrics['delayed']);
                    $delayedColorBg = match($delayedStatus) {
                        'success' => 'bg-success-50 dark:bg-success-400/10',
                        'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                        'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                        default => 'bg-gray-50 dark:bg-gray-800',
                    };
                    $delayedColorText = match($delayedStatus) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <a href="{{ $this->getDelayedPOsUrl() }}" class="block group">
                    <div class="p-4 rounded-lg {{ $delayedColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Delayed</p>
                                <p class="text-2xl font-bold {{ $delayedColorText }} mt-1">{{ $inboundStatusMetrics['delayed'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">past expected date</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-clock class="h-8 w-8 {{ $delayedColorText }} opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>

                {{-- Awaiting Serialization Routing --}}
                @php
                    $serialStatus = $this->getInboundStatusHealthStatus('awaiting_serialization_routing', $inboundStatusMetrics['awaiting_serialization_routing']);
                    $serialColorBg = match($serialStatus) {
                        'success' => 'bg-success-50 dark:bg-success-400/10',
                        'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                        'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                        default => 'bg-gray-50 dark:bg-gray-800',
                    };
                    $serialColorText = match($serialStatus) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <a href="{{ $this->getAwaitingSerializationRoutingUrl() }}" class="block group">
                    <div class="p-4 rounded-lg {{ $serialColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Awaiting Routing</p>
                                <p class="text-2xl font-bold {{ $serialColorText }} mt-1">{{ $inboundStatusMetrics['awaiting_serialization_routing'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">need serialization</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-qr-code class="h-8 w-8 {{ $serialColorText }} opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>

                {{-- Awaiting Hand-off --}}
                @php
                    $handoffStatus = $this->getInboundStatusHealthStatus('awaiting_handoff', $inboundStatusMetrics['awaiting_handoff']);
                    $handoffColorBg = match($handoffStatus) {
                        'success' => 'bg-success-50 dark:bg-success-400/10',
                        'warning' => 'bg-warning-50 dark:bg-warning-400/10',
                        'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                        default => 'bg-gray-50 dark:bg-gray-800',
                    };
                    $handoffColorText = match($handoffStatus) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <a href="{{ $this->getAwaitingHandoffUrl() }}" class="block group">
                    <div class="p-4 rounded-lg {{ $handoffColorBg }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Awaiting Hand-off</p>
                                <p class="text-2xl font-bold {{ $handoffColorText }} mt-1">{{ $inboundStatusMetrics['awaiting_handoff'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">ready for Module B</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-heroicon-o-arrow-right-circle class="h-8 w-8 {{ $handoffColorText }} opacity-50" />
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Delayed Alert (if any) --}}
            @if($inboundStatusMetrics['delayed'] > 0)
                <a href="{{ $this->getDelayedPOsUrl() }}" class="mt-4 block">
                    <div class="flex items-center justify-between p-3 rounded-lg bg-danger-50 dark:bg-danger-400/10 border-2 border-danger-300 dark:border-danger-600 hover:border-danger-500 transition-colors">
                        <div class="flex items-center">
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-danger-600 dark:text-danger-400 mr-2" />
                            <span class="text-sm font-medium text-danger-700 dark:text-danger-300">
                                <span class="font-bold">{{ $inboundStatusMetrics['delayed'] }}</span> delivery{{ $inboundStatusMetrics['delayed'] !== 1 ? 'ies' : '' }} overdue - action required!
                            </span>
                        </div>
                        <x-heroicon-o-chevron-right class="h-5 w-5 text-danger-500" />
                    </div>
                </a>
            @endif

            {{-- Legend / Help text --}}
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-success-500 mr-2"></span>
                        <span>Healthy</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-warning-500 mr-2"></span>
                        <span>Attention</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-danger-500 mr-2"></span>
                        <span>Critical</span>
                    </div>
                    <span class="text-gray-400 dark:text-gray-500">|</span>
                    <span>Click each metric to view filtered list</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Widget D: Exceptions --}}
    @php
        $exceptionMetrics = $this->getExceptionMetrics();
    @endphp
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-exclamation-triangle class="inline-block h-5 w-5 mr-2 -mt-0.5 text-danger-500" />
                Exceptions
            </h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">Items requiring attention</span>
        </div>
        <div class="fi-section-content p-4">
            @if($exceptionMetrics['has_any_exception'])
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Inbound without ownership clarity --}}
                    @php
                        $ownershipStatus = $this->getExceptionHealthStatus($exceptionMetrics['ownership_pending']);
                        $ownershipColorBg = match($ownershipStatus) {
                            'success' => 'bg-success-50 dark:bg-success-400/10',
                            'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                            default => 'bg-gray-50 dark:bg-gray-800',
                        };
                        $ownershipColorText = match($ownershipStatus) {
                            'success' => 'text-success-600 dark:text-success-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                        $ownershipBorder = $exceptionMetrics['ownership_pending'] > 0 ? 'ring-2 ring-danger-300 dark:ring-danger-600' : '';
                    @endphp
                    <a href="{{ $this->getPendingOwnershipInboundsUrl() }}" class="block group">
                        <div class="p-4 rounded-lg {{ $ownershipColorBg }} {{ $ownershipBorder }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Ownership Unclear</p>
                                    <p class="text-2xl font-bold {{ $ownershipColorText }} mt-1">{{ $exceptionMetrics['ownership_pending'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">inbounds pending</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <x-heroicon-o-question-mark-circle class="h-8 w-8 {{ $ownershipColorText }} opacity-50" />
                                </div>
                            </div>
                        </div>
                    </a>

                    {{-- Inbound blocked by missing intent --}}
                    @php
                        $unlinkedStatus = $this->getExceptionHealthStatus($exceptionMetrics['unlinked_inbounds']);
                        $unlinkedColorBg = match($unlinkedStatus) {
                            'success' => 'bg-success-50 dark:bg-success-400/10',
                            'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                            default => 'bg-gray-50 dark:bg-gray-800',
                        };
                        $unlinkedColorText = match($unlinkedStatus) {
                            'success' => 'text-success-600 dark:text-success-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                        $unlinkedBorder = $exceptionMetrics['unlinked_inbounds'] > 0 ? 'ring-2 ring-danger-300 dark:ring-danger-600' : '';
                    @endphp
                    <a href="{{ $this->getUnlinkedInboundsUrl() }}" class="block group">
                        <div class="p-4 rounded-lg {{ $unlinkedColorBg }} {{ $unlinkedBorder }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Missing Intent</p>
                                    <p class="text-2xl font-bold {{ $unlinkedColorText }} mt-1">{{ $exceptionMetrics['unlinked_inbounds'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">unlinked inbounds</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <x-heroicon-o-link-slash class="h-8 w-8 {{ $unlinkedColorText }} opacity-50" />
                                </div>
                            </div>
                        </div>
                    </a>

                    {{-- Bottling past deadline --}}
                    @php
                        $bottlingStatus = $this->getExceptionHealthStatus($exceptionMetrics['bottling_past_deadline']);
                        $bottlingColorBg = match($bottlingStatus) {
                            'success' => 'bg-success-50 dark:bg-success-400/10',
                            'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                            default => 'bg-gray-50 dark:bg-gray-800',
                        };
                        $bottlingColorText = match($bottlingStatus) {
                            'success' => 'text-success-600 dark:text-success-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                        $bottlingBorder = $exceptionMetrics['bottling_past_deadline'] > 0 ? 'ring-2 ring-danger-300 dark:ring-danger-600' : '';
                    @endphp
                    <a href="{{ $this->getBottlingPastDeadlineUrl() }}" class="block group">
                        <div class="p-4 rounded-lg {{ $bottlingColorBg }} {{ $bottlingBorder }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Past Deadline</p>
                                    <p class="text-2xl font-bold {{ $bottlingColorText }} mt-1">{{ $exceptionMetrics['bottling_past_deadline'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">bottling overdue</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <x-heroicon-o-clock class="h-8 w-8 {{ $bottlingColorText }} opacity-50" />
                                </div>
                            </div>
                        </div>
                    </a>

                    {{-- PO with delivery variance > 10% --}}
                    @php
                        $varianceStatus = $this->getExceptionHealthStatus($exceptionMetrics['variance_pos']);
                        $varianceColorBg = match($varianceStatus) {
                            'success' => 'bg-success-50 dark:bg-success-400/10',
                            'danger' => 'bg-danger-50 dark:bg-danger-400/10',
                            default => 'bg-gray-50 dark:bg-gray-800',
                        };
                        $varianceColorText = match($varianceStatus) {
                            'success' => 'text-success-600 dark:text-success-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                        $varianceBorder = $exceptionMetrics['variance_pos'] > 0 ? 'ring-2 ring-danger-300 dark:ring-danger-600' : '';
                    @endphp
                    <a href="{{ $this->getVariancePOsUrl() }}" class="block group">
                        <div class="p-4 rounded-lg {{ $varianceColorBg }} {{ $varianceBorder }} border border-transparent group-hover:border-gray-300 dark:group-hover:border-gray-600 transition-colors">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Variance &gt; 10%</p>
                                    <p class="text-2xl font-bold {{ $varianceColorText }} mt-1">{{ $exceptionMetrics['variance_pos'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">PO delivery mismatch</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <x-heroicon-o-arrow-trending-down class="h-8 w-8 {{ $varianceColorText }} opacity-50" />
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                {{-- Summary Alert when there are exceptions --}}
                @php
                    $totalExceptions = $exceptionMetrics['ownership_pending'] + $exceptionMetrics['unlinked_inbounds'] + $exceptionMetrics['bottling_past_deadline'] + $exceptionMetrics['variance_pos'];
                @endphp
                <div class="mt-4 flex items-center justify-between p-3 rounded-lg bg-danger-50 dark:bg-danger-400/10 border-2 border-danger-300 dark:border-danger-600">
                    <div class="flex items-center">
                        <x-heroicon-o-exclamation-circle class="h-5 w-5 text-danger-600 dark:text-danger-400 mr-2" />
                        <span class="text-sm font-medium text-danger-700 dark:text-danger-300">
                            <span class="font-bold">{{ $totalExceptions }}</span> total exception{{ $totalExceptions !== 1 ? 's' : '' }} require{{ $totalExceptions === 1 ? 's' : '' }} attention
                        </span>
                    </div>
                    <span class="text-xs text-danger-600 dark:text-danger-400">Click items above to resolve</span>
                </div>
            @else
                {{-- All Clear State --}}
                <div class="text-center py-8">
                    <div class="flex justify-center">
                        <x-heroicon-o-check-circle class="h-16 w-16 text-success-500" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">No Exceptions</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">All systems operating normally. No items require immediate attention.</p>
                    <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="text-center p-3 rounded-lg bg-success-50 dark:bg-success-400/10">
                            <x-heroicon-o-check class="h-5 w-5 text-success-600 dark:text-success-400 mx-auto" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ownership Clear</p>
                        </div>
                        <div class="text-center p-3 rounded-lg bg-success-50 dark:bg-success-400/10">
                            <x-heroicon-o-check class="h-5 w-5 text-success-600 dark:text-success-400 mx-auto" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">All Linked</p>
                        </div>
                        <div class="text-center p-3 rounded-lg bg-success-50 dark:bg-success-400/10">
                            <x-heroicon-o-check class="h-5 w-5 text-success-600 dark:text-success-400 mx-auto" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Deadlines OK</p>
                        </div>
                        <div class="text-center p-3 rounded-lg bg-success-50 dark:bg-success-400/10">
                            <x-heroicon-o-check class="h-5 w-5 text-success-600 dark:text-success-400 mx-auto" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Variances OK</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Legend / Help text --}}
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-success-500 mr-2"></span>
                        <span>Healthy (0 issues)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block w-3 h-3 rounded-full bg-danger-500 mr-2"></span>
                        <span>Requires Action (any count &gt; 0)</span>
                    </div>
                    <span class="text-gray-400 dark:text-gray-500">|</span>
                    <span>Click each metric to view problematic items</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid (Status Distributions) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        {{-- Intents by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-clipboard-document-list class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Intents by Status
                </h3>
                <a href="{{ $this->getIntentsListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-4">
                <div class="space-y-4">
                    @foreach($intentStatusCounts as $status => $count)
                        @php
                            $meta = $intentStatusMeta[$status];
                            $percentage = $totalIntents > 0 ? round(($count / $totalIntents) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-primary-500',
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

        {{-- POs by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-document-text class="inline-block h-5 w-5 mr-2 -mt-0.5 text-info-500" />
                    Purchase Orders by Status
                </h3>
                <a href="{{ $this->getPurchaseOrdersListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-4">
                <div class="space-y-4">
                    @foreach($poStatusCounts as $status => $count)
                        @php
                            $meta = $poStatusMeta[$status];
                            $percentage = $totalPOs > 0 ? round(($count / $totalPOs) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-primary-500',
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

    {{-- Inbounds and Bottling Metrics Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        {{-- Inbounds by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-inbox-arrow-down class="inline-block h-5 w-5 mr-2 -mt-0.5 text-success-500" />
                    Inbounds by Status
                </h3>
                <a href="{{ $this->getInboundsListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-4">
                <div class="space-y-4">
                    @foreach($inboundStatusCounts as $status => $count)
                        @php
                            $meta = $inboundStatusMeta[$status];
                            $percentage = $totalInbounds > 0 ? round(($count / $totalInbounds) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-primary-500',
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

        {{-- Bottling Deadlines --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-calendar-days class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                    Bottling Deadlines
                </h3>
                <a href="{{ $this->getBottlingInstructionsListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-4">
                <div class="grid grid-cols-2 gap-4">
                    {{-- Next 30 days --}}
                    <div class="text-center p-4 rounded-lg {{ $bottlingDeadlineCounts['next_30_days'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <p class="text-2xl font-bold {{ $bottlingDeadlineCounts['next_30_days'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">{{ $bottlingDeadlineCounts['next_30_days'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Next 30 days</p>
                    </div>
                    {{-- Next 60 days --}}
                    <div class="text-center p-4 rounded-lg {{ $bottlingDeadlineCounts['next_60_days'] > $bottlingDeadlineCounts['next_30_days'] ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <p class="text-2xl font-bold {{ $bottlingDeadlineCounts['next_60_days'] > $bottlingDeadlineCounts['next_30_days'] ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">{{ $bottlingDeadlineCounts['next_60_days'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Next 60 days</p>
                    </div>
                    {{-- Next 90 days --}}
                    <div class="text-center p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ $bottlingDeadlineCounts['next_90_days'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Next 90 days</p>
                    </div>
                    {{-- Past deadline --}}
                    <div class="text-center p-4 rounded-lg {{ $bottlingDeadlineCounts['past_deadline'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10 ring-2 ring-danger-500' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <p class="text-2xl font-bold {{ $bottlingDeadlineCounts['past_deadline'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">{{ $bottlingDeadlineCounts['past_deadline'] }}</p>
                        <p class="text-xs {{ $bottlingDeadlineCounts['past_deadline'] > 0 ? 'text-danger-600 dark:text-danger-400 font-medium' : 'text-gray-500 dark:text-gray-400' }} mt-1">Past Deadline!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-bolt class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                Quick Actions
            </h3>
        </div>
        <div class="fi-section-content p-4">
            <div class="flex flex-wrap gap-3">
                <a href="{{ $this->getCreateIntentUrl() }}" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <x-heroicon-o-plus class="h-4 w-4 mr-2" />
                    Create Procurement Intent
                </a>
                <a href="{{ $this->getCreateInboundUrl() }}" class="inline-flex items-center px-4 py-2 bg-info-600 hover:bg-info-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <x-heroicon-o-inbox-arrow-down class="h-4 w-4 mr-2" />
                    Record Inbound
                </a>
                <a href="{{ $this->getPendingApprovalsUrl() }}" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 transition-colors">
                    <x-heroicon-o-check-circle class="h-4 w-4 mr-2" />
                    View Pending Approvals
                    @if($summaryMetrics['pending_approvals'] > 0)
                        <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-warning-800 bg-warning-100 dark:text-warning-200 dark:bg-warning-800 rounded-full">{{ $summaryMetrics['pending_approvals'] }}</span>
                    @endif
                </a>
                @if($exceptionCounts['unlinked_inbounds'] > 0)
                    <a href="{{ $this->getUnlinkedInboundsUrl() }}" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-danger-700 dark:text-danger-400 text-sm font-medium rounded-lg border border-danger-300 dark:border-danger-600 transition-colors">
                        <x-heroicon-o-link-slash class="h-4 w-4 mr-2" />
                        Review {{ $exceptionCounts['unlinked_inbounds'] }} Unlinked Inbounds
                    </a>
                @endif
                @if($exceptionCounts['pending_ownership'] > 0)
                    <a href="{{ $this->getPendingOwnershipInboundsUrl() }}" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-warning-700 dark:text-warning-400 text-sm font-medium rounded-lg border border-warning-300 dark:border-warning-600 transition-colors">
                        <x-heroicon-o-exclamation-triangle class="h-4 w-4 mr-2" />
                        Clarify {{ $exceptionCounts['pending_ownership'] }} Pending Ownerships
                    </a>
                @endif
                @if($exceptionCounts['overdue_pos'] > 0)
                    <a href="{{ $this->getOverduePOsUrl() }}" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-danger-700 dark:text-danger-400 text-sm font-medium rounded-lg border border-danger-300 dark:border-danger-600 transition-colors">
                        <x-heroicon-o-clock class="h-4 w-4 mr-2" />
                        {{ $exceptionCounts['overdue_pos'] }} Overdue POs
                    </a>
                @endif
                @if($exceptionCounts['variance_pos'] > 0)
                    <a href="{{ $this->getVariancePOsUrl() }}" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-danger-700 dark:text-danger-400 text-sm font-medium rounded-lg border border-danger-300 dark:border-danger-600 transition-colors">
                        <x-heroicon-o-arrow-trending-down class="h-4 w-4 mr-2" />
                        {{ $exceptionCounts['variance_pos'] }} POs with Variance
                    </a>
                @endif
                @if($bottlingDeadlineCounts['past_deadline'] > 0)
                    <a href="{{ $this->getBottlingPastDeadlineUrl() }}" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-danger-700 dark:text-danger-400 text-sm font-medium rounded-lg border border-danger-300 dark:border-danger-600 transition-colors">
                        <x-heroicon-o-fire class="h-4 w-4 mr-2" />
                        {{ $bottlingDeadlineCounts['past_deadline'] }} Bottling Past Deadline
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Problem Areas / Awaiting Action --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Intents Awaiting Approval --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-clock class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                    Awaiting Approval
                </h3>
                @if($intentsAwaitingApproval->count() > 0)
                    <a href="{{ $this->getPendingApprovalsUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                        View All &rarr;
                    </a>
                @endif
            </div>
            <div class="fi-section-content">
                @if($intentsAwaitingApproval->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($intentsAwaitingApproval as $intent)
                            <a href="{{ $this->getIntentViewUrl($intent) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $intent->getProductLabel() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Qty: {{ $intent->quantity }} | {{ $intent->trigger_type->label() }}
                                    </p>
                                </div>
                                <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No intents awaiting approval.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Inbounds with Pending Ownership --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-exclamation-triangle class="inline-block h-5 w-5 mr-2 -mt-0.5 text-danger-500" />
                    Pending Ownership
                </h3>
                @if($inboundsWithPendingOwnership->count() > 0)
                    <a href="{{ $this->getPendingOwnershipInboundsUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                        View All &rarr;
                    </a>
                @endif
            </div>
            <div class="fi-section-content">
                @if($inboundsWithPendingOwnership->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($inboundsWithPendingOwnership as $inbound)
                            <a href="{{ $this->getInboundViewUrl($inbound) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $inbound->getProductLabel() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $inbound->warehouse }} | {{ $inbound->received_date?->format('M j') }}
                                    </p>
                                </div>
                                <div class="ml-2 flex items-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                                        Pending
                                    </span>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400 ml-2" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No ownership pending.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Urgent Bottling Deadlines --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-4 py-3 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-fire class="inline-block h-5 w-5 mr-2 -mt-0.5 text-danger-500" />
                    Urgent Deadlines (&lt;14d)
                </h3>
                @if($urgentBottlingInstructions->count() > 0)
                    <a href="{{ $this->getBottlingDeadlinesUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                        View All &rarr;
                    </a>
                @endif
            </div>
            <div class="fi-section-content">
                @if($urgentBottlingInstructions->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($urgentBottlingInstructions as $instruction)
                            @php
                                $daysRemaining = now()->diffInDays($instruction->bottling_deadline, false);
                            @endphp
                            <a href="{{ $this->getBottlingInstructionViewUrl($instruction) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $instruction->getProductLabel() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $instruction->bottling_deadline?->format('M j, Y') }}
                                    </p>
                                </div>
                                <div class="ml-2 flex items-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $daysRemaining <= 7 ? 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' : 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200' }}">
                                        {{ $daysRemaining }}d
                                    </span>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400 ml-2" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No urgent deadlines.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
