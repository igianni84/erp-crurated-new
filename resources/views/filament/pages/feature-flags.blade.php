<x-filament-panels::page>
    @php
        $features = $this->getFeatures();
    @endphp

    {{-- Header Info --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/20 p-2">
                <x-heroicon-o-flag class="h-5 w-5 text-primary-600 dark:text-primary-400" />
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Runtime Feature Flags</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Toggle features without redeploying. Overrides take effect immediately. Reset returns to env/config default.
                </p>
            </div>
        </div>
    </div>

    {{-- Feature List --}}
    <div class="space-y-3">
        @foreach($features as $feature)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" wire:key="{{ $feature['class'] }}">
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between gap-4">
                        {{-- Left: Feature Info --}}
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex-shrink-0 rounded-lg {{ $feature['active'] ? 'bg-success-100 dark:bg-success-900/20' : 'bg-gray-100 dark:bg-gray-800' }} p-2">
                                @if($feature['active'])
                                    <x-heroicon-o-check-circle class="h-5 w-5 text-success-600 dark:text-success-400" />
                                @else
                                    <x-heroicon-o-x-circle class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $feature['name'] }}</h3>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $feature['active'] ? 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                        {{ $feature['active'] ? 'Active' : 'Inactive' }}
                                    </span>
                                    @if($feature['has_override'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400">
                                            Override
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $feature['description'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 font-mono">{{ $feature['env_fallback'] }}</p>
                            </div>
                        </div>

                        {{-- Right: Actions --}}
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($feature['has_override'])
                                <button
                                    type="button"
                                    wire:click="resetFeature('{{ $feature['class'] }}')"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-gray-700 bg-white ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600 dark:hover:bg-gray-700"
                                >
                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5 mr-1" />
                                    Reset
                                </button>
                            @endif

                            <button
                                type="button"
                                wire:click="toggleFeature('{{ $feature['class'] }}')"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg {{ $feature['active'] ? 'text-danger-700 bg-danger-50 ring-1 ring-danger-200 hover:bg-danger-100 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30 dark:hover:bg-danger-400/20' : 'text-success-700 bg-success-50 ring-1 ring-success-200 hover:bg-success-100 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30 dark:hover:bg-success-400/20' }}"
                            >
                                @if($feature['active'])
                                    <x-heroicon-o-pause class="h-3.5 w-3.5 mr-1" />
                                    Disable
                                @else
                                    <x-heroicon-o-play class="h-3.5 w-3.5 mr-1" />
                                    Enable
                                @endif
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
