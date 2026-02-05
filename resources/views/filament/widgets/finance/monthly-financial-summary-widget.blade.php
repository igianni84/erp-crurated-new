<x-filament-widgets::widget>
    @php
        $currentMetrics = $this->getCurrentMonthMetrics();
        $previousMetrics = $this->getPreviousMonthMetrics();
        $invoiceTypeBreakdown = $this->getInvoiceTypeBreakdown();
        $currentMonthLabel = $this->getCurrentMonthLabel();
        $previousMonthLabel = $this->getPreviousMonthLabel();

        // Calculate changes
        $invoicesAmountChange = $this->calculateChange($currentMetrics['invoices_amount'], $previousMetrics['invoices_amount']);
        $invoicesCountChange = $this->calculateCountChange($currentMetrics['invoices_issued'], $previousMetrics['invoices_issued']);
        $paymentsAmountChange = $this->calculateChange($currentMetrics['payments_amount'], $previousMetrics['payments_amount']);
        $paymentsCountChange = $this->calculateCountChange($currentMetrics['payments_received'], $previousMetrics['payments_received']);
        $creditNotesAmountChange = $this->calculateChange($currentMetrics['credit_notes_amount'], $previousMetrics['credit_notes_amount']);
        $creditNotesCountChange = $this->calculateCountChange($currentMetrics['credit_notes'], $previousMetrics['credit_notes']);
        $refundsAmountChange = $this->calculateChange($currentMetrics['refunds_amount'], $previousMetrics['refunds_amount']);
        $refundsCountChange = $this->calculateCountChange($currentMetrics['refunds'], $previousMetrics['refunds']);
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-calendar class="h-5 w-5 text-gray-400" />
                    Monthly Financial Summary - {{ $currentMonthLabel }}
                </span>
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    vs {{ $previousMonthLabel }}
                </span>
            </div>
        </x-slot>

        {{-- Main Metrics Grid --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-6">
            {{-- Invoices Issued --}}
            <div class="rounded-xl bg-gray-50 dark:bg-white/5 ring-1 ring-gray-200 dark:ring-white/10 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex-shrink-0 p-2 bg-primary-100 dark:bg-primary-500/20 rounded-lg">
                        <x-heroicon-o-document-text class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="flex items-center gap-1 {{ $this->getChangeColorClass($invoicesAmountChange['direction']) }}">
                        <x-dynamic-component :component="$this->getChangeIcon($invoicesAmountChange['direction'])" class="h-4 w-4" />
                        <span class="text-xs font-medium">{{ number_format($invoicesAmountChange['value'], 1) }}%</span>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Invoices Issued</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $this->formatAmount($currentMetrics['invoices_amount']) }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $currentMetrics['invoices_issued'] }} invoices</span>
                        <span class="text-xs {{ $this->getChangeColorClass($invoicesCountChange['direction']) }}">
                            @if($invoicesCountChange['direction'] === 'up')
                                +{{ $previousMetrics['invoices_issued'] > 0 ? $currentMetrics['invoices_issued'] - $previousMetrics['invoices_issued'] : $currentMetrics['invoices_issued'] }}
                            @elseif($invoicesCountChange['direction'] === 'down')
                                {{ $currentMetrics['invoices_issued'] - $previousMetrics['invoices_issued'] }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Payments Received --}}
            <div class="rounded-xl bg-gray-50 dark:bg-white/5 ring-1 ring-gray-200 dark:ring-white/10 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex-shrink-0 p-2 bg-success-100 dark:bg-success-500/20 rounded-lg">
                        <x-heroicon-o-banknotes class="h-5 w-5 text-success-600 dark:text-success-400" />
                    </div>
                    <div class="flex items-center gap-1 {{ $this->getChangeColorClass($paymentsAmountChange['direction']) }}">
                        <x-dynamic-component :component="$this->getChangeIcon($paymentsAmountChange['direction'])" class="h-4 w-4" />
                        <span class="text-xs font-medium">{{ number_format($paymentsAmountChange['value'], 1) }}%</span>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Payments Received</p>
                    <p class="text-xl font-bold text-success-600 dark:text-success-400">{{ $this->formatAmount($currentMetrics['payments_amount']) }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $currentMetrics['payments_received'] }} payments</span>
                        <span class="text-xs {{ $this->getChangeColorClass($paymentsCountChange['direction']) }}">
                            @if($paymentsCountChange['direction'] === 'up')
                                +{{ $previousMetrics['payments_received'] > 0 ? $currentMetrics['payments_received'] - $previousMetrics['payments_received'] : $currentMetrics['payments_received'] }}
                            @elseif($paymentsCountChange['direction'] === 'down')
                                {{ $currentMetrics['payments_received'] - $previousMetrics['payments_received'] }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Credit Notes --}}
            <div class="rounded-xl bg-gray-50 dark:bg-white/5 ring-1 ring-gray-200 dark:ring-white/10 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex-shrink-0 p-2 bg-warning-100 dark:bg-warning-500/20 rounded-lg">
                        <x-heroicon-o-document-minus class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div class="flex items-center gap-1 {{ $this->getChangeColorClass($creditNotesAmountChange['direction'], true) }}">
                        <x-dynamic-component :component="$this->getChangeIcon($creditNotesAmountChange['direction'])" class="h-4 w-4" />
                        <span class="text-xs font-medium">{{ number_format($creditNotesAmountChange['value'], 1) }}%</span>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Credit Notes</p>
                    <p class="text-xl font-bold text-warning-600 dark:text-warning-400">{{ $this->formatAmount($currentMetrics['credit_notes_amount']) }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $currentMetrics['credit_notes'] }} notes</span>
                        <span class="text-xs {{ $this->getChangeColorClass($creditNotesCountChange['direction'], true) }}">
                            @if($creditNotesCountChange['direction'] === 'up')
                                +{{ $previousMetrics['credit_notes'] > 0 ? $currentMetrics['credit_notes'] - $previousMetrics['credit_notes'] : $currentMetrics['credit_notes'] }}
                            @elseif($creditNotesCountChange['direction'] === 'down')
                                {{ $currentMetrics['credit_notes'] - $previousMetrics['credit_notes'] }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Refunds --}}
            <div class="rounded-xl bg-gray-50 dark:bg-white/5 ring-1 ring-gray-200 dark:ring-white/10 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex-shrink-0 p-2 bg-danger-100 dark:bg-danger-500/20 rounded-lg">
                        <x-heroicon-o-arrow-uturn-left class="h-5 w-5 text-danger-600 dark:text-danger-400" />
                    </div>
                    <div class="flex items-center gap-1 {{ $this->getChangeColorClass($refundsAmountChange['direction'], true) }}">
                        <x-dynamic-component :component="$this->getChangeIcon($refundsAmountChange['direction'])" class="h-4 w-4" />
                        <span class="text-xs font-medium">{{ number_format($refundsAmountChange['value'], 1) }}%</span>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Refunds</p>
                    <p class="text-xl font-bold text-danger-600 dark:text-danger-400">{{ $this->formatAmount($currentMetrics['refunds_amount']) }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $currentMetrics['refunds'] }} refunds</span>
                        <span class="text-xs {{ $this->getChangeColorClass($refundsCountChange['direction'], true) }}">
                            @if($refundsCountChange['direction'] === 'up')
                                +{{ $previousMetrics['refunds'] > 0 ? $currentMetrics['refunds'] - $previousMetrics['refunds'] : $currentMetrics['refunds'] }}
                            @elseif($refundsCountChange['direction'] === 'down')
                                {{ $currentMetrics['refunds'] - $previousMetrics['refunds'] }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Invoice Type Breakdown --}}
        <div class="border-t border-gray-200 dark:border-white/10 pt-4">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                <x-heroicon-o-squares-2x2 class="h-4 w-4 text-gray-400" />
                Invoices by Type
            </h4>

            <div class="space-y-1">
                {{-- Header --}}
                <div class="grid grid-cols-12 gap-2 px-3 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    <div class="col-span-5">Type</div>
                    <div class="col-span-2 text-right">Count</div>
                    <div class="col-span-3 text-right">Amount</div>
                    <div class="col-span-2 text-right">vs Last</div>
                </div>

                {{-- Rows --}}
                @foreach($invoiceTypeBreakdown as $row)
                    @php
                        $amountChange = $this->calculateChange($row['amount'], $row['previous_amount']);
                        $textColorClass = match($row['color']) {
                            'primary' => 'text-primary-600 dark:text-primary-400',
                            'success' => 'text-success-600 dark:text-success-400',
                            'info' => 'text-info-600 dark:text-info-400',
                            'warning' => 'text-warning-600 dark:text-warning-400',
                            'danger' => 'text-danger-600 dark:text-danger-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                        $bgColorClass = match($row['color']) {
                            'primary' => 'bg-primary-100 dark:bg-primary-500/20',
                            'success' => 'bg-success-100 dark:bg-success-500/20',
                            'info' => 'bg-info-100 dark:bg-info-500/20',
                            'warning' => 'bg-warning-100 dark:bg-warning-500/20',
                            'danger' => 'bg-danger-100 dark:bg-danger-500/20',
                            default => 'bg-gray-100 dark:bg-white/10',
                        };
                    @endphp
                    <div class="grid grid-cols-12 gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
                        <div class="col-span-5 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded {{ $bgColorClass }} font-bold text-xs {{ $textColorClass }}">{{ $row['code'] }}</span>
                            <span class="text-sm text-gray-900 dark:text-white">{{ $row['label'] }}</span>
                        </div>
                        <div class="col-span-2 text-right text-sm text-gray-900 dark:text-white">
                            {{ $row['count'] }}
                            @if($row['previous_count'] > 0)
                                <span class="text-xs text-gray-400">({{ $row['previous_count'] }})</span>
                            @endif
                        </div>
                        <div class="col-span-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                            {{ $this->formatAmount($row['amount']) }}
                        </div>
                        <div class="col-span-2 text-right">
                            <div class="flex items-center justify-end gap-1 {{ $this->getChangeColorClass($amountChange['direction']) }}">
                                @if($amountChange['direction'] !== 'neutral')
                                    <x-dynamic-component :component="$this->getChangeIcon($amountChange['direction'])" class="h-3 w-3" />
                                    <span class="text-xs font-medium">{{ number_format($amountChange['value'], 1) }}%</span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Footer/Total --}}
                <div class="grid grid-cols-12 gap-2 px-3 py-2 rounded-lg bg-gray-100 dark:bg-white/5 mt-2">
                    <div class="col-span-5 text-sm font-semibold text-gray-900 dark:text-white">Total</div>
                    <div class="col-span-2 text-right text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $currentMetrics['invoices_issued'] }}
                    </div>
                    <div class="col-span-3 text-right text-sm font-bold text-gray-900 dark:text-white">
                        {{ $this->formatAmount($currentMetrics['invoices_amount']) }}
                    </div>
                    <div class="col-span-2 text-right">
                        <div class="flex items-center justify-end gap-1 {{ $this->getChangeColorClass($invoicesAmountChange['direction']) }}">
                            @if($invoicesAmountChange['direction'] !== 'neutral')
                                <x-dynamic-component :component="$this->getChangeIcon($invoicesAmountChange['direction'])" class="h-3 w-3" />
                                <span class="text-xs font-medium">{{ number_format($invoicesAmountChange['value'], 1) }}%</span>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Previous Month Reference --}}
        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-white/10">
            <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <x-heroicon-o-information-circle class="h-4 w-4" />
                <span>Comparison shows change from {{ $previousMonthLabel }} ({{ $previousMetrics['invoices_issued'] }} invoices / {{ $this->formatAmount($previousMetrics['invoices_amount']) }})</span>
            </p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
