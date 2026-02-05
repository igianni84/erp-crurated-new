<x-filament-panels::page>
    @php
        $traceabilityData = $this->getTraceabilityData();
        $summary = $this->getSummary();
        $breakdownByType = $this->getBreakdownByEventType();
        $eventTypes = $this->getEventTypes();
    @endphp

    {{-- Filters Section --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            {{-- Event Type Filter --}}
            <div class="flex-1 max-w-xs">
                <label for="filterEventType" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Event Type
                </label>
                <select
                    id="filterEventType"
                    wire:model.live="filterEventType"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
                    @foreach($eventTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date From --}}
            <div class="flex-1 max-w-xs">
                <label for="dateFrom" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    From Date
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
                    To Date
                </label>
                <input
                    type="date"
                    id="dateTo"
                    wire:model.live.debounce.500ms="dateTo"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                >
            </div>

            {{-- Unmatched Only Toggle --}}
            <div class="flex items-center">
                <button
                    wire:click="toggleUnmatchedFilter"
                    type="button"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors
                        {{ $this->showUnmatchedOnly
                            ? 'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300 ring-1 ring-warning-500'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                        }}"
                >
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4 mr-2" />
                    Unmatched Only
                </button>
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
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 mb-6">
        {{-- Total Events --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Events</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['total_events'] }}</p>
            </div>
        </div>

        {{-- Complete --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Complete</p>
                <p class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">{{ $summary['complete'] }}</p>
                <p class="text-xs text-success-500">{{ $summary['completion_rate'] }}%</p>
            </div>
        </div>

        {{-- Pending Payment --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending Payment</p>
                <p class="mt-1 text-2xl font-bold text-info-600 dark:text-info-400">{{ $summary['pending_payment'] }}</p>
            </div>
        </div>

        {{-- Partial Payment --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Partial Payment</p>
                <p class="mt-1 text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $summary['partial_payment'] }}</p>
            </div>
        </div>

        {{-- Outstanding Amount --}}
        <div class="fi-section rounded-xl bg-primary-50 dark:bg-primary-900/20 shadow-sm ring-1 ring-primary-200 dark:ring-primary-800 p-4">
            <div class="text-center">
                <p class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wider">Outstanding</p>
                <p class="mt-1 text-lg font-bold text-primary-700 dark:text-primary-300">{{ $this->formatAmount($summary['total_outstanding']) }}</p>
                <p class="text-xs text-primary-500">of {{ $this->formatAmount($summary['total_invoiced']) }}</p>
            </div>
        </div>
    </div>

    {{-- Breakdown by Event Type --}}
    @if($breakdownByType->count() > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-chart-pie class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Breakdown by Event Type
                </h3>
            </div>
            <div class="fi-section-content p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                    @foreach($breakdownByType as $type)
                        <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-{{ $this->getEventTypeColor($type['event_type']) }}-100 text-{{ $this->getEventTypeColor($type['event_type']) }}-700 dark:bg-{{ $this->getEventTypeColor($type['event_type']) }}-900 dark:text-{{ $this->getEventTypeColor($type['event_type']) }}-300">
                                    {{ $type['label'] }}
                                </span>
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ $type['count'] }}</div>
                            <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex justify-between">
                                    <span>Invoiced:</span>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->formatAmount($type['invoiced']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Paid:</span>
                                    <span class="font-medium text-success-600 dark:text-success-400">{{ $this->formatAmount($type['paid']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Outstanding:</span>
                                    <span class="font-medium text-warning-600 dark:text-warning-400">{{ $this->formatAmount($type['outstanding']) }}</span>
                                </div>
                                <div class="flex justify-between pt-1 border-t border-gray-200 dark:border-gray-700 mt-1">
                                    <span>Complete:</span>
                                    <span class="font-medium text-success-600">{{ $type['complete'] }} / {{ $type['count'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Traceability Table --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-arrows-right-left class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Event to Invoice to Payment Traceability
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $traceabilityData->count() }} records
                </span>
            </div>
        </div>
        <div class="fi-section-content">
            @if($traceabilityData->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Event
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Invoice
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Amount
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Payments
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($traceabilityData as $record)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    {{-- Event Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-{{ $this->getEventTypeColor($record['source_type']) }}-100 text-{{ $this->getEventTypeColor($record['source_type']) }}-700 dark:bg-{{ $this->getEventTypeColor($record['source_type']) }}-900 dark:text-{{ $this->getEventTypeColor($record['source_type']) }}-300">
                                                {{ $this->getEventTypeLabel($record['source_type']) }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            ID: {{ Str::limit($record['source_id'], 20) }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $record['event_date'] }}
                                        </div>
                                    </td>

                                    {{-- Invoice Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if($record['has_invoice'])
                                            <a href="{{ $this->getInvoiceUrl($record['invoice_id']) }}"
                                               class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 font-medium">
                                                {{ $record['invoice_number'] ?? 'Draft' }}
                                            </a>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-{{ $record['invoice_type']->color() }}-100 text-{{ $record['invoice_type']->color() }}-700 dark:bg-{{ $record['invoice_type']->color() }}-900 dark:text-{{ $record['invoice_type']->color() }}-300">
                                                    {{ $record['invoice_type']->code() }}
                                                </span>
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-{{ $record['invoice_status']->color() }}-100 text-{{ $record['invoice_status']->color() }}-700 dark:bg-{{ $record['invoice_status']->color() }}-900 dark:text-{{ $record['invoice_status']->color() }}-300">
                                                    {{ $record['invoice_status']->label() }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-danger-600 dark:text-danger-400 font-medium">
                                                <x-heroicon-o-x-circle class="inline-block h-4 w-4 mr-1 -mt-0.5" />
                                                No Invoice
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Customer Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <a href="{{ $this->getCustomerFinanceUrl($record['customer_id']) }}"
                                           class="text-sm text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                            {{ Str::limit($record['customer_name'], 25) }}
                                        </a>
                                    </td>

                                    {{-- Amount Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap text-right">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatAmount($record['invoice_amount'], $record['currency']) }}
                                        </div>
                                        @if(bccomp($record['outstanding'], '0', 2) > 0)
                                            <div class="text-xs text-warning-600 dark:text-warning-400">
                                                Outstanding: {{ $this->formatAmount($record['outstanding'], $record['currency']) }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Payments Column --}}
                                    <td class="px-4 py-4">
                                        @if(count($record['payment_references']) > 0)
                                            <div class="space-y-1">
                                                @foreach(array_slice($record['payment_references'], 0, 2) as $payment)
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                                            {{ $payment['source'] }}
                                                        </span>
                                                        <span class="text-success-600 dark:text-success-400 font-medium">
                                                            {{ $record['currency'] }} {{ number_format((float)$payment['amount'], 2) }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                                @if(count($record['payment_references']) > 2)
                                                    <div class="text-xs text-gray-400">
                                                        +{{ count($record['payment_references']) - 2 }} more
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">No payments</span>
                                        @endif
                                    </td>

                                    {{-- Status Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-{{ $this->getStatusColor($record['traceability_status']) }}-100 text-{{ $this->getStatusColor($record['traceability_status']) }}-700 dark:bg-{{ $this->getStatusColor($record['traceability_status']) }}-900 dark:text-{{ $this->getStatusColor($record['traceability_status']) }}-300">
                                            @php
                                                $statusIcon = $this->getStatusIcon($record['traceability_status']);
                                            @endphp
                                            <x-dynamic-component :component="$statusIcon" class="h-3.5 w-3.5" />
                                            {{ $this->getStatusLabel($record['traceability_status']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <x-heroicon-o-document-check class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No events found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($this->showUnmatchedOnly)
                            All events in the selected date range have complete traceability.
                        @else
                            No events match the selected filters.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-start">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 mt-0.5 text-gray-400 flex-shrink-0" />
            <span>
                <strong>Event-to-Invoice Traceability</strong> shows the complete audit trail from ERP events (sales, shipments, storage billing)
                through to invoices and payments. Use the "Unmatched Only" filter to identify events that haven't completed the full financial cycle.
                <br><br>
                <strong>Status meanings:</strong>
                <span class="text-success-600">Complete</span> = Event → Invoice → Full Payment |
                <span class="text-warning-600">Partial Payment</span> = Invoice has some payments but not fully paid |
                <span class="text-info-600">Pending Payment</span> = Invoice exists but no payments received |
                <span class="text-danger-600">No Invoice</span> = Event exists but no invoice generated
            </span>
        </p>
    </div>
</x-filament-panels::page>
