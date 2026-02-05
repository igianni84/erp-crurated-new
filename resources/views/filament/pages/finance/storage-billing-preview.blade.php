<x-filament-panels::page>
    @php
        $previewData = $this->getPreviewData();
        $summary = $this->getPreviewSummary();
        $periodDays = $this->getPeriodDays();
    @endphp

    {{-- Period Selection --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="flex-1">
                <label for="periodStart" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Period Start
                </label>
                <input
                    type="date"
                    id="periodStart"
                    wire:model.live.debounce.500ms="periodStart"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>
            <div class="flex-1">
                <label for="periodEnd" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Period End
                </label>
                <input
                    type="date"
                    id="periodEnd"
                    wire:model.live.debounce.500ms="periodEnd"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input
                        type="checkbox"
                        wire:model.live="showLocationBreakdown"
                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-800"
                    >
                    Show location breakdown
                </label>
            </div>
            <div>
                <button
                    wire:click="exportPreview"
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                >
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4 mr-2" />
                    Export CSV
                </button>
            </div>
        </div>
        @if($periodDays > 0)
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Period: {{ $periodDays }} days ({{ \Carbon\Carbon::parse($this->periodStart)->format('M j, Y') }} - {{ \Carbon\Carbon::parse($this->periodEnd)->format('M j, Y') }})
            </p>
        @endif
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Customers --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-3">
                    <x-heroicon-o-user-group class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Customers</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_customers'] }}</p>
                </div>
            </div>
        </div>

        {{-- Total Bottle Days --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                    <x-heroicon-o-cube class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bottle Days</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_bottle_days']) }}</p>
                </div>
            </div>
        </div>

        {{-- Total Amount --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-3">
                    <x-heroicon-o-banknotes class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</p>
                    <p class="text-2xl font-semibold text-success-600 dark:text-success-400">{{ $this->formatAmount($summary['total_amount'], $summary['currency']) }}</p>
                </div>
            </div>
        </div>

        {{-- New Periods to Create --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg {{ $summary['new_periods'] > 0 ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-gray-50 dark:bg-gray-700' }} p-3">
                    <x-heroicon-o-document-plus class="h-6 w-6 {{ $summary['new_periods'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">New / Existing</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        <span class="{{ $summary['new_periods'] > 0 ? 'text-warning-600 dark:text-warning-400' : '' }}">{{ $summary['new_periods'] }}</span>
                        /
                        <span class="text-gray-400">{{ $summary['existing_periods'] }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Warning if existing periods --}}
    @if($summary['existing_periods'] > 0)
        <div class="rounded-lg bg-warning-50 dark:bg-warning-400/10 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-warning-800 dark:text-warning-300">
                        Existing billing periods found
                    </h3>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-400">
                        {{ $summary['existing_periods'] }} customer(s) already have billing periods for this date range.
                        These will be skipped when generating invoices.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Customer Breakdown Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-table-cells class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                Projected Invoices by Customer
            </h3>
        </div>
        <div class="fi-section-content">
            @if($previewData->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Bottles (avg)
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Bottle Days
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Unit Rate
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($previewData as $row)
                                @php
                                    $rateTier = $this->getRateTierLabel($row['bottle_count']);
                                    $hasExisting = $row['has_existing_period'];
                                    $locationBreakdown = $this->showLocationBreakdown ? $this->getCustomerLocationBreakdown($row['customer_id']) : [];
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 {{ $hasExisting ? 'opacity-60' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                                <span class="text-primary-600 dark:text-primary-400 font-medium text-sm">{{ substr($row['customer_name'], 0, 2) }}</span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $row['customer_name'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $rateTier }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                        {{ number_format($row['bottle_count']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                        {{ number_format($row['bottle_days']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                        {{ $row['currency'] }} {{ number_format((float)$row['unit_rate'], 4) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $this->formatAmount($row['calculated_amount'], $row['currency']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($hasExisting)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                <x-heroicon-o-check class="h-3 w-3 mr-1" />
                                                Exists
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400">
                                                <x-heroicon-o-clock class="h-3 w-3 mr-1" />
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                {{-- Location breakdown rows --}}
                                @if($this->showLocationBreakdown && count($locationBreakdown) > 1)
                                    @foreach($locationBreakdown as $location)
                                        <tr class="bg-gray-50/50 dark:bg-gray-800/50 {{ $hasExisting ? 'opacity-60' : '' }}">
                                            <td class="px-6 py-2 whitespace-nowrap pl-16">
                                                <div class="flex items-center">
                                                    <x-heroicon-o-map-pin class="h-4 w-4 text-gray-400 mr-2" />
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $location['location_name'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-2 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                {{ number_format($location['bottle_count']) }}
                                            </td>
                                            <td class="px-6 py-2 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                {{ number_format($location['bottle_days']) }}
                                            </td>
                                            <td class="px-6 py-2 whitespace-nowrap text-right text-sm text-gray-400">
                                                {{ $row['currency'] }} {{ number_format((float)$location['unit_rate'], 4) }}
                                            </td>
                                            <td class="px-6 py-2 whitespace-nowrap text-right text-sm text-gray-600 dark:text-gray-400">
                                                {{ $this->formatAmount($location['amount'], $row['currency']) }}
                                            </td>
                                            <td class="px-6 py-2 whitespace-nowrap text-center">
                                                {{-- Empty for location rows --}}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                    Total
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-white">
                                    -
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($summary['total_bottle_days']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                    -
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-success-600 dark:text-success-400">
                                    {{ $this->formatAmount($summary['total_amount'], $summary['currency']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    -
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <x-heroicon-o-inbox class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No storage usage</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        No customers with storage usage were found for the selected period.
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
                <strong>Bottle Days</strong> = Number of bottles x Days stored during the period.
                Rates are based on volume tiers. Click "Generate Invoices" to create billing periods and INV3 invoices.
            </span>
        </p>
    </div>
</x-filament-panels::page>
