<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Description --}}
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                    <x-heroicon-o-chart-bar-square class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Demand Aggregation View
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        View procurement intents aggregated by product to identify total demand volumes.
                        This view is useful for bulk procurement decisions - click "View Intents" to see individual intents for each product.
                    </p>
                </div>
            </div>
        </div>

        {{-- Table --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
