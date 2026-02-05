<x-filament-panels::page>
    @php
        $agingData = $this->getAgingData();
        $summary = $this->getAgingSummary();
        $bucketLabels = $this->getAgingBucketLabels();
        $bucketPercentages = $this->getBucketPercentages();
        $selectedCustomer = $this->getSelectedCustomer();
    @endphp

    {{-- Filters Section --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            {{-- Report Date --}}
            <div class="flex-1 max-w-xs">
                <label for="reportDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Report Date
                </label>
                <input
                    type="date"
                    id="reportDate"
                    wire:model.live.debounce.500ms="reportDate"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>

            {{-- Customer Filter --}}
            <div class="flex-1 max-w-md relative">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Filter by Customer
                </label>
                @if($selectedCustomer)
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                        <span class="text-sm text-gray-900 dark:text-white flex-1">{{ $selectedCustomer->name }}</span>
                        <button
                            wire:click="clearCustomerFilter"
                            type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    </div>
                @else
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="customerSearch"
                        placeholder="Search customer..."
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                    >
                    @if(strlen($this->customerSearch) >= 2)
                        @php $filteredCustomers = $this->getFilteredCustomers(); @endphp
                        @if($filteredCustomers->count() > 0)
                            <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto">
                                @foreach($filteredCustomers as $customer)
                                    <button
                                        wire:click="selectCustomer('{{ $customer->id }}')"
                                        type="button"
                                        class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-900 dark:text-white"
                                    >
                                        {{ $customer->name }}
                                        <span class="text-gray-400 text-xs ml-2">{{ $customer->email }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endif
            </div>

            {{-- Export Button --}}
            <div>
                <button
                    wire:click="exportToCsv"
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                >
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4 mr-2" />
                    Export CSV
                </button>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-6">
        {{-- Current --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $bucketLabels['current'] }}</p>
                <p class="mt-1 text-lg font-semibold text-success-600 dark:text-success-400">{{ $this->formatAmount($summary['current']) }}</p>
                <p class="text-xs text-gray-400">{{ $bucketPercentages['current'] }}%</p>
            </div>
        </div>

        {{-- 1-30 Days --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $bucketLabels['days_1_30'] }}</p>
                <p class="mt-1 text-lg font-semibold text-info-600 dark:text-info-400">{{ $this->formatAmount($summary['days_1_30']) }}</p>
                <p class="text-xs text-gray-400">{{ $bucketPercentages['days_1_30'] }}%</p>
            </div>
        </div>

        {{-- 31-60 Days --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $bucketLabels['days_31_60'] }}</p>
                <p class="mt-1 text-lg font-semibold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($summary['days_31_60']) }}</p>
                <p class="text-xs text-gray-400">{{ $bucketPercentages['days_31_60'] }}%</p>
            </div>
        </div>

        {{-- 61-90 Days --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $bucketLabels['days_61_90'] }}</p>
                <p class="mt-1 text-lg font-semibold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($summary['days_61_90']) }}</p>
                <p class="text-xs text-gray-400">{{ $bucketPercentages['days_61_90'] }}%</p>
            </div>
        </div>

        {{-- 90+ Days --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $bucketLabels['days_90_plus'] }}</p>
                <p class="mt-1 text-lg font-semibold text-danger-600 dark:text-danger-400">{{ $this->formatAmount($summary['days_90_plus']) }}</p>
                <p class="text-xs text-gray-400">{{ $bucketPercentages['days_90_plus'] }}%</p>
            </div>
        </div>

        {{-- Total Outstanding --}}
        <div class="fi-section rounded-xl bg-primary-50 dark:bg-primary-900/20 shadow-sm ring-1 ring-primary-200 dark:ring-primary-800 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wider">Total</p>
                <p class="mt-1 text-lg font-bold text-primary-700 dark:text-primary-300">{{ $this->formatAmount($summary['total']) }}</p>
                <p class="text-xs text-primary-500">{{ $summary['customer_count'] }} customers</p>
            </div>
        </div>
    </div>

    {{-- Aging Breakdown Bar (Visual) --}}
    @if(bccomp($summary['total'], '0', 2) > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
            <div class="flex items-center gap-1 h-6 rounded-lg overflow-hidden">
                @if($bucketPercentages['current'] > 0)
                    <div class="h-full bg-success-500 dark:bg-success-600 transition-all" style="width: {{ $bucketPercentages['current'] }}%"
                         title="{{ $bucketLabels['current'] }}: {{ $bucketPercentages['current'] }}%"></div>
                @endif
                @if($bucketPercentages['days_1_30'] > 0)
                    <div class="h-full bg-info-500 dark:bg-info-600 transition-all" style="width: {{ $bucketPercentages['days_1_30'] }}%"
                         title="{{ $bucketLabels['days_1_30'] }}: {{ $bucketPercentages['days_1_30'] }}%"></div>
                @endif
                @if($bucketPercentages['days_31_60'] > 0)
                    <div class="h-full bg-warning-400 dark:bg-warning-500 transition-all" style="width: {{ $bucketPercentages['days_31_60'] }}%"
                         title="{{ $bucketLabels['days_31_60'] }}: {{ $bucketPercentages['days_31_60'] }}%"></div>
                @endif
                @if($bucketPercentages['days_61_90'] > 0)
                    <div class="h-full bg-warning-600 dark:bg-warning-700 transition-all" style="width: {{ $bucketPercentages['days_61_90'] }}%"
                         title="{{ $bucketLabels['days_61_90'] }}: {{ $bucketPercentages['days_61_90'] }}%"></div>
                @endif
                @if($bucketPercentages['days_90_plus'] > 0)
                    <div class="h-full bg-danger-500 dark:bg-danger-600 transition-all" style="width: {{ $bucketPercentages['days_90_plus'] }}%"
                         title="{{ $bucketLabels['days_90_plus'] }}: {{ $bucketPercentages['days_90_plus'] }}%"></div>
                @endif
            </div>
            <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-success-500"></span> Current
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-info-500"></span> 1-30
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-warning-400"></span> 31-60
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-warning-600"></span> 61-90
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-danger-500"></span> 90+
                </span>
            </div>
        </div>
    @endif

    {{-- Customer Breakdown Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-user-group class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Aging by Customer
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $summary['invoice_count'] }} invoices across {{ $summary['customer_count'] }} customers
                </span>
            </div>
        </div>
        <div class="fi-section-content">
            @if($agingData->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ $bucketLabels['current'] }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ $bucketLabels['days_1_30'] }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ $bucketLabels['days_31_60'] }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ $bucketLabels['days_61_90'] }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ $bucketLabels['days_90_plus'] }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($agingData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ $this->getCustomerFinanceUrl($row['customer_id']) }}"
                                           class="flex items-center group">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                                <span class="text-primary-600 dark:text-primary-400 font-medium text-sm">{{ substr($row['customer_name'], 0, 2) }}</span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                                    {{ $row['customer_name'] }}
                                                </div>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ bccomp($row['current'], '0', 2) > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-400' }}">
                                        {{ bccomp($row['current'], '0', 2) > 0 ? $this->formatAmount($row['current']) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ bccomp($row['days_1_30'], '0', 2) > 0 ? 'text-info-600 dark:text-info-400' : 'text-gray-400' }}">
                                        {{ bccomp($row['days_1_30'], '0', 2) > 0 ? $this->formatAmount($row['days_1_30']) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ bccomp($row['days_31_60'], '0', 2) > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">
                                        {{ bccomp($row['days_31_60'], '0', 2) > 0 ? $this->formatAmount($row['days_31_60']) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ bccomp($row['days_61_90'], '0', 2) > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">
                                        {{ bccomp($row['days_61_90'], '0', 2) > 0 ? $this->formatAmount($row['days_61_90']) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ bccomp($row['days_90_plus'], '0', 2) > 0 ? 'text-danger-600 dark:text-danger-400 font-semibold' : 'text-gray-400' }}">
                                        {{ bccomp($row['days_90_plus'], '0', 2) > 0 ? $this->formatAmount($row['days_90_plus']) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $this->formatAmount($row['total']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                    TOTAL
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-success-600 dark:text-success-400">
                                    {{ $this->formatAmount($summary['current']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-info-600 dark:text-info-400">
                                    {{ $this->formatAmount($summary['days_1_30']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-warning-600 dark:text-warning-400">
                                    {{ $this->formatAmount($summary['days_31_60']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-warning-600 dark:text-warning-400">
                                    {{ $this->formatAmount($summary['days_61_90']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-danger-600 dark:text-danger-400">
                                    {{ $this->formatAmount($summary['days_90_plus']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-primary-600 dark:text-primary-400">
                                    {{ $this->formatAmount($summary['total']) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <x-heroicon-o-document-check class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No outstanding invoices</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($selectedCustomer)
                            {{ $selectedCustomer->name }} has no open invoices.
                        @else
                            There are no open invoices to show in the aging report.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-center">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 text-gray-400" />
            <span>
                <strong>Invoice Aging</strong> shows outstanding amounts grouped by how long they have been overdue.
                Click on a customer name to view their full financial details.
            </span>
        </p>
    </div>
</x-filament-panels::page>
