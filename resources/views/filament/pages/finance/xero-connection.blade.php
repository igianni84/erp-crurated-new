<x-filament-panels::page>
    @php
        $status = $this->getConnectionStatus();
    @endphp

    <div class="max-w-3xl mx-auto space-y-6">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="bg-success-50 dark:bg-success-500/10 border border-success-200 dark:border-success-500/20 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <x-heroicon-m-check-circle class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0" />
                    <p class="text-sm font-medium text-success-800 dark:text-success-200">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/20 rounded-xl p-4">
                <div class="flex items-center gap-3">
                    <x-heroicon-m-x-circle class="w-5 h-5 text-danger-600 dark:text-danger-400 flex-shrink-0" />
                    <p class="text-sm font-medium text-danger-800 dark:text-danger-200">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        {{-- Connection Status Card --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Connection Status</h2>
            </div>

            <div class="p-4">
                @if($status['connected'])
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-3 h-3 rounded-full bg-success-500 animate-pulse"></div>
                        <span class="text-sm font-medium text-success-700 dark:text-success-400">Connected to Xero</span>
                    </div>
                @elseif($status['configured'])
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-3 h-3 rounded-full bg-warning-500"></div>
                        <span class="text-sm font-medium text-warning-700 dark:text-warning-400">Configured but not connected</span>
                    </div>
                @else
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Not configured</span>
                    </div>
                @endif

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-500">OAuth Credentials</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            @if($status['configured'])
                                <span class="text-success-600 dark:text-success-400">Configured</span>
                            @else
                                <span class="text-gray-400">Not set</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-500">Sync Enabled</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            @if($status['sync_enabled'])
                                <span class="text-success-600 dark:text-success-400">Yes</span>
                            @else
                                <span class="text-warning-600 dark:text-warning-400">Disabled</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-500">Tenant ID</dt>
                        <dd class="font-medium font-mono text-xs text-gray-900 dark:text-white">
                            {{ $status['tenant_id'] ? Str::limit($status['tenant_id'], 20) : 'Not set' }}
                        </dd>
                    </div>
                    @if($status['token_expires_at'])
                        <div>
                            <dt class="text-gray-500 dark:text-gray-500">Token Expires</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">
                                {{ $status['token_expires_at']->diffForHumans() }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Not Configured Info --}}
        @if(!$status['configured'])
            <div class="bg-info-50 dark:bg-info-500/10 border border-info-200 dark:border-info-500/20 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-m-information-circle class="w-5 h-5 text-info-600 dark:text-info-400 flex-shrink-0 mt-0.5" />
                    <div>
                        <h4 class="font-medium text-info-800 dark:text-info-200">Setup Required</h4>
                        <p class="text-sm text-info-700 dark:text-info-300 mt-1">
                            To connect Xero, set the following environment variables:
                        </p>
                        <ul class="mt-2 space-y-1 text-sm text-info-700 dark:text-info-300">
                            <li><code class="px-1 py-0.5 bg-info-100 dark:bg-info-500/20 rounded text-xs font-mono">XERO_CLIENT_ID</code></li>
                            <li><code class="px-1 py-0.5 bg-info-100 dark:bg-info-500/20 rounded text-xs font-mono">XERO_CLIENT_SECRET</code></li>
                            <li><code class="px-1 py-0.5 bg-info-100 dark:bg-info-500/20 rounded text-xs font-mono">XERO_REDIRECT_URI</code></li>
                        </ul>
                        <p class="text-sm text-info-700 dark:text-info-300 mt-2">
                            Register your app at <strong>developer.xero.com/app/manage</strong>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Account Codes Card --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Account Code Mapping</h2>
            </div>

            <div class="p-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-500">
                            <th class="pb-2">Invoice Type</th>
                            <th class="pb-2">Account Code</th>
                            <th class="pb-2">Env Variable</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach([
                            ['INV0 - Membership Service', config('finance.xero.account_codes.membership_service', '200'), 'XERO_ACCOUNT_CODE_INV0'],
                            ['INV1 - Voucher Sale', config('finance.xero.account_codes.voucher_sale', '210'), 'XERO_ACCOUNT_CODE_INV1'],
                            ['INV2 - Shipping Redemption', config('finance.xero.account_codes.shipping_redemption', '220'), 'XERO_ACCOUNT_CODE_INV2'],
                            ['INV3 - Storage Fee', config('finance.xero.account_codes.storage_fee', '230'), 'XERO_ACCOUNT_CODE_INV3'],
                            ['INV4 - Service Events', config('finance.xero.account_codes.service_events', '240'), 'XERO_ACCOUNT_CODE_INV4'],
                        ] as [$type, $code, $env])
                            <tr>
                                <td class="py-2 text-gray-900 dark:text-white">{{ $type }}</td>
                                <td class="py-2 font-mono text-gray-900 dark:text-white">{{ $code }}</td>
                                <td class="py-2"><code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono text-gray-600 dark:text-gray-400">{{ $env }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
