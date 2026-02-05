<x-filament-panels::page>
    {{-- Back Navigation --}}
    <div class="mb-6">
        <a href="{{ \App\Filament\Pages\PricingIntelligence::getUrl() }}"
           class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">
            <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
            Back to Pricing Intelligence
        </a>
    </div>

    {{-- Page Description --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <x-heroicon-o-information-circle class="h-5 w-5 text-info-500" />
            </div>
            <div class="ml-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>Pricing Intelligence Detail</strong> provides a comprehensive view of Estimated Market Prices (EMP) for a specific Sellable SKU.
                    This view is read-only. EMP data is imported from external sources and serves as a benchmark for pricing decisions.
                </p>
            </div>
        </div>
    </div>

    {{-- Infolist with Tabs --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        {{ $this->infolist }}
    </div>
</x-filament-panels::page>
