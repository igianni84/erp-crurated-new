<x-filament-panels::page>
    @php
        $stripeSummary = $this->getStripeHealthSummary();
        $failedWebhooks = $this->getFailedWebhooks();
    @endphp

    {{-- Stripe Integration Section --}}
    <div class="space-y-6">
        {{-- Section Header --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 rounded-lg bg-purple-100 dark:bg-purple-900/20 p-3">
                    <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Stripe Integration</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Webhook processing and payment reconciliation</p>
                </div>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $this->getStatusBadgeClasses($stripeSummary['status']) }}">
                @if($stripeSummary['status'] === 'healthy')
                    <x-heroicon-s-check-circle class="h-4 w-4 mr-1.5" />
                @elseif($stripeSummary['status'] === 'warning')
                    <x-heroicon-s-exclamation-triangle class="h-4 w-4 mr-1.5" />
                @elseif($stripeSummary['status'] === 'critical')
                    <x-heroicon-s-x-circle class="h-4 w-4 mr-1.5" />
                @else
                    <x-heroicon-s-question-mark-circle class="h-4 w-4 mr-1.5" />
                @endif
                {{ ucfirst($stripeSummary['status']) }}
            </span>
        </div>

        {{-- Alerts --}}
        @if(count($stripeSummary['alerts']) > 0)
            <div class="space-y-3">
                @foreach($stripeSummary['alerts'] as $alert)
                    <div class="rounded-lg {{ $stripeSummary['status'] === 'critical' ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-warning-50 dark:bg-warning-400/10' }} p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                @if($stripeSummary['status'] === 'critical')
                                    <x-heroicon-o-x-circle class="h-5 w-5 text-danger-400" />
                                @else
                                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-400" />
                                @endif
                            </div>
                            <div class="ml-3">
                                <p class="text-sm {{ $stripeSummary['status'] === 'critical' ? 'text-danger-700 dark:text-danger-400' : 'text-warning-700 dark:text-warning-400' }}">
                                    {{ $alert }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Metrics Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Last Webhook --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ $this->hasNoRecentWebhooks() && $stripeSummary['last_webhook'] !== null ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-primary-50 dark:bg-primary-400/10' }} p-3">
                        <x-heroicon-o-clock class="h-6 w-6 {{ $this->hasNoRecentWebhooks() && $stripeSummary['last_webhook'] !== null ? 'text-warning-600 dark:text-warning-400' : 'text-primary-600 dark:text-primary-400' }}" />
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Webhook</p>
                        @if($stripeSummary['last_webhook_time'])
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $stripeSummary['last_webhook_time']->diffForHumans() }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $stripeSummary['last_webhook'] }}</p>
                        @else
                            <p class="text-lg font-semibold text-gray-400 dark:text-gray-500">Never</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Failed Events --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ $stripeSummary['failed_count'] > 0 ? 'bg-danger-50 dark:bg-danger-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-3">
                        <x-heroicon-o-x-circle class="h-6 w-6 {{ $stripeSummary['failed_count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed Events</p>
                        <p class="text-2xl font-semibold {{ $stripeSummary['failed_count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $stripeSummary['failed_count'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Pending Reconciliations --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg {{ $stripeSummary['pending_reconciliations'] > 0 ? 'bg-warning-50 dark:bg-warning-400/10' : 'bg-success-50 dark:bg-success-400/10' }} p-3">
                        <x-heroicon-o-document-magnifying-glass class="h-6 w-6 {{ $stripeSummary['pending_reconciliations'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Reconciliations</p>
                        <p class="text-2xl font-semibold {{ $stripeSummary['pending_reconciliations'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">{{ $stripeSummary['pending_reconciliations'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Today's Activity --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 rounded-lg bg-info-50 dark:bg-info-400/10 p-3">
                        <x-heroicon-o-arrow-trending-up class="h-6 w-6 text-info-600 dark:text-info-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Webhooks</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $stripeSummary['today_processed'] }}<span class="text-gray-400">/{{ $stripeSummary['today_received'] }}</span>
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">processed / received</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Failed Webhooks List --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <x-heroicon-o-exclamation-circle class="inline-block h-5 w-5 mr-2 -mt-0.5 text-danger-500" />
                        Failed Webhook Events
                    </h3>
                    @if($failedWebhooks->count() > 0)
                        <button
                            wire:click="retryAllFailed"
                            wire:confirm="Are you sure you want to retry all {{ $failedWebhooks->count() }} failed webhooks?"
                            type="button"
                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-900"
                        >
                            <x-heroicon-o-arrow-path class="h-4 w-4 mr-1.5" />
                            Retry All ({{ $failedWebhooks->count() }})
                        </button>
                    @endif
                </div>
            </div>
            <div class="fi-section-content">
                @if($failedWebhooks->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Event
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Event ID
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Received
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Retries
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Error
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($failedWebhooks as $webhook)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <x-dynamic-component :component="$webhook->getStatusIcon()" class="h-5 w-5 text-danger-500 mr-2" />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ $webhook->getEventTypeLabel() }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $webhook->event_type }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-mono text-gray-600 dark:text-gray-400">
                                                {{ Str::limit($webhook->event_id, 24) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                {{ $webhook->created_at->format('M j, Y') }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $webhook->created_at->format('H:i:s') }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($webhook->retry_count > 0)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $webhook->retry_count >= 3 ? 'bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400' : 'bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400' }}">
                                                    {{ $webhook->retry_count }} {{ Str::plural('retry', $webhook->retry_count) }}
                                                </span>
                                                @if($webhook->last_retry_at)
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                        {{ $webhook->last_retry_at->diffForHumans() }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500">Never</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-danger-600 dark:text-danger-400 max-w-xs truncate" title="{{ $webhook->error_message }}">
                                                {{ Str::limit($webhook->error_message, 60) }}
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                wire:click="retryWebhook({{ $webhook->id }})"
                                                type="button"
                                                class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                            >
                                                <x-heroicon-o-arrow-path class="h-3.5 w-3.5 mr-1" />
                                                Retry
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No failed webhooks</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            All webhook events have been processed successfully.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Additional Info --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Reconciliation Summary --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                    <x-heroicon-o-scale class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Reconciliation Summary
                </h3>
                <dl class="space-y-3">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Pending reconciliation</dt>
                        <dd class="text-sm font-medium {{ $stripeSummary['pending_reconciliations'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">
                            {{ $stripeSummary['pending_reconciliations'] }} payment(s)
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Mismatched</dt>
                        <dd class="text-sm font-medium {{ $stripeSummary['mismatched_reconciliations'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">
                            {{ $stripeSummary['mismatched_reconciliations'] }} payment(s)
                        </dd>
                    </div>
                </dl>
                @if($stripeSummary['pending_reconciliations'] > 0 || $stripeSummary['mismatched_reconciliations'] > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('filament.admin.resources.finance.payments.index', ['tableFilters' => ['reconciliation_status' => ['value' => 'pending']]]) }}" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">
                            View pending reconciliations &rarr;
                        </a>
                    </div>
                @endif
            </div>

            {{-- Webhook Event Types --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                    <x-heroicon-o-list-bullet class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Supported Event Types
                </h3>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <li class="flex items-center">
                        <x-heroicon-o-check class="h-4 w-4 text-success-500 mr-2" />
                        <span><code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">payment_intent.succeeded</code></span>
                    </li>
                    <li class="flex items-center">
                        <x-heroicon-o-check class="h-4 w-4 text-success-500 mr-2" />
                        <span><code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">payment_intent.payment_failed</code></span>
                    </li>
                    <li class="flex items-center">
                        <x-heroicon-o-check class="h-4 w-4 text-success-500 mr-2" />
                        <span><code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">charge.refunded</code></span>
                    </li>
                    <li class="flex items-center">
                        <x-heroicon-o-check class="h-4 w-4 text-success-500 mr-2" />
                        <span><code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">charge.dispute.created</code></span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Xero Integration Placeholder --}}
        <div class="fi-section rounded-xl bg-gray-50 dark:bg-gray-800/50 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-6">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 rounded-lg bg-gray-200 dark:bg-gray-700 p-3">
                    <svg class="h-6 w-6 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.526.5H1.934a1.5 1.5 0 0 0-1.5 1.5v9.592a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5V2a1.5 1.5 0 0 0-1.5-1.5zm10.54 0h-9.592a1.5 1.5 0 0 0-1.5 1.5v9.592a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5V2a1.5 1.5 0 0 0-1.5-1.5zM11.526 11.408H1.934a1.5 1.5 0 0 0-1.5 1.5V22.5a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5v-9.592a1.5 1.5 0 0 0-1.5-1.5zm10.54 0h-9.592a1.5 1.5 0 0 0-1.5 1.5V22.5a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5v-9.592a1.5 1.5 0 0 0-1.5-1.5z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-500 dark:text-gray-400">Xero Integration</h2>
                    <p class="text-sm text-gray-400 dark:text-gray-500">Coming soon - Invoice and credit note synchronization</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-center">
            <x-heroicon-o-information-circle class="h-5 w-5 mr-2 text-gray-400" />
            <span>
                Integration health is monitored in real-time. Alerts are triggered when webhooks fail or are not received within expected timeframes.
                Use the retry actions to reprocess failed webhook events.
            </span>
        </p>
    </div>
</x-filament-panels::page>
