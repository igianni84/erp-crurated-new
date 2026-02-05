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
                    @php
                        $overallStatus = $simulationResult['status'] ?? 'pending';
                        $statusBadgeClasses = match($overallStatus) {
                            'success' => 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200',
                            'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200',
                            'error' => 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200',
                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusBadgeClasses }}">
                        {{ ucfirst($overallStatus) }}
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
                            <p class="font-mono text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['sku_code'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Channel</p>
                            <p class="text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['channel'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Customer</p>
                            <p class="text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['customer'] ?? 'Anonymous' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Date / Quantity</p>
                            <p class="text-gray-900 dark:text-gray-100">{{ $simulationResult['context']['date'] ?? 'N/A' }} / {{ $simulationResult['context']['quantity'] ?? 1 }} unit(s)</p>
                        </div>
                    </div>
                </div>

                {{-- Final Price Highlight (if available) --}}
                @if(isset($simulationResult['steps']['final']) && $simulationResult['steps']['final']['status'] === 'success' && isset($simulationResult['steps']['final']['final_price']))
                    <div class="rounded-lg bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800 p-6 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-success-600 dark:text-success-400">Final Price</p>
                                <p class="text-3xl font-bold text-success-800 dark:text-success-200 mt-1">
                                    {{ $simulationResult['steps']['final']['currency'] ?? 'EUR' }} {{ $simulationResult['steps']['final']['final_price'] }}
                                </p>
                                @if(isset($simulationResult['steps']['final']['details']['total_price']) && ($simulationResult['context']['quantity'] ?? 1) > 1)
                                    <p class="text-sm text-success-600 dark:text-success-400 mt-1">
                                        Total for {{ $simulationResult['context']['quantity'] }} units: {{ $simulationResult['steps']['final']['details']['total_price'] }}
                                    </p>
                                @endif
                            </div>
                            <div class="text-right">
                                @if(isset($simulationResult['steps']['final']['details']['base_price']))
                                    <p class="text-sm text-success-600 dark:text-success-400">
                                        Base: {{ $simulationResult['steps']['final']['details']['base_price'] }}
                                    </p>
                                @endif
                                @if(isset($simulationResult['steps']['final']['details']['discount']) && $simulationResult['steps']['final']['details']['discount'] !== 'None')
                                    <p class="text-sm text-success-600 dark:text-success-400">
                                        Discount: {{ $simulationResult['steps']['final']['details']['discount'] }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        @if(isset($simulationResult['steps']['final']['explanation']))
                            <p class="text-sm text-success-700 dark:text-success-300 mt-3 border-t border-success-200 dark:border-success-800 pt-3">
                                <span class="font-medium">Calculation:</span> {{ $simulationResult['steps']['final']['explanation'] }}
                            </p>
                        @endif
                    </div>
                @endif

                {{-- Resolution Steps --}}
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Resolution Steps</h4>
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
                            $stepNumber = match($stepKey) {
                                'allocation' => 1,
                                'emp' => 2,
                                'price_book' => 3,
                                'offer' => 4,
                                'final' => 5,
                                default => 0,
                            };
                        @endphp

                        <div class="rounded-lg border {{ $statusBg }} p-4">
                            <div class="flex items-start gap-3">
                                {{-- Step Number Badge --}}
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white dark:bg-gray-900 border-2 {{ $statusBg }} flex items-center justify-center">
                                    <span class="text-sm font-bold {{ $iconColor }}">{{ $stepNumber }}</span>
                                </div>
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
                                                    @if($key !== 'rationale')
                                                        <div>
                                                            <dt class="text-gray-500 dark:text-gray-400 uppercase">{{ str_replace('_', ' ', $key) }}</dt>
                                                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </dl>
                                            @if(isset($step['details']['rationale']))
                                                <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                                        <span class="font-medium">Rationale:</span> {{ $step['details']['rationale'] }}
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Warnings Section --}}
                @if(!empty($simulationResult['warnings']))
                    <div class="mt-6 rounded-lg bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-800 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 text-warning-600 dark:text-warning-400">
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-warning-800 dark:text-warning-200">Warnings</h4>
                                <ul class="mt-2 text-sm text-warning-700 dark:text-warning-300 list-disc list-inside">
                                    @foreach($simulationResult['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Errors Section --}}
                @if(!empty($simulationResult['errors']))
                    <div class="mt-6 rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 text-danger-600 dark:text-danger-400">
                                <x-heroicon-o-x-circle class="h-5 w-5" />
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-danger-800 dark:text-danger-200">Blocking Errors</h4>
                                <p class="text-sm text-danger-600 dark:text-danger-400 mt-1">
                                    The following issues prevented price calculation:
                                </p>
                                <ul class="mt-2 text-sm text-danger-700 dark:text-danger-300 list-disc list-inside">
                                    @foreach($simulationResult['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Info Notice --}}
                <div class="mt-6 rounded-lg bg-info-50 dark:bg-info-950 border border-info-200 dark:border-info-800 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 text-info-600 dark:text-info-400">
                            <x-heroicon-o-information-circle class="h-5 w-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-info-800 dark:text-info-200">About This Simulation</h4>
                            <p class="text-sm text-info-700 dark:text-info-300 mt-1">
                                This simulation shows the complete price resolution pipeline: Allocation verification,
                                EMP reference lookup, Price Book resolution, Offer application, and final price calculation.
                                Each step shows the source data and rationale for the resolution.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
