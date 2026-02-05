<x-filament-panels::page>
    @php
        $customer = $this->getSelectedCustomer();
        $balanceSummary = $this->getBalanceSummary();
    @endphp

    {{-- Customer Selection --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            @if($customer)
                {{-- Selected Customer Display --}}
                <div class="flex-1">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 rounded-full bg-primary-100 dark:bg-primary-900 p-3">
                            <x-heroicon-o-user class="h-8 w-8 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $customer->name }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $customer->email }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    @if($this->getCustomerResourceUrl())
                        <a href="{{ $this->getCustomerResourceUrl() }}" target="_blank"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 mr-2" />
                            View in Module K
                        </a>
                    @endif
                    <button wire:click="clearCustomer" type="button"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <x-heroicon-o-x-mark class="h-4 w-4 mr-2" />
                        Change Customer
                    </button>
                </div>
            @else
                {{-- Customer Search --}}
                <div class="flex-1 relative">
                    <label for="customerSearch" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Select Customer
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            id="customerSearch"
                            wire:model.live.debounce.300ms="customerSearch"
                            placeholder="Search by name or email..."
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm pl-10"
                        >
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-400" />
                        </div>
                    </div>

                    {{-- Autocomplete Results --}}
                    @if(strlen($this->customerSearch) >= 2)
                        @php $filteredCustomers = $this->getFilteredCustomers(); @endphp
                        @if($filteredCustomers->isNotEmpty())
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-auto">
                                @foreach($filteredCustomers as $c)
                                    <button
                                        wire:click="selectCustomer('{{ $c->id }}')"
                                        type="button"
                                        class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 focus:bg-gray-100 dark:focus:bg-gray-700"
                                    >
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $c->name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $c->email }}</div>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center text-gray-500 dark:text-gray-400">
                                No customers found
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($customer)
        {{-- Balance Summary Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            {{-- Total Outstanding --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-3">
                        <x-heroicon-o-document-text class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Outstanding</p>
                        <p class="text-2xl font-semibold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($balanceSummary['total_outstanding']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $balanceSummary['open_invoices_count'] }} open invoices</p>
                    </div>
                </div>
            </div>

            {{-- Overdue Amount --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ bccomp($balanceSummary['overdue_amount'], '0', 2) > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-3">
                        <x-heroicon-o-exclamation-triangle class="h-6 w-6 {{ bccomp($balanceSummary['overdue_amount'], '0', 2) > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue</p>
                        <p class="text-2xl font-semibold {{ bccomp($balanceSummary['overdue_amount'], '0', 2) > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">{{ $this->formatAmount($balanceSummary['overdue_amount']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $balanceSummary['overdue_invoices_count'] }} overdue invoices</p>
                    </div>
                </div>
            </div>

            {{-- Total Credits --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                        <x-heroicon-o-receipt-refund class="h-6 w-6 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credits</p>
                        <p class="text-2xl font-semibold text-info-600 dark:text-info-400">{{ $this->formatAmount($balanceSummary['total_credits']) }}</p>
                    </div>
                </div>
            </div>

            {{-- Paid YTD --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-3">
                        <x-heroicon-o-banknotes class="h-6 w-6 text-success-600 dark:text-success-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Paid (YTD)</p>
                        <p class="text-2xl font-semibold text-success-600 dark:text-success-400">{{ $this->formatAmount($balanceSummary['total_paid_ytd']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            {{-- Tab Navigation --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <button
                        wire:click="setTab('open-invoices')"
                        type="button"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $this->activeTab === 'open-invoices' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Open Invoices
                        <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium {{ $this->activeTab === 'open-invoices' ? 'bg-primary-100 text-primary-600 dark:bg-primary-900 dark:text-primary-400' : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-300' }}">
                            {{ $balanceSummary['open_invoices_count'] }}
                        </span>
                    </button>
                    <button
                        wire:click="setTab('payment-history')"
                        type="button"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $this->activeTab === 'payment-history' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Payment History
                    </button>
                    <button
                        wire:click="setTab('credits-refunds')"
                        type="button"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $this->activeTab === 'credits-refunds' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Credits & Refunds
                    </button>
                    <button
                        wire:click="setTab('exposure-limits')"
                        type="button"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $this->activeTab === 'exposure-limits' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Exposure & Limits
                    </button>
                    <button
                        wire:click="setTab('eligibility-signals')"
                        type="button"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $this->activeTab === 'eligibility-signals' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Eligibility Signals
                        @if($this->hasActiveBlocks())
                            <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-danger-100 text-danger-600 dark:bg-danger-900 dark:text-danger-400">
                                !
                            </span>
                        @endif
                    </button>
                </nav>
            </div>

            {{-- Tab Content --}}
            <div class="p-6">
                {{-- Open Invoices Tab --}}
                @if($this->activeTab === 'open-invoices')
                    @php $openInvoices = $this->getOpenInvoices(); @endphp
                    @if($openInvoices->isEmpty())
                        <div class="text-center py-8">
                            <x-heroicon-o-document-check class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No open invoices</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This customer has no outstanding invoices.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Outstanding</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($openInvoices as $invoice)
                                        <tr class="{{ $invoice->isOverdue() ? 'bg-danger-50 dark:bg-danger-900/20' : '' }}">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $invoice->invoice_number ?? 'Draft' }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: color-mix(in srgb, {{ $invoice->invoice_type->color() === 'primary' ? '#3b82f6' : ($invoice->invoice_type->color() === 'success' ? '#10b981' : ($invoice->invoice_type->color() === 'warning' ? '#f59e0b' : ($invoice->invoice_type->color() === 'danger' ? '#ef4444' : ($invoice->invoice_type->color() === 'info' ? '#06b6d4' : '#6b7280')))) }} 20%, transparent); color: {{ $invoice->invoice_type->color() === 'primary' ? '#3b82f6' : ($invoice->invoice_type->color() === 'success' ? '#10b981' : ($invoice->invoice_type->color() === 'warning' ? '#f59e0b' : ($invoice->invoice_type->color() === 'danger' ? '#ef4444' : ($invoice->invoice_type->color() === 'info' ? '#06b6d4' : '#6b7280')))) }};">
                                                    {{ $invoice->invoice_type->code() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white">
                                                {{ $this->formatAmount($invoice->total_amount, $invoice->currency) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium {{ $invoice->isOverdue() ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400' }}">
                                                {{ $this->formatAmount($this->getInvoiceOutstanding($invoice), $invoice->currency) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm {{ $invoice->isOverdue() ? 'text-danger-600 dark:text-danger-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                                {{ $invoice->due_date?->format('M j, Y') ?? '-' }}
                                                @if($invoice->isOverdue())
                                                    <span class="text-xs">({{ $invoice->getDaysOverdue() }} days overdue)</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $this->getInvoiceStatusColor($invoice) }}-100 text-{{ $this->getInvoiceStatusColor($invoice) }}-800 dark:bg-{{ $this->getInvoiceStatusColor($invoice) }}-900/50 dark:text-{{ $this->getInvoiceStatusColor($invoice) }}-400">
                                                    {{ $invoice->status->label() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                                <a href="{{ $this->getInvoiceUrl($invoice->id) }}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white text-right">Total Outstanding:</td>
                                        <td class="px-4 py-3 text-sm font-bold text-warning-600 dark:text-warning-400 text-right">{{ $this->formatAmount($balanceSummary['total_outstanding']) }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                @endif

                {{-- Payment History Tab --}}
                @if($this->activeTab === 'payment-history')
                    {{-- Date Filters --}}
                    <div class="flex flex-col sm:flex-row gap-4 mb-6">
                        <div class="flex-1">
                            <label for="paymentDateFrom" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                            <input
                                type="date"
                                id="paymentDateFrom"
                                wire:model.live.debounce.500ms="paymentDateFrom"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                            >
                        </div>
                        <div class="flex-1">
                            <label for="paymentDateTo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                            <input
                                type="date"
                                id="paymentDateTo"
                                wire:model.live.debounce.500ms="paymentDateTo"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                            >
                        </div>
                    </div>

                    @php $payments = $this->getPaymentHistory(); @endphp
                    @if($payments->isEmpty())
                        <div class="text-center py-8">
                            <x-heroicon-o-banknotes class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No payments found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No payments in the selected date range.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Source</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Applied To</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($payments as $payment)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $payment->payment_reference }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium text-success-600 dark:text-success-400">
                                                {{ $this->formatAmount($payment->amount, $payment->currency) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $payment->received_at?->format('M j, Y') ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $payment->source->color() }}-100 text-{{ $payment->source->color() }}-800 dark:bg-{{ $payment->source->color() }}-900/50 dark:text-{{ $payment->source->color() }}-400">
                                                    {{ $payment->source->label() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $this->getPaymentAppliedInvoices($payment) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                                <a href="{{ $this->getPaymentUrl($payment->id) }}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                {{-- Credits & Refunds Tab --}}
                @if($this->activeTab === 'credits-refunds')
                    @php
                        $creditNotes = $this->getCreditNotes();
                        $refunds = $this->getRefunds();
                        $creditsSummary = $this->getCreditsSummary();
                    @endphp

                    {{-- Summary --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Credit Notes Total</p>
                            <p class="text-xl font-semibold text-info-600 dark:text-info-400">{{ $this->formatAmount($creditsSummary['credit_notes_total']) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Refunds Total</p>
                            <p class="text-xl font-semibold text-info-600 dark:text-info-400">{{ $this->formatAmount($creditsSummary['refunds_total']) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Combined Total</p>
                            <p class="text-xl font-semibold text-info-600 dark:text-info-400">{{ $this->formatAmount($creditsSummary['combined_total']) }}</p>
                        </div>
                    </div>

                    {{-- Credit Notes Section --}}
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Credit Notes</h3>
                        @if($creditNotes->isEmpty())
                            <div class="text-center py-6 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <p class="text-sm text-gray-500 dark:text-gray-400">No credit notes for this customer.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Credit Note</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Issued At</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($creditNotes as $creditNote)
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $creditNote->credit_note_number ?? 'Draft' }}</span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $creditNote->invoice?->invoice_number ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium text-info-600 dark:text-info-400">
                                                    {{ $this->formatAmount($creditNote->amount, $creditNote->currency) }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $creditNote->status->color() }}-100 text-{{ $creditNote->status->color() }}-800 dark:bg-{{ $creditNote->status->color() }}-900/50 dark:text-{{ $creditNote->status->color() }}-400">
                                                        {{ $creditNote->status->label() }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $creditNote->issued_at?->format('M j, Y') ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                                    <a href="{{ $this->getCreditNoteUrl($creditNote->id) }}" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Refunds Section --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Refunds</h3>
                        @if($refunds->isEmpty())
                            <div class="text-center py-6 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <p class="text-sm text-gray-500 dark:text-gray-400">No refunds for this customer.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Processed At</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($refunds as $refund)
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                    {{ $refund->invoice?->invoice_number ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium text-info-600 dark:text-info-400">
                                                    {{ $this->formatAmount($refund->amount, $refund->currency) }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $refund->method->color() }}-100 text-{{ $refund->method->color() }}-800 dark:bg-{{ $refund->method->color() }}-900/50 dark:text-{{ $refund->method->color() }}-400">
                                                        {{ $refund->method->label() }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $refund->status->color() }}-100 text-{{ $refund->status->color() }}-800 dark:bg-{{ $refund->status->color() }}-900/50 dark:text-{{ $refund->status->color() }}-400">
                                                        {{ $refund->status->label() }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $refund->processed_at?->format('M j, Y') ?? '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Exposure & Limits Tab --}}
                @if($this->activeTab === 'exposure-limits')
                    @php
                        $exposureMetrics = $this->getExposureMetrics();
                        $trendData = $this->getExposureTrendData();
                    @endphp

                    {{-- Exposure Metrics Cards --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        {{-- Total Outstanding --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-2">
                                    <x-heroicon-o-document-text class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Outstanding</p>
                                    <p class="text-xl font-semibold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($exposureMetrics['total_outstanding']) }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Overdue Amount --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 rounded-lg {{ bccomp($exposureMetrics['overdue_amount'], '0', 2) > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-2">
                                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 {{ bccomp($exposureMetrics['overdue_amount'], '0', 2) > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Overdue Amount</p>
                                    <p class="text-xl font-semibold {{ bccomp($exposureMetrics['overdue_amount'], '0', 2) > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">{{ $this->formatAmount($exposureMetrics['overdue_amount']) }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Credit Limit --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-2">
                                    <x-heroicon-o-shield-check class="h-5 w-5 text-info-600 dark:text-info-400" />
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Credit Limit</p>
                                    @if($exposureMetrics['credit_limit'] !== null)
                                        <p class="text-xl font-semibold text-info-600 dark:text-info-400">{{ $this->formatAmount($exposureMetrics['credit_limit']) }}</p>
                                    @else
                                        <p class="text-xl font-semibold text-gray-400 dark:text-gray-500">Not Set</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Available Credit --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 rounded-lg {{ $exposureMetrics['is_over_limit'] ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-2">
                                    <x-heroicon-o-banknotes class="h-5 w-5 {{ $exposureMetrics['is_over_limit'] ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Available Credit</p>
                                    @if($exposureMetrics['available_credit'] !== null)
                                        <p class="text-xl font-semibold {{ $exposureMetrics['is_over_limit'] ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">{{ $this->formatAmount($exposureMetrics['available_credit']) }}</p>
                                    @else
                                        <p class="text-xl font-semibold text-gray-400 dark:text-gray-500">N/A</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Exposure Percentage Bar (if credit limit is set) --}}
                    @if($exposureMetrics['credit_limit'] !== null && $exposureMetrics['exposure_percentage'] !== null)
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 mb-6">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Credit Utilization</span>
                                <span class="text-sm font-medium {{ $exposureMetrics['is_over_limit'] ? 'text-danger-600 dark:text-danger-400' : ($exposureMetrics['exposure_percentage'] > 80 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400') }}">
                                    {{ number_format($exposureMetrics['exposure_percentage'], 1) }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div
                                    class="h-3 rounded-full {{ $exposureMetrics['is_over_limit'] ? 'bg-danger-500' : ($exposureMetrics['exposure_percentage'] > 80 ? 'bg-warning-500' : 'bg-success-500') }}"
                                    style="width: {{ min($exposureMetrics['exposure_percentage'], 100) }}%"
                                ></div>
                            </div>
                            @if($exposureMetrics['is_over_limit'])
                                <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">
                                    <x-heroicon-o-exclamation-circle class="inline h-4 w-4 mr-1" />
                                    This customer has exceeded their credit limit.
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- Exposure Trend Chart --}}
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Exposure Trend (Last 12 Months)</h3>
                        @if(empty($trendData))
                            <div class="text-center py-8">
                                <x-heroicon-o-chart-bar class="mx-auto h-12 w-12 text-gray-400" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No trend data available.</p>
                            </div>
                        @else
                            {{-- Simple bar chart using CSS --}}
                            <div class="overflow-x-auto">
                                <div class="min-w-[600px]">
                                    @php
                                        $maxOutstanding = collect($trendData)->max('outstanding');
                                        $maxPayments = collect($trendData)->max('payments');
                                        $maxValue = max((float) $maxOutstanding, (float) $maxPayments, 1);
                                    @endphp

                                    {{-- Chart Legend --}}
                                    <div class="flex items-center gap-6 mb-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded bg-warning-500"></div>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">Outstanding</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded bg-success-500"></div>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">Payments</span>
                                        </div>
                                    </div>

                                    {{-- Chart Bars --}}
                                    <div class="flex items-end justify-between gap-2 h-48 border-b border-l border-gray-300 dark:border-gray-600 pl-2 pb-2">
                                        @foreach($trendData as $data)
                                            <div class="flex-1 flex flex-col items-center gap-1">
                                                <div class="w-full flex gap-1 items-end justify-center" style="height: 160px;">
                                                    {{-- Outstanding Bar --}}
                                                    <div
                                                        class="w-3 bg-warning-500 rounded-t transition-all"
                                                        style="height: {{ $maxValue > 0 ? ((float) $data['outstanding'] / $maxValue * 100) : 0 }}%"
                                                        title="Outstanding: {{ $this->formatAmount($data['outstanding']) }}"
                                                    ></div>
                                                    {{-- Payments Bar --}}
                                                    <div
                                                        class="w-3 bg-success-500 rounded-t transition-all"
                                                        style="height: {{ $maxValue > 0 ? ((float) $data['payments'] / $maxValue * 100) : 0 }}%"
                                                        title="Payments: {{ $this->formatAmount($data['payments']) }}"
                                                    ></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Month Labels --}}
                                    <div class="flex justify-between gap-2 pl-2 pt-2">
                                        @foreach($trendData as $data)
                                            <div class="flex-1 text-center">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($data['month'], 3, '') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Data Table --}}
                            <div class="mt-6 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Month</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Outstanding</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payments</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($trendData as $data)
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $data['month'] }}</td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-warning-600 dark:text-warning-400">{{ $this->formatAmount($data['outstanding']) }}</td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-success-600 dark:text-success-400">{{ $this->formatAmount($data['payments']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Eligibility Signals Tab --}}
                @if($this->activeTab === 'eligibility-signals')
                    @php $signals = $this->getEligibilitySignals(); @endphp

                    {{-- Important Notice --}}
                    <div class="rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-heroicon-o-information-circle class="h-5 w-5 text-info-600 dark:text-info-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-info-700 dark:text-info-300">
                                    This view shows <strong>FINANCIAL eligibility</strong> only. For full eligibility status including membership, KYC, and other criteria, see Module K (Customer Management).
                                </p>
                            </div>
                        </div>
                    </div>

                    @if(empty($signals))
                        <div class="text-center py-8">
                            <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Active Blocks</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This customer has no financial eligibility blocks.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($signals as $signal)
                                <div class="rounded-lg border {{ $signal['severity'] === 'danger' ? 'border-danger-200 dark:border-danger-800 bg-danger-50 dark:bg-danger-900/20' : 'border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-900/20' }} p-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            @if($signal['severity'] === 'danger')
                                                <x-heroicon-o-x-circle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                                            @else
                                                <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                                            @endif
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <h3 class="text-sm font-semibold {{ $signal['severity'] === 'danger' ? 'text-danger-800 dark:text-danger-200' : 'text-warning-800 dark:text-warning-200' }}">
                                                {{ $signal['label'] }}
                                            </h3>
                                            <div class="mt-2 text-sm {{ $signal['severity'] === 'danger' ? 'text-danger-700 dark:text-danger-300' : 'text-warning-700 dark:text-warning-300' }}">
                                                <p><strong>Reason:</strong> {{ $signal['reason'] }}</p>
                                                @if($signal['invoice_number'])
                                                    <p class="mt-1">
                                                        <strong>Invoice Reference:</strong>
                                                        <a href="{{ $this->getInvoiceUrl($signal['invoice_id']) }}" class="underline hover:no-underline">
                                                            {{ $signal['invoice_number'] }}
                                                        </a>
                                                    </p>
                                                @endif
                                                <p class="mt-1"><strong>How to Resolve:</strong> {{ $signal['how_to_resolve'] }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Summary --}}
                        <div class="mt-6 rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Block Types Reference</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div class="flex items-start gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900/50 dark:text-danger-400">
                                        payment_blocked
                                    </span>
                                    <span class="text-gray-600 dark:text-gray-400">Membership invoice (INV0) is overdue. Blocks new purchases.</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900/50 dark:text-warning-400">
                                        custody_blocked
                                    </span>
                                    <span class="text-gray-600 dark:text-gray-400">Storage fee invoice (INV3) is overdue. Blocks item retrieval.</span>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-12 text-center">
            <x-heroicon-o-user-circle class="mx-auto h-16 w-16 text-gray-400" />
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Select a Customer</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Search for a customer above to view their financial dashboard.</p>
        </div>
    @endif
</x-filament-panels::page>
