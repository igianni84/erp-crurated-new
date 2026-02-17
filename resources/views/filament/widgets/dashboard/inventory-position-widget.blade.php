<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-archive-box class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                <span>Inventory Position</span>
            </div>
        </x-slot>

        @php
            $bottlesByState = $this->getBottlesByState();
            $totalBottles = $this->getTotalBottles();
            $caseIntegrity = $this->getCaseIntegrity();
            $locationCount = $this->getLocationCount();

            $filamentColors = [
                'success' => 'bg-success-500',
                'warning' => 'bg-warning-500',
                'info' => 'bg-info-500',
                'danger' => 'bg-danger-500',
                'gray' => 'bg-gray-400',
                'primary' => 'bg-primary-500',
            ];
        @endphp

        <div class="space-y-4">
            {{-- Summary Metrics --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Bottles</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalBottles) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cases Intact</p>
                    <p class="text-lg font-bold text-success-600 dark:text-success-400">{{ number_format($caseIntegrity['intact']) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Locations</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($locationCount) }}</p>
                </div>
            </div>

            {{-- Bottles by State Bar --}}
            @if($totalBottles > 0)
                <div>
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Bottles by State</p>
                    <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        @foreach($bottlesByState as $state)
                            <div
                                class="{{ $filamentColors[$state['color']] ?? 'bg-gray-400' }}"
                                style="width: {{ ($state['count'] / $totalBottles) * 100 }}%"
                                title="{{ $state['label'] }}: {{ number_format($state['count']) }}"
                            ></div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        @foreach($bottlesByState as $state)
                            <div class="flex items-center gap-1">
                                <div class="h-2 w-2 rounded-full {{ $filamentColors[$state['color']] ?? 'bg-gray-400' }}"></div>
                                <span class="text-xs text-gray-600 dark:text-gray-400">{{ $state['label'] }} ({{ number_format($state['count']) }})</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Case Integrity --}}
            @if($caseIntegrity['broken'] > 0)
                <div class="flex items-center gap-2 rounded-lg bg-danger-50 p-2 dark:bg-danger-400/10">
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4 text-danger-600 dark:text-danger-400" />
                    <span class="text-xs font-medium text-danger-600 dark:text-danger-400">
                        {{ number_format($caseIntegrity['broken']) }} broken cases
                    </span>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
