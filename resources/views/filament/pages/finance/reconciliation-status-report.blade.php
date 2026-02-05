<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $sourceOptions = $this->getSourceOptions();
    @endphp

    {{-- Filters Section --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            {{-- Date From --}}
            <div class="flex-1 max-w-xs">
                <label for="dateFrom" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Date From
                </label>
                <input
                    type="date"
                    id="dateFrom"
                    wire:model.live.debounce.500ms="dateFrom"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>

            {{-- Date To --}}
            <div class="flex-1 max-w-xs">
                <label for="dateTo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Date To
                </label>
                <input
                    type="date"
                    id="dateTo"
                    wire:model.live.debounce.500ms="dateTo"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>

            {{-- Payment Source Filter --}}
            <div class="flex-1 max-w-xs">
                <label for="filterSource" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Payment Source
                </label>
                <select
                    id="filterSource"
                    wire:model.live="filterSource"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
                    @foreach($sourceOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        {{-- Total Payments --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Payments</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_payments']) }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->formatAmount($summary['total_amount']) }}</p>
            </div>
        </div>

        {{-- Matched --}}
        <div class="fi-section rounded-xl bg-success-50 dark:bg-success-900/20 shadow-sm ring-1 ring-success-200 dark:ring-success-800 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-success-600 dark:text-success-400 uppercase tracking-wider">
                    <x-heroicon-o-check-circle class="inline-block h-4 w-4 mr-1 -mt-0.5" />
                    Matched
                </p>
                <p class="mt-1 text-2xl font-semibold text-success-700 dark:text-success-300">{{ number_format($summary['matched_count']) }}</p>
                <p class="text-sm text-success-600 dark:text-success-400">{{ $this->formatAmount($summary['matched_amount']) }}</p>
                @if($summary['total_payments'] > 0)
                    <p class="text-xs text-success-500">{{ $summary['by_status']['matched']['percentage'] }}%</p>
                @endif
            </div>
        </div>

        {{-- Pending --}}
        <div class="fi-section rounded-xl bg-warning-50 dark:bg-warning-900/20 shadow-sm ring-1 ring-warning-200 dark:ring-warning-800 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-warning-600 dark:text-warning-400 uppercase tracking-wider">
                    <x-heroicon-o-clock class="inline-block h-4 w-4 mr-1 -mt-0.5" />
                    Pending
                </p>
                <p class="mt-1 text-2xl font-semibold text-warning-700 dark:text-warning-300">{{ number_format($summary['pending_count']) }}</p>
                <p class="text-sm text-warning-600 dark:text-warning-400">{{ $this->formatAmount($summary['pending_amount']) }}</p>
                @if($summary['total_payments'] > 0)
                    <p class="text-xs text-warning-500">{{ $summary['by_status']['pending']['percentage'] }}%</p>
                @endif
            </div>
        </div>

        {{-- Mismatched --}}
        <div class="fi-section rounded-xl bg-danger-50 dark:bg-danger-900/20 shadow-sm ring-1 ring-danger-200 dark:ring-danger-800 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-danger-600 dark:text-danger-400 uppercase tracking-wider">
                    <x-heroicon-o-exclamation-triangle class="inline-block h-4 w-4 mr-1 -mt-0.5" />
                    Mismatched
                </p>
                <p class="mt-1 text-2xl font-semibold text-danger-700 dark:text-danger-300">{{ number_format($summary['mismatched_count']) }}</p>
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $this->formatAmount($summary['mismatched_amount']) }}</p>
                @if($summary['total_payments'] > 0)
                    <p class="text-xs text-danger-500">{{ $summary['by_status']['mismatched']['percentage'] }}%</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Status Distribution Bar --}}
    @if($summary['total_payments'] > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
            <div class="flex items-center gap-1 h-6 rounded-lg overflow-hidden">
                @if($summary['by_status']['matched']['percentage'] > 0)
                    <div class="h-full bg-success-500 dark:bg-success-600 transition-all" style="width: {{ $summary['by_status']['matched']['percentage'] }}%"
                         title="Matched: {{ $summary['by_status']['matched']['percentage'] }}%"></div>
                @endif
                @if($summary['by_status']['pending']['percentage'] > 0)
                    <div class="h-full bg-warning-500 dark:bg-warning-600 transition-all" style="width: {{ $summary['by_status']['pending']['percentage'] }}%"
                         title="Pending: {{ $summary['by_status']['pending']['percentage'] }}%"></div>
                @endif
                @if($summary['by_status']['mismatched']['percentage'] > 0)
                    <div class="h-full bg-danger-500 dark:bg-danger-600 transition-all" style="width: {{ $summary['by_status']['mismatched']['percentage'] }}%"
                         title="Mismatched: {{ $summary['by_status']['mismatched']['percentage'] }}%"></div>
                @endif
            </div>
            <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-success-500"></span> Matched ({{ $summary['by_status']['matched']['percentage'] }}%)
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-warning-500"></span> Pending ({{ $summary['by_status']['pending']['percentage'] }}%)
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-danger-500"></span> Mismatched ({{ $summary['by_status']['mismatched']['percentage'] }}%)
                </span>
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200 dark:border-white/10">
            <nav class="flex -mb-px" aria-label="Tabs">
                <button
                    wire:click="setTab('summary')"
                    type="button"
                    class="px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'summary' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <x-heroicon-o-chart-pie class="inline-block h-4 w-4 mr-2 -mt-0.5" />
                    Summary
                </button>
                <button
                    wire:click="setTab('pending')"
                    type="button"
                    class="px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'pending' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <x-heroicon-o-clock class="inline-block h-4 w-4 mr-2 -mt-0.5" />
                    Pending Reconciliations
                    @if($summary['pending_count'] > 0)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200">
                            {{ $summary['pending_count'] }}
                        </span>
                    @endif
                </button>
                <button
                    wire:click="setTab('mismatched')"
                    type="button"
                    class="px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'mismatched' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <x-heroicon-o-exclamation-triangle class="inline-block h-4 w-4 mr-2 -mt-0.5" />
                    Mismatches
                    @if($summary['mismatched_count'] > 0)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                            {{ $summary['mismatched_count'] }}
                        </span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- Tab Content --}}
        <div class="p-6">
            {{-- Summary Tab --}}
            @if($activeTab === 'summary')
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reconciliation Overview</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- Matched Details --}}
                        <div class="p-4 rounded-lg border border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-900/10">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-success-100 dark:bg-success-900/30 flex items-center justify-center">
                                    <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                                </div>
                                <div>
                                    <h4 class="font-medium text-success-700 dark:text-success-300">Matched</h4>
                                    <p class="text-xs text-success-600 dark:text-success-400">Successfully reconciled</p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-success-600 dark:text-success-400">Count:</span>
                                    <span class="font-semibold text-success-700 dark:text-success-300">{{ number_format($summary['matched_count']) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-success-600 dark:text-success-400">Amount:</span>
                                    <span class="font-semibold text-success-700 dark:text-success-300">{{ $this->formatAmount($summary['matched_amount']) }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Pending Details --}}
                        <div class="p-4 rounded-lg border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-900/10">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-warning-100 dark:bg-warning-900/30 flex items-center justify-center">
                                    <x-heroicon-o-clock class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                                </div>
                                <div>
                                    <h4 class="font-medium text-warning-700 dark:text-warning-300">Pending</h4>
                                    <p class="text-xs text-warning-600 dark:text-warning-400">Awaiting reconciliation</p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-warning-600 dark:text-warning-400">Count:</span>
                                    <span class="font-semibold text-warning-700 dark:text-warning-300">{{ number_format($summary['pending_count']) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-warning-600 dark:text-warning-400">Amount:</span>
                                    <span class="font-semibold text-warning-700 dark:text-warning-300">{{ $this->formatAmount($summary['pending_amount']) }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Mismatched Details --}}
                        <div class="p-4 rounded-lg border border-danger-200 dark:border-danger-800 bg-danger-50 dark:bg-danger-900/10">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-danger-100 dark:bg-danger-900/30 flex items-center justify-center">
                                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                                </div>
                                <div>
                                    <h4 class="font-medium text-danger-700 dark:text-danger-300">Mismatched</h4>
                                    <p class="text-xs text-danger-600 dark:text-danger-400">Requires attention</p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-danger-600 dark:text-danger-400">Count:</span>
                                    <span class="font-semibold text-danger-700 dark:text-danger-300">{{ number_format($summary['mismatched_count']) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-danger-600 dark:text-danger-400">Amount:</span>
                                    <span class="font-semibold text-danger-700 dark:text-danger-300">{{ $this->formatAmount($summary['mismatched_amount']) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($summary['pending_count'] > 0 || $summary['mismatched_count'] > 0)
                        <div class="p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/10 border border-yellow-200 dark:border-yellow-800">
                            <div class="flex">
                                <x-heroicon-o-exclamation-circle class="h-5 w-5 text-yellow-400 flex-shrink-0" />
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Action Required</h4>
                                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                        There are {{ $summary['pending_count'] + $summary['mismatched_count'] }} payment(s) requiring attention.
                                        Review the Pending Reconciliations and Mismatches tabs for details.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Pending Reconciliations Tab --}}
            @if($activeTab === 'pending')
                @php $pendingPayments = $this->getPendingReconciliations(); @endphp

                @if($pendingPayments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Payment Reference
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Source
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Received
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Age
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($pendingPayments as $payment)
                                    @php
                                        $urgencyLevel = $this->getUrgencyLevel($payment);
                                        $urgencyColor = $this->getUrgencyColor($urgencyLevel);
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ $this->getPaymentUrl($payment->id) }}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                                {{ $payment->payment_reference }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $payment->getSourceColor() }}-100 text-{{ $payment->getSourceColor() }}-800 dark:bg-{{ $payment->getSourceColor() }}-900 dark:text-{{ $payment->getSourceColor() }}-200">
                                                {{ $payment->getSourceLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {{ $payment->customer?->name ?? 'Unknown' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatAmount($payment->amount, $payment->currency) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $payment->received_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $urgencyColor }}-100 text-{{ $urgencyColor }}-800 dark:bg-{{ $urgencyColor }}-900 dark:text-{{ $urgencyColor }}-200">
                                                {{ $this->getDaysSinceReceived($payment) }} days
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="{{ $this->getPaymentUrl($payment->id) }}" class="text-primary-600 dark:text-primary-400 hover:underline">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($pendingPayments->count() >= 100)
                        <div class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                            Showing first 100 pending reconciliations. Use filters to narrow down results.
                        </div>
                    @endif
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Pending Reconciliations</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            All payments within the selected date range have been reconciled.
                        </p>
                    </div>
                @endif
            @endif

            {{-- Mismatches Tab --}}
            @if($activeTab === 'mismatched')
                @php $mismatchedPayments = $this->getMismatchedPayments(); @endphp

                @if($mismatchedPayments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Payment Reference
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Source
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Mismatch Type
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Received
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Resolution Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($mismatchedPayments as $payment)
                                    @php
                                        $hasResolution = $this->hasResolutionStatus($payment);
                                        $resolutionStatus = $this->getResolutionStatus($payment);
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ $this->getPaymentUrl($payment->id) }}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                                {{ $payment->payment_reference }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $payment->getSourceColor() }}-100 text-{{ $payment->getSourceColor() }}-800 dark:bg-{{ $payment->getSourceColor() }}-900 dark:text-{{ $payment->getSourceColor() }}-200">
                                                {{ $payment->getSourceLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-danger-600 dark:text-danger-400">
                                                {{ $this->getMismatchTypeLabel($payment) }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate" title="{{ $this->getMismatchReason($payment) }}">
                                                {{ $this->getMismatchReason($payment) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatAmount($payment->amount, $payment->currency) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $payment->received_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($hasResolution)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-200">
                                                    {{ $resolutionStatus ?? 'In Progress' }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                                                    Unresolved
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="{{ $this->getPaymentUrl($payment->id) }}" class="text-primary-600 dark:text-primary-400 hover:underline">
                                                Resolve
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($mismatchedPayments->count() >= 100)
                        <div class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                            Showing first 100 mismatched payments. Use filters to narrow down results.
                        </div>
                    @endif
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Mismatches</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            No mismatched payments within the selected date range.
                        </p>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-center">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 text-gray-400" />
            <span>
                <strong>Reconciliation Status Report</strong> shows the reconciliation status of all payments.
                Pending payments await matching to invoices. Mismatched payments have issues that need manual resolution.
            </span>
        </p>
    </div>
</x-filament-panels::page>
