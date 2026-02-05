<x-filament-panels::page>
    @php
        $totalOutstanding = $this->getTotalOutstanding();
        $outstandingCount = $this->getOutstandingInvoicesCount();
        $overdueAmount = $this->getOverdueAmount();
        $overdueCount = $this->getOverdueInvoicesCount();
        $paymentsThisMonth = $this->getPaymentsThisMonth();
        $paymentsCount = $this->getPaymentsThisMonthCount();
        $paymentsComparison = $this->getPaymentsComparison();
        $pendingReconciliations = $this->getPendingReconciliationsCount();
        $mismatchedReconciliations = $this->getMismatchedReconciliationsCount();
        $pendingReconciliationAmount = $this->getPendingReconciliationAmount();
        $stripeSummary = $this->getStripeHealthSummary();
        $xeroSummary = $this->getXeroHealthSummary();
        $invoicesToday = $this->getInvoicesIssuedToday();
        $paymentsToday = $this->getPaymentsReceivedToday();
        $paymentsAmountToday = $this->getPaymentsAmountToday();
        $alerts = $this->getAlerts();
    @endphp

    {{-- Alerts and Warnings Section (US-E119) --}}
    @if(count($alerts) > 0)
        <div class="space-y-3 mb-6">
            @foreach($alerts as $alert)
                <div
                    x-data="{ dismissed: false }"
                    x-show="!dismissed"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="rounded-xl border p-4 {{ $this->getAlertColorClasses($alert['color']) }}"
                    role="alert"
                >
                    <div class="flex items-start gap-3">
                        {{-- Alert Icon --}}
                        <div class="flex-shrink-0">
                            <x-dynamic-component
                                :component="$alert['icon']"
                                class="h-5 w-5 {{ $this->getAlertIconColorClass($alert['color']) }}"
                            />
                        </div>

                        {{-- Alert Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold {{ $this->getAlertTextColorClass($alert['color']) }}">
                                    {{ $alert['title'] }}
                                </h3>
                                @if($alert['count'] !== null)
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full
                                        @if($alert['color'] === 'danger') bg-danger-200 dark:bg-danger-800 text-danger-800 dark:text-danger-200
                                        @elseif($alert['color'] === 'warning') bg-warning-200 dark:bg-warning-800 text-warning-800 dark:text-warning-200
                                        @else bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200
                                        @endif
                                    ">
                                        {{ $alert['count'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm {{ $this->getAlertTextColorClass($alert['color']) }} opacity-80">
                                {{ $alert['message'] }}
                            </p>
                            @if($alert['url'])
                                <a
                                    href="{{ $alert['url'] }}"
                                    class="inline-flex items-center gap-1 mt-2 text-sm font-medium {{ $this->getAlertIconColorClass($alert['color']) }} hover:underline"
                                >
                                    View details
                                    <x-heroicon-m-arrow-right class="h-4 w-4" />
                                </a>
                            @endif
                        </div>

                        {{-- Dismiss Button --}}
                        @if($alert['dismissible'])
                            <div class="flex-shrink-0">
                                <button
                                    type="button"
                                    x-on:click="dismissed = true; $wire.dismissAlert('{{ $alert['id'] }}')"
                                    class="inline-flex items-center justify-center rounded-lg p-1.5 {{ $this->getAlertIconColorClass($alert['color']) }} hover:bg-black/5 dark:hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-{{ $alert['color'] }}-500"
                                    title="Dismiss alert"
                                >
                                    <span class="sr-only">Dismiss</span>
                                    <x-heroicon-s-x-mark class="h-4 w-4" />
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Clear All Alerts Button --}}
            @if(count($alerts) > 1)
                <div class="flex justify-end">
                    <button
                        type="button"
                        wire:click="clearDismissedAlerts"
                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:underline"
                    >
                        Reset dismissed alerts
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Main Metrics Grid --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {{-- Total Outstanding --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-3">
                    <x-heroicon-o-banknotes class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Outstanding</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->formatAmount($totalOutstanding) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $outstandingCount }} {{ Str::plural('invoice', $outstandingCount) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Overdue Amount --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg {{ $overdueCount > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-3">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 {{ $overdueCount > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue Amount</p>
                    <p class="text-2xl font-bold {{ $overdueCount > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $this->formatAmount($overdueAmount) }}</p>
                    <p class="text-xs {{ $overdueCount > 0 ? 'text-danger-500 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400' }} mt-1">
                        {{ $overdueCount }} {{ Str::plural('invoice', $overdueCount) }} overdue
                    </p>
                </div>
            </div>
        </div>

        {{-- Payments This Month --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-3">
                    <x-heroicon-o-arrow-trending-up class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Payments This Month</p>
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $this->formatAmount($paymentsThisMonth) }}</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $paymentsCount }} {{ Str::plural('payment', $paymentsCount) }}</span>
                        @if($paymentsComparison['direction'] !== 'neutral')
                            <span class="text-xs {{ $this->getChangeColorClass($paymentsComparison['direction']) }} flex items-center gap-0.5">
                                <x-dynamic-component :component="$this->getChangeIcon($paymentsComparison['direction'])" class="h-3 w-3" />
                                {{ number_format($paymentsComparison['change'], 1) }}%
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Reconciliations --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg {{ $pendingReconciliations > 0 ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-3">
                    <x-heroicon-o-document-magnifying-glass class="h-6 w-6 {{ $pendingReconciliations > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}" />
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Reconciliations</p>
                    <p class="text-2xl font-bold {{ $pendingReconciliations > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">{{ $pendingReconciliations }}</p>
                    @if($pendingReconciliations > 0)
                        <p class="text-xs text-warning-500 dark:text-warning-400 mt-1">
                            {{ $this->formatAmount($pendingReconciliationAmount) }} pending
                        </p>
                    @else
                        <p class="text-xs text-success-500 dark:text-success-400 mt-1">All reconciled</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Integration Health Section --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
        {{-- Stripe Health --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 rounded-lg bg-purple-100 dark:bg-purple-900/20 p-2">
                        <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Stripe</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Payment processing</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getStatusBadgeClasses($stripeSummary['status']) }}">
                    @if($stripeSummary['status'] === 'healthy')
                        <x-heroicon-s-check-circle class="h-3.5 w-3.5 mr-1" />
                    @elseif($stripeSummary['status'] === 'warning')
                        <x-heroicon-s-exclamation-triangle class="h-3.5 w-3.5 mr-1" />
                    @elseif($stripeSummary['status'] === 'critical')
                        <x-heroicon-s-x-circle class="h-3.5 w-3.5 mr-1" />
                    @else
                        <x-heroicon-s-question-mark-circle class="h-3.5 w-3.5 mr-1" />
                    @endif
                    {{ ucfirst($stripeSummary['status']) }}
                </span>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Last webhook</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $stripeSummary['last_webhook'] ?? 'Never' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Failed events</dt>
                    <dd class="font-medium {{ $stripeSummary['failed_count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $stripeSummary['failed_count'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Pending</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $stripeSummary['pending_count'] }}</dd>
                </div>
            </dl>
            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('filament.admin.pages.integrations-health') }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                    View details &rarr;
                </a>
            </div>
        </div>

        {{-- Xero Health --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/20 p-2">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.526.5H1.934a1.5 1.5 0 0 0-1.5 1.5v9.592a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5V2a1.5 1.5 0 0 0-1.5-1.5zm10.54 0h-9.592a1.5 1.5 0 0 0-1.5 1.5v9.592a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5V2a1.5 1.5 0 0 0-1.5-1.5zM11.526 11.408H1.934a1.5 1.5 0 0 0-1.5 1.5V22.5a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5v-9.592a1.5 1.5 0 0 0-1.5-1.5zm10.54 0h-9.592a1.5 1.5 0 0 0-1.5 1.5V22.5a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5v-9.592a1.5 1.5 0 0 0-1.5-1.5z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Xero</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Accounting sync</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getStatusBadgeClasses($xeroSummary['status']) }}">
                    @if($xeroSummary['status'] === 'healthy')
                        <x-heroicon-s-check-circle class="h-3.5 w-3.5 mr-1" />
                    @elseif($xeroSummary['status'] === 'warning')
                        <x-heroicon-s-exclamation-triangle class="h-3.5 w-3.5 mr-1" />
                    @elseif($xeroSummary['status'] === 'critical')
                        <x-heroicon-s-x-circle class="h-3.5 w-3.5 mr-1" />
                    @elseif($xeroSummary['status'] === 'disabled')
                        <x-heroicon-s-pause-circle class="h-3.5 w-3.5 mr-1" />
                    @else
                        <x-heroicon-s-question-mark-circle class="h-3.5 w-3.5 mr-1" />
                    @endif
                    {{ ucfirst($xeroSummary['status']) }}
                </span>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Last sync</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $xeroSummary['last_sync']?->diffForHumans() ?? 'Never' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Pending syncs</dt>
                    <dd class="font-medium {{ $xeroSummary['pending_count'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">{{ $xeroSummary['pending_count'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Failed syncs</dt>
                    <dd class="font-medium {{ $xeroSummary['failed_count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $xeroSummary['failed_count'] }}</dd>
                </div>
            </dl>
            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('filament.admin.pages.integrations-health') }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                    View details &rarr;
                </a>
            </div>
        </div>
    </div>

    {{-- Quick Actions (US-E117) --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <x-heroicon-o-bolt class="h-5 w-5 text-gray-400" />
            Quick Actions
        </h3>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($this->getQuickActions() as $action)
                <a href="{{ $action['url'] }}"
                   class="group relative flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-{{ $action['color'] }}-300 dark:hover:border-{{ $action['color'] }}-700 hover:bg-{{ $action['color'] }}-50 dark:hover:bg-{{ $action['color'] }}-900/10 transition-all duration-150">
                    <div class="flex-shrink-0 rounded-lg bg-{{ $action['color'] }}-50 dark:bg-{{ $action['color'] }}-400/10 p-2.5 group-hover:bg-{{ $action['color'] }}-100 dark:group-hover:bg-{{ $action['color'] }}-400/20 transition-colors">
                        <x-dynamic-component :component="$action['icon']" class="h-5 w-5 text-{{ $action['color'] }}-600 dark:text-{{ $action['color'] }}-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-{{ $action['color'] }}-700 dark:group-hover:text-{{ $action['color'] }}-300 transition-colors">
                            {{ $action['label'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $action['description'] }}
                        </p>
                    </div>
                    @if($action['badge'])
                        <span class="absolute top-2 right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-{{ $action['color'] }}-500 rounded-full">
                            {{ $action['badge'] > 99 ? '99+' : $action['badge'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- Recent Activity Feed (US-E118) --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <x-heroicon-o-clock class="h-5 w-5 text-gray-400" />
            Recent Activity
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(Last 24 hours)</span>
        </h3>

        @php
            $recentActivities = $this->getRecentActivityFeed();
        @endphp

        @if(count($recentActivities) > 0)
            <div class="flow-root">
                <ul role="list" class="-mb-8">
                    @foreach($recentActivities as $index => $activity)
                        <li>
                            <div class="relative pb-8">
                                {{-- Connecting line --}}
                                @if($index < count($recentActivities) - 1)
                                    <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                @endif

                                <div class="relative flex space-x-3">
                                    {{-- Icon --}}
                                    <div>
                                        <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-900
                                            @if($activity['icon_color'] === 'primary') bg-primary-100 dark:bg-primary-400/20
                                            @elseif($activity['icon_color'] === 'success') bg-success-100 dark:bg-success-400/20
                                            @elseif($activity['icon_color'] === 'warning') bg-warning-100 dark:bg-warning-400/20
                                            @elseif($activity['icon_color'] === 'danger') bg-danger-100 dark:bg-danger-400/20
                                            @else bg-gray-100 dark:bg-gray-700
                                            @endif
                                        ">
                                            @php
                                                $iconTextClass = match($activity['icon_color']) {
                                                    'primary' => 'text-primary-600 dark:text-primary-400',
                                                    'success' => 'text-success-600 dark:text-success-400',
                                                    'warning' => 'text-warning-600 dark:text-warning-400',
                                                    'danger' => 'text-danger-600 dark:text-danger-400',
                                                    default => 'text-gray-600 dark:text-gray-400',
                                                };
                                            @endphp
                                            <x-dynamic-component
                                                :component="$activity['icon']"
                                                @class(['h-4 w-4', $iconTextClass])
                                            />
                                        </span>
                                    </div>

                                    {{-- Content --}}
                                    <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                        <div>
                                            <p class="text-sm text-gray-900 dark:text-white">
                                                <span class="font-medium">{{ $activity['title'] }}</span>
                                            </p>
                                            @if($activity['url'])
                                                <a href="{{ $activity['url'] }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:underline">
                                                    {{ $activity['description'] }}
                                                </a>
                                            @else
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $activity['description'] }}
                                                </p>
                                            @endif
                                        </div>
                                        <div class="whitespace-nowrap text-right text-sm">
                                            @if($activity['amount'])
                                                <p class="font-medium
                                                    @if($activity['type'] === 'payment_received') text-success-600 dark:text-success-400
                                                    @elseif($activity['type'] === 'refund_processed') text-danger-600 dark:text-danger-400
                                                    @elseif($activity['type'] === 'credit_note_issued') text-warning-600 dark:text-warning-400
                                                    @else text-gray-900 dark:text-white
                                                    @endif
                                                ">
                                                    @if($activity['type'] === 'refund_processed' || $activity['type'] === 'credit_note_issued')
                                                        -
                                                    @endif
                                                    {{ $activity['currency'] }} {{ number_format((float) $activity['amount'], 2) }}
                                                </p>
                                            @endif
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $activity['timestamp']->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="text-center py-8">
                <x-heroicon-o-inbox class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No recent activity</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    There haven't been any financial events in the last 24 hours.
                </p>
            </div>
        @endif
    </div>

    {{-- Today's Activity & Quick Stats --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
        {{-- Today's Activity --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-calendar-days class="h-5 w-5 text-gray-400" />
                Today's Activity
            </h3>
            <dl class="space-y-3">
                <div class="flex items-center justify-between">
                    <dt class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-document-text class="h-4 w-4 mr-2 text-primary-400" />
                        Invoices issued
                    </dt>
                    <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ $invoicesToday }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-credit-card class="h-4 w-4 mr-2 text-success-400" />
                        Payments received
                    </dt>
                    <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ $paymentsToday }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-banknotes class="h-4 w-4 mr-2 text-success-400" />
                        Amount collected
                    </dt>
                    <dd class="text-sm font-semibold text-success-600 dark:text-success-400">{{ $this->formatAmount($paymentsAmountToday) }}</dd>
                </div>
            </dl>
        </div>

        {{-- Reconciliation Summary --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-scale class="h-5 w-5 text-gray-400" />
                Reconciliation Status
            </h3>
            <dl class="space-y-3">
                <div class="flex items-center justify-between">
                    <dt class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <span class="w-2 h-2 rounded-full bg-warning-400 mr-2"></span>
                        Pending
                    </dt>
                    <dd class="text-sm font-semibold {{ $pendingReconciliations > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">{{ $pendingReconciliations }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <span class="w-2 h-2 rounded-full bg-danger-400 mr-2"></span>
                        Mismatched
                    </dt>
                    <dd class="text-sm font-semibold {{ $mismatchedReconciliations > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $mismatchedReconciliations }}</dd>
                </div>
            </dl>
            @if($pendingReconciliations > 0 || $mismatchedReconciliations > 0)
                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('filament.admin.resources.finance.payments.index', ['tableFilters' => ['reconciliation_status' => ['value' => 'pending']]]) }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                        View payments &rarr;
                    </a>
                </div>
            @endif
        </div>

        {{-- Month Summary --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-chart-bar class="h-5 w-5 text-gray-400" />
                {{ $this->getCurrentMonthLabel() }}
            </h3>
            <dl class="space-y-3">
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Collected</dt>
                    <dd class="text-sm font-semibold text-success-600 dark:text-success-400">{{ $this->formatAmount($paymentsThisMonth) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Outstanding</dt>
                    <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->formatAmount($totalOutstanding) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Overdue</dt>
                    <dd class="text-sm font-semibold {{ $overdueCount > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $this->formatAmount($overdueAmount) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Period Comparison Widget (US-E120) --}}
    @php
        $periodComparison = $this->getPeriodComparison();
    @endphp
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <x-heroicon-o-scale class="h-5 w-5 text-gray-400" />
                Period Comparison
            </h3>
            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $periodComparison['current_month'] }}</span>
                <span>vs</span>
                <span>{{ $periodComparison['previous_month'] }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Invoices Issued --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-2">
                        <x-heroicon-o-document-text class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                    </div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Invoices Issued</span>
                </div>

                <div class="space-y-3">
                    {{-- Count comparison --}}
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Count</div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $periodComparison['invoices']['current_count'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    vs {{ $periodComparison['invoices']['previous_count'] }}
                                </div>
                            </div>
                            @if($periodComparison['invoices']['count_change']['direction'] !== 'neutral')
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getPeriodChangeBgColorClass($periodComparison['invoices']['count_change']['direction']) }} {{ $this->getPeriodChangeColorClass($periodComparison['invoices']['count_change']['direction']) }}">
                                    <x-dynamic-component :component="$this->getChangeIcon($periodComparison['invoices']['count_change']['direction'])" class="h-3 w-3" />
                                    {{ number_format($periodComparison['invoices']['count_change']['value'], 1) }}%
                                </div>
                            @else
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                    0%
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Amount comparison --}}
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Amount</div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $this->formatAmount($periodComparison['invoices']['current_amount']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    vs {{ $this->formatAmount($periodComparison['invoices']['previous_amount']) }}
                                </div>
                            </div>
                            @if($periodComparison['invoices']['amount_change']['direction'] !== 'neutral')
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getPeriodChangeBgColorClass($periodComparison['invoices']['amount_change']['direction']) }} {{ $this->getPeriodChangeColorClass($periodComparison['invoices']['amount_change']['direction']) }}">
                                    <x-dynamic-component :component="$this->getChangeIcon($periodComparison['invoices']['amount_change']['direction'])" class="h-3 w-3" />
                                    {{ number_format($periodComparison['invoices']['amount_change']['value'], 1) }}%
                                </div>
                            @else
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                    0%
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Amount Collected (Payments) --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-2">
                        <x-heroicon-o-banknotes class="h-4 w-4 text-success-600 dark:text-success-400" />
                    </div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Amount Collected</span>
                </div>

                <div class="space-y-3">
                    {{-- Count comparison --}}
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Payments</div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $periodComparison['payments']['current_count'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    vs {{ $periodComparison['payments']['previous_count'] }}
                                </div>
                            </div>
                            @if($periodComparison['payments']['count_change']['direction'] !== 'neutral')
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getPeriodChangeBgColorClass($periodComparison['payments']['count_change']['direction']) }} {{ $this->getPeriodChangeColorClass($periodComparison['payments']['count_change']['direction']) }}">
                                    <x-dynamic-component :component="$this->getChangeIcon($periodComparison['payments']['count_change']['direction'])" class="h-3 w-3" />
                                    {{ number_format($periodComparison['payments']['count_change']['value'], 1) }}%
                                </div>
                            @else
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                    0%
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Amount comparison --}}
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Amount</div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-success-600 dark:text-success-400">
                                    {{ $this->formatAmount($periodComparison['payments']['current_amount']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    vs {{ $this->formatAmount($periodComparison['payments']['previous_amount']) }}
                                </div>
                            </div>
                            @if($periodComparison['payments']['amount_change']['direction'] !== 'neutral')
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getPeriodChangeBgColorClass($periodComparison['payments']['amount_change']['direction']) }} {{ $this->getPeriodChangeColorClass($periodComparison['payments']['amount_change']['direction']) }}">
                                    <x-dynamic-component :component="$this->getChangeIcon($periodComparison['payments']['amount_change']['direction'])" class="h-3 w-3" />
                                    {{ number_format($periodComparison['payments']['amount_change']['value'], 1) }}%
                                </div>
                            @else
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                    0%
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Credit Notes (inverse colors - more credit notes is typically bad) --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <div class="flex-shrink-0 rounded-lg bg-warning-50 dark:bg-warning-400/10 p-2">
                        <x-heroicon-o-document-minus class="h-4 w-4 text-warning-600 dark:text-warning-400" />
                    </div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Credit Notes</span>
                </div>

                <div class="space-y-3">
                    {{-- Count comparison --}}
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Count</div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $periodComparison['credit_notes']['current_count'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    vs {{ $periodComparison['credit_notes']['previous_count'] }}
                                </div>
                            </div>
                            @if($periodComparison['credit_notes']['count_change']['direction'] !== 'neutral')
                                {{-- Inverse colors for credit notes: up is red/bad, down is green/good --}}
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getPeriodChangeBgColorClass($periodComparison['credit_notes']['count_change']['direction'], true) }} {{ $this->getPeriodChangeColorClass($periodComparison['credit_notes']['count_change']['direction'], true) }}">
                                    <x-dynamic-component :component="$this->getChangeIcon($periodComparison['credit_notes']['count_change']['direction'])" class="h-3 w-3" />
                                    {{ number_format($periodComparison['credit_notes']['count_change']['value'], 1) }}%
                                </div>
                            @else
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                    0%
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Amount comparison --}}
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Amount</div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-warning-600 dark:text-warning-400">
                                    {{ $this->formatAmount($periodComparison['credit_notes']['current_amount']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    vs {{ $this->formatAmount($periodComparison['credit_notes']['previous_amount']) }}
                                </div>
                            </div>
                            @if($periodComparison['credit_notes']['amount_change']['direction'] !== 'neutral')
                                {{-- Inverse colors for credit notes: up is red/bad, down is green/good --}}
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getPeriodChangeBgColorClass($periodComparison['credit_notes']['amount_change']['direction'], true) }} {{ $this->getPeriodChangeColorClass($periodComparison['credit_notes']['amount_change']['direction'], true) }}">
                                    <x-dynamic-component :component="$this->getChangeIcon($periodComparison['credit_notes']['amount_change']['direction'])" class="h-3 w-3" />
                                    {{ number_format($periodComparison['credit_notes']['amount_change']['value'], 1) }}%
                                </div>
                            @else
                                <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                    0%
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Top 10 Customers by Outstanding (US-E121) --}}
    @php
        $topCustomers = $this->getTopCustomersOutstanding();
    @endphp
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <x-heroicon-o-user-group class="h-5 w-5 text-gray-400" />
            Top 10 Outstanding
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(by customer)</span>
        </h3>

        @if(count($topCustomers) > 0)
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th scope="col" class="py-2 pl-0 pr-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Customer
                            </th>
                            <th scope="col" class="py-2 px-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Invoices
                            </th>
                            <th scope="col" class="py-2 pl-3 pr-0 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Outstanding
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($topCustomers as $index => $customerData)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="py-2.5 pl-0 pr-3 text-sm">
                                    <div class="flex items-center gap-3">
                                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-400">
                                            {{ $index + 1 }}
                                        </span>
                                        <a href="{{ $customerData['url'] }}"
                                           class="font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 hover:underline truncate max-w-[200px]"
                                           title="{{ $customerData['customer_name'] }}">
                                            {{ $customerData['customer_name'] }}
                                        </a>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-sm text-right text-gray-500 dark:text-gray-400">
                                    {{ $customerData['invoice_count'] }} {{ Str::plural('invoice', $customerData['invoice_count']) }}
                                </td>
                                <td class="py-2.5 pl-3 pr-0 text-sm text-right">
                                    <span class="font-semibold {{ $index < 3 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">
                                        {{ $this->formatAmount($customerData['outstanding_amount']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('filament.admin.pages.customer-finance') }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                    View all customers &rarr;
                </a>
            </div>
        @else
            <div class="text-center py-6">
                <x-heroicon-o-check-circle class="mx-auto h-10 w-10 text-success-300 dark:text-success-600" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All clear!</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No customers have outstanding invoices.
                </p>
            </div>
        @endif
    </div>

    {{-- Help Text --}}
    <div class="text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-center">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 text-gray-400" />
            <span>
                This dashboard provides a real-time overview of your financial operations.
                Use the refresh button to update the metrics.
            </span>
        </p>
    </div>
</x-filament-panels::page>
