<x-filament-panels::page>
    @php
        $summaryMetrics = $this->getSummaryMetrics();
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
    @endphp

    {{-- Dashboard Description --}}
    <div class="mb-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Control tower for Module D - Procurement & Inbound. This dashboard shows where priorities are identified. Use the links to navigate to filtered lists for action.
        </p>
    </div>

    {{-- Summary Cards Row (4 main widgets) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Active Intents --}}
        <a href="{{ $this->getIntentsListUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-3">
                        <x-heroicon-o-clipboard-document-list class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Intents</p>
                        <p class="text-2xl font-semibold text-primary-600 dark:text-primary-400">{{ $summaryMetrics['total_intents'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Pending Approvals --}}
        <a href="{{ $this->getPendingApprovalsUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ $summaryMetrics['pending_approvals'] > 0 ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-3">
                        <x-heroicon-o-clock class="h-6 w-6 {{ $summaryMetrics['pending_approvals'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Approvals</p>
                        <p class="text-2xl font-semibold {{ $summaryMetrics['pending_approvals'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}">{{ $summaryMetrics['pending_approvals'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Pending Inbounds --}}
        <a href="{{ $this->getInboundsListUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                        <x-heroicon-o-inbox-arrow-down class="h-6 w-6 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Inbounds</p>
                        <p class="text-2xl font-semibold text-info-600 dark:text-info-400">{{ $summaryMetrics['pending_inbounds'] }}</p>
                    </div>
                </div>
            </div>
        </a>

        {{-- Bottling Deadlines (30d) --}}
        <a href="{{ $this->getBottlingDeadlinesUrl() }}" class="block">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ $summaryMetrics['bottling_deadlines_30d'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-3">
                        <x-heroicon-o-calendar-days class="h-6 w-6 {{ $summaryMetrics['bottling_deadlines_30d'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Deadlines (30d)</p>
                        <p class="text-2xl font-semibold {{ $summaryMetrics['bottling_deadlines_30d'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">{{ $summaryMetrics['bottling_deadlines_30d'] }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Main Content Grid (Status Distributions) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Intents by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-clipboard-document-list class="inline-block h-5 w-5 mr-2 -mt-0.5 text-primary-500" />
                    Intents by Status
                </h3>
                <a href="{{ $this->getIntentsListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-6">
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
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-document-text class="inline-block h-5 w-5 mr-2 -mt-0.5 text-info-500" />
                    Purchase Orders by Status
                </h3>
                <a href="{{ $this->getPurchaseOrdersListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-6">
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
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Inbounds by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-inbox-arrow-down class="inline-block h-5 w-5 mr-2 -mt-0.5 text-success-500" />
                    Inbounds by Status
                </h3>
                <a href="{{ $this->getInboundsListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-6">
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
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-calendar-days class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                    Bottling Deadlines
                </h3>
                <a href="{{ $this->getBottlingInstructionsListUrl() }}" class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                    View All &rarr;
                </a>
            </div>
            <div class="fi-section-content p-6">
                <div class="grid grid-cols-2 gap-4">
                    {{-- Next 30 days --}}
                    <div class="text-center p-4 rounded-lg {{ $bottlingDeadlineCounts['next_30_days'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <p class="text-3xl font-bold {{ $bottlingDeadlineCounts['next_30_days'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">{{ $bottlingDeadlineCounts['next_30_days'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Next 30 days</p>
                    </div>
                    {{-- Next 60 days --}}
                    <div class="text-center p-4 rounded-lg {{ $bottlingDeadlineCounts['next_60_days'] > $bottlingDeadlineCounts['next_30_days'] ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <p class="text-3xl font-bold {{ $bottlingDeadlineCounts['next_60_days'] > $bottlingDeadlineCounts['next_30_days'] ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">{{ $bottlingDeadlineCounts['next_60_days'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Next 60 days</p>
                    </div>
                    {{-- Next 90 days --}}
                    <div class="text-center p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <p class="text-3xl font-bold text-gray-600 dark:text-gray-300">{{ $bottlingDeadlineCounts['next_90_days'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Next 90 days</p>
                    </div>
                    {{-- Past deadline --}}
                    <div class="text-center p-4 rounded-lg {{ $bottlingDeadlineCounts['past_deadline'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10 ring-2 ring-danger-500' : 'bg-gray-50 dark:bg-gray-800' }}">
                        <p class="text-3xl font-bold {{ $bottlingDeadlineCounts['past_deadline'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">{{ $bottlingDeadlineCounts['past_deadline'] }}</p>
                        <p class="text-xs {{ $bottlingDeadlineCounts['past_deadline'] > 0 ? 'text-danger-600 dark:text-danger-400 font-medium' : 'text-gray-500 dark:text-gray-400' }} mt-1">Past Deadline!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-bolt class="inline-block h-5 w-5 mr-2 -mt-0.5 text-warning-500" />
                Quick Actions
            </h3>
        </div>
        <div class="fi-section-content p-6">
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
            </div>
        </div>
    </div>

    {{-- Problem Areas / Awaiting Action --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Intents Awaiting Approval --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
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
                            <a href="{{ $this->getIntentViewUrl($intent) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
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
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No intents awaiting approval.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Inbounds with Pending Ownership --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
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
                            <a href="{{ $this->getInboundViewUrl($inbound) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
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
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No ownership pending.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Urgent Bottling Deadlines --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
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
                            <a href="{{ $this->getBottlingInstructionViewUrl($instruction) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
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
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All Clear!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No urgent deadlines.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
