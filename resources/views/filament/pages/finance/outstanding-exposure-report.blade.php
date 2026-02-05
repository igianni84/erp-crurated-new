<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $customerData = $this->getOutstandingByCustomer();
        $typeData = $this->getOutstandingByType();
        $trendData = $this->getOutstandingTrend();
        $chartData = $this->getChartData();
    @endphp

    {{-- Filters Section --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            {{-- Trend Period --}}
            <div class="flex-1 max-w-xs">
                <label for="trendMonths" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Trend Period
                </label>
                <select
                    id="trendMonths"
                    wire:model.live="trendMonths"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
                    @foreach($this->getTrendMonthOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Top Customers Count --}}
            <div class="flex-1 max-w-xs">
                <label for="topCustomersCount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Show Customers
                </label>
                <select
                    id="topCustomersCount"
                    wire:model.live="topCustomersCount"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
                    @foreach($this->getTopCustomersOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Outstanding --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-warning-100 dark:bg-warning-900/30 rounded-lg">
                    <x-heroicon-o-currency-euro class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Outstanding</p>
                    <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($summary['total_outstanding']) }}</p>
                    <p class="text-xs text-gray-500">{{ $summary['invoice_count'] }} invoices</p>
                </div>
            </div>
        </div>

        {{-- Total Overdue --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-danger-100 dark:bg-danger-900/30 rounded-lg">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Overdue</p>
                    <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $this->formatAmount($summary['total_overdue']) }}</p>
                    <p class="text-xs text-gray-500">{{ number_format($summary['overdue_percentage'], 1) }}% of outstanding</p>
                </div>
            </div>
        </div>

        {{-- Customers with Outstanding --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-info-100 dark:bg-info-900/30 rounded-lg">
                    <x-heroicon-o-users class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Customers</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['customer_count'] }}</p>
                    <p class="text-xs text-gray-500">with outstanding balance</p>
                </div>
            </div>
        </div>

        {{-- Average per Customer --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <x-heroicon-o-calculator class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg per Customer</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->formatAmount($summary['average_per_customer']) }}</p>
                    <p class="text-xs text-gray-500">average exposure</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Trend Chart Section --}}
    @if($trendData->count() > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Outstanding Trend Over Time</h3>

            {{-- Simple Bar Chart (CSS-based) --}}
            <div class="space-y-4">
                @php
                    $maxOutstanding = max(array_map(fn($row) => (float) $row['outstanding'], $trendData->toArray()));
                @endphp

                @foreach($trendData as $row)
                    @php
                        $outstandingPercentage = $maxOutstanding > 0 ? ((float) $row['outstanding'] / $maxOutstanding) * 100 : 0;
                        $overduePercentage = (float) $row['outstanding'] > 0
                            ? ((float) $row['overdue'] / (float) $row['outstanding']) * $outstandingPercentage
                            : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $row['month_label'] }}</span>
                            <div class="text-right">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->formatAmount($row['outstanding']) }}</span>
                                @if(bccomp($row['overdue'], '0', 2) > 0)
                                    <span class="text-xs text-danger-600 dark:text-danger-400 ml-2">({{ $this->formatAmount($row['overdue']) }} overdue)</span>
                                @endif
                            </div>
                        </div>
                        <div class="w-full h-6 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden relative">
                            {{-- Outstanding bar --}}
                            <div class="absolute top-0 left-0 h-full bg-warning-500 transition-all duration-500 rounded-full"
                                 style="width: {{ max($outstandingPercentage, 0.5) }}%"></div>
                            {{-- Overdue overlay --}}
                            @if($overduePercentage > 0)
                                <div class="absolute top-0 left-0 h-full bg-danger-500 transition-all duration-500 rounded-full"
                                     style="width: {{ $overduePercentage }}%"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <span class="w-3 h-3 rounded-full bg-warning-500 mr-2"></span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">Outstanding</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 rounded-full bg-danger-500 mr-2"></span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">Overdue</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Two Column Layout for Tables --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Outstanding by Customer --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <x-heroicon-o-users class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                        Top Customers by Outstanding
                    </h3>
                </div>
            </div>
            <div class="fi-section-content">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Outstanding
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Overdue
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($customerData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ $this->getCustomerFinanceUrl($row['customer_id']) }}" class="group">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                                {{ $row['customer_name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $row['invoice_count'] }} invoices ({{ number_format($row['percentage'], 1) }}%)
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-warning-600 dark:text-warning-400">
                                        {{ $this->formatAmount($row['outstanding']) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                        @if(bccomp($row['overdue'], '0', 2) > 0)
                                            <span class="text-danger-600 dark:text-danger-400 font-semibold">{{ $this->formatAmount($row['overdue']) }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No outstanding invoices found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Outstanding by Invoice Type --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <x-heroicon-o-document-text class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                        Outstanding by Invoice Type
                    </h3>
                </div>
            </div>
            <div class="fi-section-content">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Type
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Outstanding
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Overdue
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($typeData as $row)
                                @php
                                    $textColorClass = match($row['color']) {
                                        'primary' => 'text-primary-600 dark:text-primary-400',
                                        'success' => 'text-success-600 dark:text-success-400',
                                        'info' => 'text-info-600 dark:text-info-400',
                                        'warning' => 'text-warning-600 dark:text-warning-400',
                                        'danger' => 'text-danger-600 dark:text-danger-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };
                                    $bgColorClass = match($row['color']) {
                                        'primary' => 'bg-primary-100 dark:bg-primary-900/30',
                                        'success' => 'bg-success-100 dark:bg-success-900/30',
                                        'info' => 'bg-info-100 dark:bg-info-900/30',
                                        'warning' => 'bg-warning-100 dark:bg-warning-900/30',
                                        'danger' => 'bg-danger-100 dark:bg-danger-900/30',
                                        default => 'bg-gray-100 dark:bg-gray-900/30',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-lg {{ $bgColorClass }} flex items-center justify-center">
                                                <span class="font-bold text-xs {{ $textColorClass }}">{{ $row['code'] }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $row['label'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row['invoice_count'] }} invoices ({{ number_format($row['percentage'], 1) }}%)
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-warning-600 dark:text-warning-400">
                                        {{ bccomp($row['outstanding'], '0', 2) > 0 ? $this->formatAmount($row['outstanding']) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                        @if(bccomp($row['overdue'], '0', 2) > 0)
                                            <span class="text-danger-600 dark:text-danger-400 font-semibold">{{ $this->formatAmount($row['overdue']) }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                    TOTAL
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold text-warning-600 dark:text-warning-400">
                                    {{ $this->formatAmount($summary['total_outstanding']) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold text-danger-600 dark:text-danger-400">
                                    {{ $this->formatAmount($summary['total_overdue']) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-center">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 text-gray-400" />
            <span>
                <strong>Outstanding Exposure</strong> shows the total amount owed by customers that has not yet been paid.
                Overdue amounts are invoices past their due date. Click on a customer to view their detailed financial profile.
            </span>
        </p>
    </div>
</x-filament-panels::page>
