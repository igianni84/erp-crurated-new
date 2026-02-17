<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-bell-alert class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                <span>Operational Alerts</span>
            </div>
        </x-slot>

        @php
            $alerts = $this->getAlerts();

            $severityClasses = [
                'danger' => [
                    'bg' => 'bg-danger-50 dark:bg-danger-400/10',
                    'text' => 'text-danger-600 dark:text-danger-400',
                    'icon' => 'text-danger-500 dark:text-danger-400',
                    'badge' => 'bg-danger-100 text-danger-700 dark:bg-danger-400/20 dark:text-danger-400',
                ],
                'warning' => [
                    'bg' => 'bg-warning-50 dark:bg-warning-400/10',
                    'text' => 'text-warning-600 dark:text-warning-400',
                    'icon' => 'text-warning-500 dark:text-warning-400',
                    'badge' => 'bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400',
                ],
            ];
        @endphp

        @if(count($alerts) === 0)
            <div class="flex flex-col items-center justify-center py-6">
                <div class="mb-2 rounded-full bg-success-50 p-2 dark:bg-success-400/10">
                    <x-heroicon-o-check-circle class="h-8 w-8 text-success-500 dark:text-success-400" />
                </div>
                <p class="text-sm font-medium text-success-600 dark:text-success-400">All Clear!</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">No operational issues detected</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($alerts as $alert)
                    @php
                        $classes = $severityClasses[$alert['severity']] ?? $severityClasses['warning'];
                    @endphp
                    <a
                        href="{{ $alert['url'] }}"
                        class="flex items-center justify-between rounded-lg p-3 transition hover:opacity-80 {{ $classes['bg'] }}"
                    >
                        <div class="flex items-center gap-3">
                            <x-dynamic-component :component="$alert['icon']" class="h-5 w-5 {{ $classes['icon'] }}" />
                            <span class="text-sm font-medium {{ $classes['text'] }}">{{ $alert['label'] }}</span>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold {{ $classes['badge'] }}">
                            {{ $alert['count'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
