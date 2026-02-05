<x-filament-panels::page>
    @php
        $stripeConfig = $this->getStripeConfig();
        $xeroConfig = $this->getXeroConfig();
        $financeConfig = $this->getFinanceConfig();
        $envVars = $this->getRequiredEnvVars();
        $health = $this->getConfigurationHealth();
    @endphp

    {{-- Overall Health Status --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 rounded-lg {{ $health['status'] === 'healthy' ? 'bg-success-100 dark:bg-success-900/20' : ($health['status'] === 'warning' ? 'bg-warning-100 dark:bg-warning-900/20' : 'bg-danger-100 dark:bg-danger-900/20') }} p-3">
                    @if($health['status'] === 'healthy')
                        <x-heroicon-o-shield-check class="h-6 w-6 text-success-600 dark:text-success-400" />
                    @elseif($health['status'] === 'warning')
                        <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                    @else
                        <x-heroicon-o-shield-exclamation class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                    @endif
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Configuration Status</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($health['status'] === 'healthy')
                            All integrations are properly configured
                        @elseif($health['status'] === 'warning')
                            Some integrations need attention
                        @else
                            Critical configuration issues detected
                        @endif
                    </p>
                </div>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $this->getStatusBadgeClasses($health['status']) }}">
                @if($health['status'] === 'healthy')
                    <x-heroicon-s-check-circle class="h-4 w-4 mr-1.5" />
                @elseif($health['status'] === 'warning')
                    <x-heroicon-s-exclamation-triangle class="h-4 w-4 mr-1.5" />
                @else
                    <x-heroicon-s-x-circle class="h-4 w-4 mr-1.5" />
                @endif
                {{ ucfirst($health['status']) }}
            </span>
        </div>

        @if(count($health['issues']) > 0)
            <div class="mt-4 space-y-2">
                @foreach($health['issues'] as $issue)
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                        <x-heroicon-o-exclamation-circle class="h-4 w-4 mr-2 text-warning-500" />
                        {{ $issue }}
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Stripe Configuration Section --}}
        <div class="space-y-6">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{-- Header --}}
                <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 rounded-lg bg-purple-100 dark:bg-purple-900/20 p-2">
                                <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Stripe</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Payment processing</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $stripeConfig['configured'] ? 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400' : 'bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400' }}">
                            {{ $stripeConfig['configured'] ? 'Configured' : 'Incomplete' }}
                        </span>
                    </div>
                </div>

                {{-- Content --}}
                <div class="p-6 space-y-4">
                    {{-- Environment Badge --}}
                    @if($stripeConfig['environment'] !== 'unknown')
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Environment</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $stripeConfig['environment'] === 'live' ? 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400' : 'bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400' }}">
                                {{ ucfirst($stripeConfig['environment']) }} Mode
                            </span>
                        </div>
                    @endif

                    {{-- API Key Preview --}}
                    @if($stripeConfig['key_preview'])
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">API Key</span>
                            <code class="text-sm font-mono text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                                {{ $stripeConfig['key_preview'] }}
                            </code>
                        </div>
                    @endif

                    {{-- Webhook Tolerance --}}
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Webhook Tolerance</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ $stripeConfig['webhook_tolerance'] }} seconds</span>
                    </div>

                    {{-- Webhook URL --}}
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Webhook Endpoint</span>
                        <div class="mt-1 flex items-center gap-2">
                            <code class="flex-1 text-xs font-mono text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-1.5 rounded break-all">
                                {{ $this->getStripeWebhookUrl() }}
                            </code>
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText('{{ $this->getStripeWebhookUrl() }}')"
                                class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                title="Copy to clipboard"
                            >
                                <x-heroicon-o-clipboard-document class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {{-- Required Environment Variables --}}
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Required Environment Variables</span>
                        <ul class="mt-2 space-y-2">
                            @foreach($envVars['stripe'] as $var)
                                <li class="flex items-start gap-2">
                                    @if($var['set'])
                                        <x-heroicon-o-check-circle class="h-4 w-4 text-success-500 mt-0.5 flex-shrink-0" />
                                    @else
                                        <x-heroicon-o-x-circle class="h-4 w-4 text-danger-500 mt-0.5 flex-shrink-0" />
                                    @endif
                                    <div>
                                        <code class="text-xs font-mono {{ $var['set'] ? 'text-gray-700 dark:text-gray-300' : 'text-danger-600 dark:text-danger-400' }}">{{ $var['key'] }}</code>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $var['description'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Test Connection Button --}}
                    <div class="pt-4">
                        <button
                            wire:click="testStripeConnection"
                            type="button"
                            class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 dark:focus:ring-offset-gray-900"
                        >
                            <x-heroicon-o-bolt class="h-4 w-4 mr-2" />
                            Test Connection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Xero Configuration Section --}}
        <div class="space-y-6">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                {{-- Header --}}
                <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/20 p-2">
                                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.526.5H1.934a1.5 1.5 0 0 0-1.5 1.5v9.592a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5V2a1.5 1.5 0 0 0-1.5-1.5zm10.54 0h-9.592a1.5 1.5 0 0 0-1.5 1.5v9.592a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5V2a1.5 1.5 0 0 0-1.5-1.5zM11.526 11.408H1.934a1.5 1.5 0 0 0-1.5 1.5V22.5a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5v-9.592a1.5 1.5 0 0 0-1.5-1.5zm10.54 0h-9.592a1.5 1.5 0 0 0-1.5 1.5V22.5a1.5 1.5 0 0 0 1.5 1.5h9.592a1.5 1.5 0 0 0 1.5-1.5v-9.592a1.5 1.5 0 0 0-1.5-1.5z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Xero</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Accounting sync</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $xeroConfig['configured'] ? 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400' : 'bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400' }}">
                            {{ $xeroConfig['configured'] ? 'Configured' : 'Incomplete' }}
                        </span>
                    </div>
                </div>

                {{-- Content --}}
                <div class="p-6 space-y-4">
                    {{-- Sync Status --}}
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Sync Status</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $xeroConfig['sync_enabled'] ? 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $xeroConfig['sync_enabled'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    {{-- Client ID Preview --}}
                    @if($xeroConfig['client_id_preview'])
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Client ID</span>
                            <code class="text-sm font-mono text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                                {{ $xeroConfig['client_id_preview'] }}
                            </code>
                        </div>
                    @endif

                    {{-- Tenant ID Preview --}}
                    @if($xeroConfig['tenant_id_preview'])
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Tenant ID</span>
                            <code class="text-sm font-mono text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                                {{ $xeroConfig['tenant_id_preview'] }}
                            </code>
                        </div>
                    @endif

                    {{-- Max Retry Count --}}
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Max Retry Count</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ $xeroConfig['max_retry_count'] }} attempts</span>
                    </div>

                    {{-- Required Environment Variables --}}
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Required Environment Variables</span>
                        <ul class="mt-2 space-y-2">
                            @foreach($envVars['xero'] as $var)
                                <li class="flex items-start gap-2">
                                    @if($var['set'])
                                        <x-heroicon-o-check-circle class="h-4 w-4 text-success-500 mt-0.5 flex-shrink-0" />
                                    @else
                                        <x-heroicon-o-x-circle class="h-4 w-4 text-danger-500 mt-0.5 flex-shrink-0" />
                                    @endif
                                    <div>
                                        <code class="text-xs font-mono {{ $var['set'] ? 'text-gray-700 dark:text-gray-300' : 'text-danger-600 dark:text-danger-400' }}">{{ $var['key'] }}</code>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $var['description'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Test Connection Button --}}
                    <div class="pt-4">
                        <button
                            wire:click="testXeroConnection"
                            type="button"
                            class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-900"
                        >
                            <x-heroicon-o-bolt class="h-4 w-4 mr-2" />
                            Test Connection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Finance Settings Section --}}
    <div class="mt-6">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-800 p-2">
                        <x-heroicon-o-currency-euro class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Finance Settings</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Billing and invoice configuration</p>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Base Currency --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Base Currency</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Default invoice currency</p>
                        </div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $financeConfig['base_currency'] }}</span>
                    </div>

                    {{-- Seller Country --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Seller Country</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Tax jurisdiction</p>
                        </div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $financeConfig['seller_country'] }}</span>
                    </div>

                    {{-- Storage Billing Cycle --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Storage Billing</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">INV3 billing cycle</p>
                        </div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white capitalize">{{ $financeConfig['storage_billing_cycle'] }}</span>
                    </div>

                    {{-- Subscription Overdue Days --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Subscription Suspension</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Days overdue before suspension</p>
                        </div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $financeConfig['subscription_overdue_days'] }} days</span>
                    </div>

                    {{-- Storage Overdue Days --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Custody Block</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Days overdue before custody block</p>
                        </div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $financeConfig['storage_overdue_days'] }} days</span>
                    </div>

                    {{-- Immediate Invoice Alert --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Payment Alert</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Hours until unpaid alert (INV1/2/4)</p>
                        </div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $financeConfig['immediate_invoice_alert_hours'] }} hours</span>
                    </div>
                </div>

                {{-- Auto Issue Toggle --}}
                <div class="mt-6 flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Auto-Issue Storage Invoices</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Automatically issue INV3 invoices when generated</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $financeConfig['storage_auto_issue'] ? 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $financeConfig['storage_auto_issue'] ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Configuration Instructions --}}
    <div class="mt-6">
        <div class="fi-section rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Configuration Instructions</h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-400 space-y-2">
                        <p>
                            Integration credentials are stored securely in your <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">.env</code> file and cannot be modified through this interface for security reasons.
                        </p>
                        <p>
                            To update credentials:
                        </p>
                        <ol class="list-decimal list-inside space-y-1 ml-2">
                            <li>Edit your <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">.env</code> file directly on the server</li>
                            <li>Run <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">php artisan config:clear</code> to refresh configuration</li>
                            <li>Use the "Test Connection" buttons above to verify your credentials</li>
                        </ol>
                        <p class="pt-2">
                            For Stripe: Configure your webhook endpoint in the Stripe Dashboard to point to the URL shown above.
                        </p>
                        <p>
                            For Xero: Complete the OAuth2 flow in your Xero Developer Portal to obtain your tenant ID.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Help Text --}}
    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p class="flex items-center">
            <x-heroicon-o-shield-check class="h-5 w-5 mr-2 text-gray-400" />
            <span>
                Sensitive values are masked for security. Full credentials are stored in environment variables.
            </span>
        </p>
    </div>
</x-filament-panels::page>
