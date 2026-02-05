<x-filament-panels::page>
    @php
        $revenueData = $this->getRevenueData();
        $summary = $this->getSummary();
        $chartData = $this->getChartData();
        $typePercentages = $this->getTypePercentages();
        $periodLabel = $this->getPeriodLabel();
    @endphp

    {{-- Filters Section --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            {{-- Period Type --}}
            <div class="flex-1 max-w-xs">
                <label for="periodType" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Period Type
                </label>
                <select
                    id="periodType"
                    wire:model.live="periodType"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>

            {{-- Year --}}
            <div class="flex-1 max-w-xs">
                <label for="selectedYear" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Year
                </label>
                <select
                    id="selectedYear"
                    wire:model.live="selectedYear"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
                    @foreach($this->getAvailableYears() as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Month (shown only when monthly) --}}
            @if($this->periodType === 'monthly')
                <div class="flex-1 max-w-xs">
                    <label for="selectedMonth" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Month
                    </label>
                    <select
                        id="selectedMonth"
                        wire:model.live="selectedMonth"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                    >
                        @foreach($this->getAvailableMonths() as $monthNum => $monthName)
                            <option value="{{ $monthNum }}">{{ $monthName }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Quarter (shown only when quarterly) --}}
            @if($this->periodType === 'quarterly')
                <div class="flex-1 max-w-xs">
                    <label for="selectedQuarter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Quarter
                    </label>
                    <select
                        id="selectedQuarter"
                        wire:model.live="selectedQuarter"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                    >
                        @foreach($this->getAvailableQuarters() as $quarterNum => $quarterLabel)
                            <option value="{{ $quarterNum }}">{{ $quarterLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

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

    {{-- Period Label --}}
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ $periodLabel }}
        </h2>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        {{-- Total Issued --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-info-100 dark:bg-info-900/30 rounded-lg">
                    <x-heroicon-o-document-text class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Issued</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->formatAmount($summary['total_issued_amount']) }}</p>
                    <p class="text-xs text-gray-500">{{ $summary['total_issued_count'] }} invoices</p>
                </div>
            </div>
        </div>

        {{-- Total Paid --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-success-100 dark:bg-success-900/30 rounded-lg">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Paid</p>
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $this->formatAmount($summary['total_paid_amount']) }}</p>
                    <p class="text-xs text-gray-500">{{ $summary['total_paid_count'] }} invoices</p>
                </div>
            </div>
        </div>

        {{-- Total Outstanding --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-warning-100 dark:bg-warning-900/30 rounded-lg">
                    <x-heroicon-o-clock class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Outstanding</p>
                    <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($summary['total_outstanding_amount']) }}</p>
                    <p class="text-xs text-gray-500">{{ $summary['total_outstanding_count'] }} invoices</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart Section --}}
    @if(bccomp($summary['total_issued_amount'], '0', 2) > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Revenue Distribution</h3>

            {{-- Horizontal Bar Chart (CSS-based) --}}
            <div class="space-y-4">
                @foreach($revenueData as $row)
                    @php
                        $percentage = $typePercentages[$row['code']] ?? 0;
                        $bgColorClass = match($row['color']) {
                            'primary' => 'bg-primary-500',
                            'success' => 'bg-success-500',
                            'info' => 'bg-info-500',
                            'warning' => 'bg-warning-500',
                            'danger' => 'bg-danger-500',
                            default => 'bg-gray-500',
                        };
                        $textColorClass = match($row['color']) {
                            'primary' => 'text-primary-600 dark:text-primary-400',
                            'success' => 'text-success-600 dark:text-success-400',
                            'info' => 'text-info-600 dark:text-info-400',
                            'warning' => 'text-warning-600 dark:text-warning-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center">
                                <span class="text-sm font-medium {{ $textColorClass }}">{{ $row['code'] }}</span>
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $row['label'] }}</span>
                            </div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $this->formatAmount($row['issued_amount']) }}
                                <span class="text-xs text-gray-400 ml-1">({{ number_format($percentage, 1) }}%)</span>
                            </div>
                        </div>
                        <div class="w-full h-4 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full {{ $bgColorClass }} transition-all duration-500 rounded-full"
                                 style="width: {{ max($percentage, 0.5) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                @foreach($revenueData as $row)
                    @php
                        $bgColorClass = match($row['color']) {
                            'primary' => 'bg-primary-500',
                            'success' => 'bg-success-500',
                            'info' => 'bg-info-500',
                            'warning' => 'bg-warning-500',
                            'danger' => 'bg-danger-500',
                            default => 'bg-gray-500',
                        };
                    @endphp
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full {{ $bgColorClass }} mr-2"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $row['code'] }}: {{ $row['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Detailed Breakdown Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-table-cells class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Breakdown by Invoice Type
                </h3>
            </div>
        </div>
        <div class="fi-section-content">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Invoice Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Issued Count
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Issued Amount
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Paid Count
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Paid Amount
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Outstanding Count
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Outstanding Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($revenueData as $row)
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-lg {{ $bgColorClass }} flex items-center justify-center">
                                            <span class="font-bold text-sm {{ $textColorClass }}">{{ $row['code'] }}</span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $row['label'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ number_format($typePercentages[$row['code']] ?? 0, 1) }}% of total
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                    {{ $row['issued_count'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $this->formatAmount($row['issued_amount']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-success-600 dark:text-success-400">
                                    {{ $row['paid_count'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-success-600 dark:text-success-400">
                                    {{ $this->formatAmount($row['paid_amount']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-warning-600 dark:text-warning-400">
                                    {{ $row['outstanding_count'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-warning-600 dark:text-warning-400">
                                    {{ bccomp($row['outstanding_amount'], '0', 2) > 0 ? $this->formatAmount($row['outstanding_amount']) : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                TOTAL
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $summary['total_issued_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900 dark:text-white">
                                {{ $this->formatAmount($summary['total_issued_amount']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-success-600 dark:text-success-400">
                                {{ $summary['total_paid_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-success-600 dark:text-success-400">
                                {{ $this->formatAmount($summary['total_paid_amount']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-warning-600 dark:text-warning-400">
                                {{ $summary['total_outstanding_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-warning-600 dark:text-warning-400">
                                {{ $this->formatAmount($summary['total_outstanding_amount']) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($summary['total_issued_count'] === 0)
                <div class="text-center py-12">
                    <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No invoices found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        No invoices were issued during {{ $periodLabel }}.
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
                <strong>Revenue by Invoice Type</strong> shows issued amounts for each invoice type (INV0-INV4) in the selected period.
                Use the period selector to view monthly, quarterly, or yearly breakdowns.
            </span>
        </p>
    </div>
</x-filament-panels::page>
