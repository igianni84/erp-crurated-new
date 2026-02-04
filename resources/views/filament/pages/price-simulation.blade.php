<x-filament-panels::page>
    {{-- Page Header --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <x-heroicon-o-calculator class="h-6 w-6 text-primary-500" />
            </div>
            <div class="ml-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Price Simulation</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Simulate end-to-end price resolution for debugging. Test how prices are calculated
                    for specific SKUs, channels, customers, and quantities.
                </p>
            </div>
        </div>
    </div>

    {{-- Form Section --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <form wire:submit="simulate">
            <div class="fi-section-content p-6">
                {{ $this->form }}
            </div>

            <div class="fi-section-footer border-t border-gray-200 dark:border-white/10 px-6 py-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Fill in the parameters above and click "Simulate" to see the price resolution breakdown.
                    </p>
                    <div class="flex gap-3">
                        @if($hasSimulated)
                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="resetSimulation"
                            >
                                Reset
                            </x-filament::button>
                        @endif
                        <x-filament::button
                            type="submit"
                            icon="heroicon-o-play"
                            wire:loading.attr="disabled"
                            wire:target="simulate"
                        >
                            <span wire:loading.remove wire:target="simulate">Simulate</span>
                            <span wire:loading wire:target="simulate">Simulating...</span>
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Results Section --}}
    @if($hasSimulated && $simulationResult !== null)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                        <x-heroicon-o-document-magnifying-glass class="h-5 w-5 mr-2 text-primary-500" />
                        Simulation Results
                    </h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-200">
                        Preview Mode
                    </span>
                </div>
            </div>
            <div class="fi-section-content p-6">
                {{-- Context Summary --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700 mb-6">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Simulation Context</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">SKU</p>
                            <p class="font-mono text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['sku_code'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Channel</p>
                            <p class="text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['channel'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Customer</p>
                            <p class="text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['customer'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Date / Quantity</p>
                            <p class="text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['date'] }} / {{ $simulationResult['context']['quantity'] }} unit(s)</p>
                        </div>
                    </div>
                </div>

                {{-- Resolution Steps --}}
                <div class="space-y-4">
                    @foreach($simulationResult['steps'] as $stepKey => $step)
                        @php
                            $statusColor = match($step['status']) {
                                'success' => 'success',
                                'warning' => 'warning',
                                'error' => 'danger',
                                default => 'gray',
                            };
                            $statusBg = match($step['status']) {
                                'success' => 'bg-success-50 dark:bg-success-950 border-success-200 dark:border-success-800',
                                'warning' => 'bg-warning-50 dark:bg-warning-950 border-warning-200 dark:border-warning-800',
                                'error' => 'bg-danger-50 dark:bg-danger-950 border-danger-200 dark:border-danger-800',
                                default => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
                            };
                            $iconColor = match($step['status']) {
                                'success' => 'text-success-600 dark:text-success-400',
                                'warning' => 'text-warning-600 dark:text-warning-400',
                                'error' => 'text-danger-600 dark:text-danger-400',
                                default => 'text-gray-500 dark:text-gray-400',
                            };
                        @endphp

                        <div class="rounded-lg border {{ $statusBg }} p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 {{ $iconColor }}">
                                    <x-dynamic-component :component="$step['icon']" class="h-6 w-6" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $step['title'] }}
                                        </h4>
                                        @php
                                            $badgeBg = match($step['status']) {
                                                'success' => 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200',
                                                'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200',
                                                'error' => 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200',
                                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeBg }}">
                                            {{ ucfirst($step['status']) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ $step['message'] }}
                                    </p>

                                    @if(!empty($step['details']))
                                        <div class="mt-3 p-3 rounded bg-white/50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-600">
                                            <dl class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                                @foreach($step['details'] as $key => $value)
                                                    <div>
                                                        <dt class="text-gray-500 dark:text-gray-400 uppercase">{{ str_replace('_', ' ', $key) }}</dt>
                                                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Coming Soon Notice --}}
                <div class="mt-6 rounded-lg bg-info-50 dark:bg-info-950 border border-info-200 dark:border-info-800 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 text-info-600 dark:text-info-400">
                            <x-heroicon-o-clock class="h-5 w-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-info-800 dark:text-info-200">Full Simulation Coming in US-056</h4>
                            <p class="text-sm text-info-700 dark:text-info-300 mt-1">
                                The SimulationService (US-056) will provide complete price resolution including:
                                allocation verification, Price Book lookup, Offer application, and final price calculation
                                with full breakdown and error reporting.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Errors Section (if any) --}}
                @if(!empty($simulationResult['errors']))
                    <div class="mt-6 rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 text-danger-600 dark:text-danger-400">
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-danger-800 dark:text-danger-200">Simulation Errors</h4>
                                <ul class="mt-2 text-sm text-danger-700 dark:text-danger-300 list-disc list-inside">
                                    @foreach($simulationResult['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
