<x-filament-panels::page>
    @php
        $invoiceData = $this->getInvoicesByCurrency();
        $paymentData = $this->getPaymentsByCurrency();
        $fxData = $this->getFxGainLoss();
        $summary = $this->getSummary();
        $foreignPercentage = $this->getForeignCurrencyPercentage();
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
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Base Currency: {{ $this->baseCurrency }}
        </p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Currencies --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-primary-100 dark:bg-primary-900/30 rounded-lg">
                    <x-heroicon-o-currency-dollar class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Invoice Currencies</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['total_invoice_currencies'] }}</p>
                </div>
            </div>
        </div>

        {{-- Total Invoiced in Base --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-info-100 dark:bg-info-900/30 rounded-lg">
                    <x-heroicon-o-document-text class="h-6 w-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Invoiced ({{ $this->baseCurrency }})</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->formatAmount($summary['total_invoiced_base'], $this->baseCurrency) }}</p>
                </div>
            </div>
        </div>

        {{-- Foreign Currency Amount --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-warning-100 dark:bg-warning-900/30 rounded-lg">
                    <x-heroicon-o-globe-alt class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Foreign Currency</p>
                    <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($summary['foreign_invoice_amount'], $this->baseCurrency) }}</p>
                    <p class="text-xs text-gray-500">{{ $summary['foreign_invoice_count'] }} invoices ({{ number_format($foreignPercentage, 1) }}%)</p>
                </div>
            </div>
        </div>

        {{-- Base Currency Amount --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 p-3 bg-success-100 dark:bg-success-900/30 rounded-lg">
                    <x-heroicon-o-home class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Base Currency ({{ $this->baseCurrency }})</p>
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $this->formatAmount($summary['base_invoice_amount'], $this->baseCurrency) }}</p>
                    <p class="text-xs text-gray-500">{{ $summary['base_invoice_count'] }} invoices</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Invoices by Currency Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-document-text class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Invoices by Currency
                </h3>
            </div>
        </div>
        <div class="fi-section-content">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Currency
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Invoice Count
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Total Amount
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Total in {{ $this->baseCurrency }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                FX Rate
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($invoiceData as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full {{ $row['is_base_currency'] ? 'bg-success-100 dark:bg-success-900/30' : 'bg-warning-100 dark:bg-warning-900/30' }}">
                                            <span class="text-sm font-bold {{ $row['is_base_currency'] ? 'text-success-700 dark:text-success-400' : 'text-warning-700 dark:text-warning-400' }}">
                                                {{ $this->getCurrencySymbol($row['currency']) }}
                                            </span>
                                        </span>
                                        <span class="ml-3 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $row['currency'] }}
                                            @if($row['is_base_currency'])
                                                <span class="ml-2 text-xs text-success-600 dark:text-success-400">(Base)</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                    {{ $row['invoice_count'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $this->formatAmount($row['total_amount'], $row['currency']) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium {{ $row['is_base_currency'] ? 'text-gray-900 dark:text-white' : 'text-info-600 dark:text-info-400' }}">
                                    @if($row['is_base_currency'])
                                        {{ $this->formatAmount($row['total_in_base'], $this->baseCurrency) }}
                                    @elseif(bccomp($row['total_in_base'], '0', 2) > 0)
                                        {{ $this->formatAmount($row['total_in_base'], $this->baseCurrency) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($row['is_base_currency'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            N/A
                                        </span>
                                    @elseif($row['has_fx_rate'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-400">
                                            Recorded
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-400">
                                            Missing
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No invoices found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        No invoices were issued during {{ $periodLabel }}.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Payments by Currency Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-banknotes class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Payments by Currency
                </h3>
            </div>
        </div>
        <div class="fi-section-content">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Currency
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Payment Count
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Total Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($paymentData as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full {{ $row['is_base_currency'] ? 'bg-success-100 dark:bg-success-900/30' : 'bg-warning-100 dark:bg-warning-900/30' }}">
                                            <span class="text-sm font-bold {{ $row['is_base_currency'] ? 'text-success-700 dark:text-success-400' : 'text-warning-700 dark:text-warning-400' }}">
                                                {{ $this->getCurrencySymbol($row['currency']) }}
                                            </span>
                                        </span>
                                        <span class="ml-3 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $row['currency'] }}
                                            @if($row['is_base_currency'])
                                                <span class="ml-2 text-xs text-success-600 dark:text-success-400">(Base)</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                    {{ $row['payment_count'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $this->formatAmount($row['total_amount'], $row['currency']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center">
                                    <x-heroicon-o-banknotes class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No payments found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        No confirmed payments were received during {{ $periodLabel }}.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- FX Gain/Loss Section (if foreign currency invoices exist) --}}
    @if($fxData->isNotEmpty())
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <x-heroicon-o-scale class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                        Foreign Currency Invoice Summary
                    </h3>
                </div>
            </div>
            <div class="fi-section-content">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Currency
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Invoice Count
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Total Invoiced
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ $this->baseCurrency }} Equivalent
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($fxData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-warning-100 dark:bg-warning-900/30">
                                                <span class="text-sm font-bold text-warning-700 dark:text-warning-400">
                                                    {{ $this->getCurrencySymbol($row['currency']) }}
                                                </span>
                                            </span>
                                            <span class="ml-3 text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $row['currency'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                        {{ $row['invoice_count'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $this->formatAmount($row['total_invoiced'], $row['currency']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-info-600 dark:text-info-400">
                                        {{ $this->formatAmount($row['total_invoiced_base'], $this->baseCurrency) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- FX Impact Note --}}
                <div class="p-4 bg-info-50 dark:bg-info-900/20 border-t border-info-100 dark:border-info-800">
                    <div class="flex">
                        <x-heroicon-o-information-circle class="h-5 w-5 text-info-400 flex-shrink-0" />
                        <p class="ml-3 text-sm text-info-700 dark:text-info-300">
                            <strong>FX Impact Note:</strong> Actual FX gain/loss calculation requires comparing the FX rate at invoice issuance
                            with the effective rate at payment settlement. This report shows the base currency equivalent using recorded
                            issuance rates. For precise FX impact analysis, coordinate with Treasury for settlement rate reconciliation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-center">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 text-gray-400" />
            <span>
                <strong>FX Impact Report</strong> shows invoices and payments grouped by currency, with base currency equivalents
                calculated using recorded FX rates at issuance. Use this report to monitor foreign currency exposure and identify
                potential FX impact on revenue.
            </span>
        </p>
    </div>
</x-filament-panels::page>
